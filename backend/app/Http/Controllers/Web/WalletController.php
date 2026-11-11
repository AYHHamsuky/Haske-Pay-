<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\PurchaseRejected;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Controller;
use App\Models\SavedMeter;
use App\Models\Service;
use App\Models\SupportTicket;
use App\Services\AgentReports;
use App\Services\Funding;
use App\Services\Payments\Paystack;
use App\Services\PinGuard;
use App\Services\PurchaseService;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class WalletController extends Controller
{
    public function dashboard(Request $request, Paystack $paystack, AgentReports $reports)
    {
        $user = $request->user();
        if (! $user->virtualAccount) {
            try {
                $paystack->ensureVirtualAccount($user);
            } catch (Throwable $e) {
                report($e);
            }
        }

        $done = $user->transactions()->where('status', 'success');

        return Inertia::render('Wallet/Dashboard', [
            'me' => MeController::present($user->fresh()),
            'recent' => $user->transactions()->with('service')->latest()->limit(5)->get()->map->toApi(),
            'stats' => [
                'successful' => (clone $done)->count(),
                'spent' => (int) (clone $done)->sum(DB::raw('amount + fee')),
                'saved_meters' => $user->savedMeters()->count(),
            ],
            'agent' => $user->isAgent() ? $reports->summary($user, 7) : null,
        ]);
    }

    public function wallet(Request $request, Paystack $paystack)
    {
        $user = $request->user();
        if (! $user->virtualAccount && $user->name) {
            try {
                $paystack->ensureVirtualAccount($user);
            } catch (Throwable $e) {
                report($e);
            }
        }
        $type = in_array($request->query('type'), ['credit', 'debit'], true) ? $request->query('type') : null;
        $entries = $user->walletOrCreate()->entries()->latest('id')
            ->when($type, fn ($q) => $q->where('type', $type))
            ->paginate(20, ['id', 'type', 'amount', 'reference', 'reason', 'description', 'balance_after', 'created_at'])->withQueryString();

        return Inertia::render('Wallet/Wallet', [
            'me' => MeController::present($user->fresh()),
            'entries' => $entries,
            'filter' => $type,
        ]);
    }

    public function buy(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Wallet/Buy', [
            'services' => CatalogController::catalog($user),
            'savedMeters' => $user->savedMeters()->latest()->get(['id', 'service_code', 'meter_number', 'meter_type', 'customer_name']),
            'balance' => $user->walletOrCreate()->balance,
            'category' => $request->query('c', 'electricity'),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function purchase(Request $request, PinGuard $pin, PurchaseService $purchases)
    {
        $data = $request->validate([
            'service_code' => 'required|exists:services,code',
            'target' => 'required|string|max:20',
            'amount' => 'nullable|integer|min:1',
            'meter_type' => 'nullable|in:prepaid,postpaid',
            'variation_code' => 'nullable|string|max:60',
            'customer_phone' => 'nullable|string',
            'pin' => 'required|digits:4',
            'idempotency_key' => 'required|string|min:8|max:64',
        ]);
        $user = $request->user();
        $pin->verify($user, $data['pin']);
        if (Service::byCode($data['service_code'])->category === 'electricity') {
            if (! preg_match('/^(\d{11}|\d{13})$/', $data['target'])) {
                throw ValidationException::withMessages(['purchase' => 'Meter numbers have 11 or 13 digits.']);
            }
        } else {
            $data['target'] = Phone::normalize($data['target']) ?? throw ValidationException::withMessages(['purchase' => 'Enter a valid phone number.']);
        }
        if (! empty($data['customer_phone'])) {
            $data['customer_phone'] = Phone::normalize($data['customer_phone']);
        }

        try {
            $tx = $purchases->buy($user, $data + ['channel' => $user->isAgent() ? 'agent' : 'web']);
        } catch (PurchaseRejected $e) {
            throw ValidationException::withMessages(['purchase' => $e->getMessage()]);
        }

        if ($tx->category === 'electricity' && $tx->customer_name && ! $user->isAgent()) {
            SavedMeter::firstOrCreate(
                ['user_id' => $user->id, 'service_code' => $tx->service_code, 'meter_number' => $tx->target],
                ['meter_type' => $tx->meter_type, 'customer_name' => $tx->customer_name],
            );
        }

        return redirect()->route('wallet.receipt', $tx->reference);
    }

    public function history(Request $request)
    {
        return Inertia::render('Wallet/History', [
            'transactions' => $this->historyQuery($request)->paginate(20)->withQueryString()->through(fn ($t) => $t->toApi()),
            'filters' => array_filter($request->only('status', 'q')),
        ]);
    }

    public function historyCsv(Request $request)
    {
        $rows = $this->historyQuery($request)->limit(5000)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Date', 'Service', 'Recipient', 'Amount (NGN)', 'Fee (NGN)', 'Status', 'Token', 'Units']);
            foreach ($rows as $t) {
                fputcsv($out, [$t->reference, $t->created_at->timezone('Africa/Lagos')->format('Y-m-d H:i'), trim(($t->service?->name ?? $t->service_code).' '.($t->variation_name ?? '')),
                    $t->target, $t->amount / 100, $t->fee / 100, $t->status, $t->token, $t->units]);
            }
            fclose($out);
        }, 'haske-transactions-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function historyQuery(Request $request)
    {
        $q = $request->user()->transactions()->with('service')->latest();
        if (in_array($s = $request->query('status'), ['success', 'pending', 'refunded', 'failed'], true)) {
            $q->where('status', $s);
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('reference', 'like', "%{$term}%")->orWhere('target', 'like', "%{$term}%")->orWhere('service_code', 'like', "%{$term}%"));
        }

        return $q;
    }

    public function receipt(Request $request, string $reference)
    {
        $tx = $request->user()->transactions()->with('service')->where('reference', $reference)->firstOrFail();

        return Inertia::render('Wallet/Receipt', ['tx' => $tx->toApi()]);
    }

    public function requery(Request $request, string $reference, PurchaseService $purchases)
    {
        $tx = $request->user()->transactions()->where('reference', $reference)->firstOrFail();
        $purchases->requery($tx);

        return back();
    }

    public function profile(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Wallet/Profile', [
            'me' => MeController::present($user),
            'savedMeters' => $user->savedMeters()->latest()->get(),
        ]);
    }

    public function help(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Wallet/Help', [
            'tickets' => SupportTicket::where('user_id', $user->id)->latest()->limit(10)->get(['id', 'reference', 'type', 'status', 'reply', 'created_at']),
            'types' => SupportTicket::TYPES,
            'recent' => $user->transactions()->with('service')->latest()->limit(10)->get()->map(fn ($t) => [
                'reference' => $t->reference, 'label' => ($t->service?->name ?? $t->service_code).' · '.Money::format($t->total()).' · '.$t->status,
            ]),
        ]);
    }

    public function helpStore(Request $request)
    {
        $data = $request->validate([
            'reference' => 'nullable|string|max:40',
            'type' => 'required|in:'.implode(',', array_keys(SupportTicket::TYPES)),
            'message' => 'required|string|min:10|max:2000',
        ]);
        $user = $request->user();
        $tx = ! empty($data['reference']) ? $user->transactions()->where('reference', trim($data['reference']))->first() : null;
        if (! empty($data['reference']) && ! $tx) {
            throw ValidationException::withMessages(['reference' => 'We could not find that reference on your account. Leave it blank if you are not sure.']);
        }
        $ticket = SupportTicket::create($data + ['user_id' => $user->id, 'transaction_id' => $tx?->id]);

        return back()->with('success', "Request SUP-{$ticket->id} received. We will reply here and by SMS.");
    }

    public function updateProfile(Request $request)
    {
        $request->user()->update($request->validate(['name' => 'required|string|min:3|max:80', 'locale' => 'required|in:en,ha']));

        return back()->with('success', 'Profile saved.');
    }

    public function setPin(Request $request, PinGuard $guard)
    {
        $data = $request->validate([
            'pin' => ['required', 'digits:4', 'confirmed', 'not_in:0000,1111,1234,4321,2222,9999'],
            'current_pin' => 'nullable|digits:4',
        ], ['pin.not_in' => 'Choose a PIN that is harder to guess.']);
        $user = $request->user();
        if ($user->hasPin()) {
            $guard->verify($user, $data['current_pin'] ?? null);
        }
        $user->setPin($data['pin']);

        return back()->with('success', 'Transaction PIN saved.');
    }

    public function deleteMeter(Request $request, int $id)
    {
        $request->user()->savedMeters()->whereKey($id)->delete();

        return back();
    }

    /** Sandbox only: pretend the user made a bank transfer to their virtual account. */
    public function sandboxFund(Request $request, Funding $funding)
    {
        abort_unless(config('haske.drivers.payments') === 'fake', 404);
        $data = $request->validate(['amount' => 'required|integer|min:100|max:1000000']);
        $va = $request->user()->virtualAccount ?? abort(422, 'No virtual account yet.');
        $funding->handlePaystackEvent(['event' => 'charge.success', 'data' => [
            'reference' => 'SBX'.strtoupper(Str::random(12)),
            'amount' => $data['amount'] * 100,
            'status' => 'success',
            'channel' => 'dedicated_nuban',
            'customer' => ['customer_code' => $va->provider_ref],
            'authorization' => ['bank' => 'Sandbox bank transfer'],
        ]]);

        return back()->with('success', 'Received ₦'.number_format($data['amount']).' by bank transfer.');
    }
}
