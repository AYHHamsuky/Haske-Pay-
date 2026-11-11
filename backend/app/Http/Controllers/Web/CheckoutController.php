<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Controller;
use App\Jobs\VendCheckoutOrder;
use App\Models\CheckoutOrder;
use App\Models\Service;
use App\Models\User;
use App\Services\Funding;
use App\Services\MeterVerifier;
use App\Services\Payments\Paystack;
use App\Services\Providers\ProviderManager;
use App\Services\PurchaseService;
use App\Support\Money;
use App\Support\Phone;
use App\Support\References;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Buy without the app: pay Paystack by card or transfer, the payment lands in a
 * wallet under the buyer's phone number, then we vend from it. A failed vend
 * refunds to that wallet, which the buyer can spend or withdraw via support.
 */
class CheckoutController extends Controller
{
    public function index()
    {
        return Inertia::render('Checkout/Index', [
            'discos' => Service::where('category', 'electricity')->orderBy('id')->get(['code', 'name', 'short_name', 'active']),
        ]);
    }

    public function pay(Request $request)
    {
        return Inertia::render('Checkout/Pay', [
            'services' => CatalogController::catalog(null),
            'category' => $request->query('c', 'electricity'),
        ]);
    }

    public function store(Request $request, Paystack $paystack, PurchaseService $purchases, MeterVerifier $verifier, ProviderManager $providers)
    {
        $data = $request->validate([
            'service_code' => 'required|exists:services,code',
            'target' => 'required|string|max:20',
            'amount' => 'nullable|integer|min:1',
            'meter_type' => 'nullable|in:prepaid,postpaid',
            'variation_code' => 'nullable|string|max:60',
            'customer_phone' => 'required|string',
        ]);
        $phone = Phone::normalize($data['customer_phone']) ?? throw ValidationException::withMessages(['customer_phone' => 'Enter a valid phone number.']);
        $service = Service::byCode($data['service_code']);
        if (! $service->active) {
            throw ValidationException::withMessages(['service_code' => 'This service is temporarily unavailable.']);
        }

        $amount = (int) ($data['amount'] ?? 0);
        $customerName = null;
        if ($service->category === 'electricity') {
            $v = $verifier->verify($service, $data['target'], $data['meter_type'] ?? 'prepaid');
            if (! $v->ok) {
                throw ValidationException::withMessages(['target' => $v->error]);
            }
            $customerName = $v->name;
        } else {
            $data['target'] = Phone::normalize($data['target']) ?? throw ValidationException::withMessages(['target' => 'Enter a valid phone number.']);
        }
        if ($service->category === 'data') {
            $plan = collect($providers->get($service->provider)->dataPlans($service))->firstWhere('code', $data['variation_code']);
            $amount = $plan['amount'] ?? throw ValidationException::withMessages(['variation_code' => 'Pick a plan.']);
        }
        if ($amount < $service->min_amount || $amount > $service->max_amount) {
            throw ValidationException::withMessages(['amount' => 'Enter between '.Money::format($service->min_amount).' and '.Money::format($service->max_amount).'.']);
        }

        $user = User::firstOrCreate(['phone' => $phone]);
        $user->walletOrCreate();
        $q = $purchases->quote($user, $service, $amount);

        $order = CheckoutOrder::create([
            'reference' => References::checkout(),
            'user_id' => $user->id,
            'service_code' => $service->code,
            'target' => $data['target'],
            'meter_type' => $service->category === 'electricity' ? ($data['meter_type'] ?? 'prepaid') : null,
            'variation_code' => $data['variation_code'] ?? null,
            'customer_name' => $customerName,
            'amount' => $amount,
            'fee' => $q['fee'],
        ]);

        $url = $paystack->initialize($order->reference, $q['total'], $paystack->placeholderEmail($user),
            route('checkout.callback'), ['order' => $order->reference, 'phone' => $phone]);

        return Inertia::location($url);
    }

    public function show(string $reference)
    {
        $order = CheckoutOrder::with('transaction.service', 'service')->where('reference', $reference)->firstOrFail();

        return Inertia::render('Checkout/Status', [
            'order' => [
                'reference' => $order->reference,
                'status' => $order->status,
                'service' => $order->service?->name,
                'short_name' => $order->service?->short_name,
                'category' => $order->service?->category,
                'target' => $order->target,
                'customer_name' => $order->customer_name,
                'amount' => $order->amount,
                'fee' => $order->fee,
                'created_at' => $order->created_at->toIso8601String(),
                'transaction' => $order->transaction?->toApi(),
            ],
        ]);
    }

    /** Paystack redirects the buyer here. The webhook is the source of truth; this only speeds it up. */
    public function callback(Request $request, Paystack $paystack, Funding $funding)
    {
        $ref = (string) $request->query('reference', $request->query('trxref', ''));
        $order = CheckoutOrder::where('reference', $ref)->firstOrFail();
        if ($order->status === 'awaiting_payment' && ($v = $paystack->verify($ref)) && $v['status'] === 'success') {
            $this->credit($funding, $order, $v['amount'], 'callback');
        }

        return redirect()->route('checkout.show', $order->reference);
    }

    public function sandboxPay(string $reference)
    {
        abort_unless(config('haske.drivers.payments') === 'fake', 404);
        $order = CheckoutOrder::with('service')->where('reference', $reference)->firstOrFail();

        return Inertia::render('Checkout/SandboxPay', ['order' => [
            'reference' => $order->reference, 'total' => $order->amount + $order->fee,
            'service' => $order->service->name, 'target' => $order->target, 'status' => $order->status,
        ]]);
    }

    public function sandboxComplete(string $reference, Funding $funding)
    {
        abort_unless(config('haske.drivers.payments') === 'fake', 404);
        $order = CheckoutOrder::where('reference', $reference)->firstOrFail();
        $this->credit($funding, $order, $order->amount + $order->fee, 'sandbox');

        return redirect()->route('checkout.show', $order->reference);
    }

    private function credit(Funding $funding, CheckoutOrder $order, int $amount, string $source): void
    {
        $result = $funding->handlePaystackEvent(['event' => 'charge.success', 'source' => $source, 'data' => [
            'reference' => $order->reference, 'amount' => $amount, 'status' => 'success',
        ]]);
        if ($result === 'checkout') {
            VendCheckoutOrder::dispatchSync($order->id);
        }
    }

    public function poll(string $reference, PurchaseService $purchases)
    {
        $order = CheckoutOrder::with('transaction.service')->where('reference', $reference)->firstOrFail();

        return response()->json(['status' => $order->status, 'transaction' => $order->transaction?->toApi()]);
    }
}
