<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PurchaseRejected;
use App\Http\Controllers\Controller;
use App\Models\FundingEvent;
use App\Models\SavedMeter;
use App\Models\Service;
use App\Services\PinGuard;
use App\Services\PurchaseService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    public function __construct(private readonly PurchaseService $purchases, private readonly PinGuard $pin) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'service_code' => 'required|string|exists:services,code',
            'target' => 'required|string|max:20',
            'amount' => 'nullable|integer|min:1', // kobo; ignored for data plans
            'meter_type' => 'nullable|in:prepaid,postpaid',
            'variation_code' => 'nullable|string|max:60',
            'customer_phone' => 'nullable|string',
            'pin' => 'required|digits:4',
            'idempotency_key' => 'required|string|min:8|max:64',
            'save_meter' => 'boolean',
        ]);
        $user = $request->user();
        $this->pin->verify($user, $data['pin']);

        $service = Service::byCode($data['service_code']);
        if (in_array($service->category, ['airtime', 'data'], true)) {
            $data['target'] = Phone::normalize($data['target']) ?? throw ValidationException::withMessages(['target' => 'Enter a valid phone number.']);
        } elseif (! preg_match('/^(\d{11}|\d{13})$/', $data['target'])) {
            throw ValidationException::withMessages(['target' => 'Meter numbers have 11 or 13 digits.']);
        }
        if (! empty($data['customer_phone'])) {
            $data['customer_phone'] = Phone::normalize($data['customer_phone']);
        }

        try {
            $tx = $this->purchases->buy($user, $data + ['channel' => $user->isAgent() ? 'agent' : 'app']);
        } catch (PurchaseRejected $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->errorCode], $e->status);
        }

        if ($tx->category === 'electricity' && ($data['save_meter'] ?? true) && $tx->customer_name) {
            SavedMeter::firstOrCreate(
                ['user_id' => $user->id, 'service_code' => $tx->service_code, 'meter_number' => $tx->target],
                ['meter_type' => $tx->meter_type, 'customer_name' => $tx->customer_name],
            );
        }

        return response()->json(['transaction' => $tx->load('service')->toApi(), 'balance' => $user->wallet()->value('balance')], 201);
    }

    /** Purchases and wallet top-ups, newest first. */
    public function index(Request $request)
    {
        $user = $request->user();
        $type = $request->query('type', 'all');
        $items = collect();

        if ($type !== 'funding') {
            $items = $items->merge($user->transactions()->with('service')->latest()->limit(100)->get()->map->toApi()->map(fn ($t) => $t + ['kind' => 'purchase']));
        }
        if ($type !== 'bills') {
            $items = $items->merge(FundingEvent::where('user_id', $user->id)->whereIn('status', ['credited', 'checkout'])->latest()->limit(100)->get()->map(fn ($f) => [
                'kind' => 'funding',
                'reference' => $f->reference,
                'amount' => $f->amount,
                'total' => $f->amount,
                'status' => 'success',
                'target' => $f->payload['data']['authorization']['bank'] ?? 'Bank transfer',
                'created_at' => $f->created_at->toIso8601String(),
            ]));
        }

        return response()->json($items->sortByDesc('created_at')->values()->take(100));
    }

    public function show(Request $request, string $reference)
    {
        $tx = $request->user()->transactions()->with('service')->where('reference', $reference)->firstOrFail();

        return response()->json($tx->toApi());
    }

    public function requery(Request $request, string $reference)
    {
        $tx = $request->user()->transactions()->where('reference', $reference)->firstOrFail();
        $key = 'manual-requery:'.$tx->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return response()->json(['message' => 'Checked a moment ago. Try again in a few seconds.', 'transaction' => $tx->load('service')->toApi()], 429);
        }
        RateLimiter::hit($key, 15);
        $tx = $this->purchases->requery($tx);

        return response()->json($tx->load('service')->toApi());
    }

    public function savedMeters(Request $request)
    {
        return response()->json($request->user()->savedMeters()->latest()->get(['id', 'service_code', 'meter_number', 'meter_type', 'customer_name', 'label']));
    }

    public function deleteMeter(Request $request, int $id)
    {
        $request->user()->savedMeters()->whereKey($id)->delete();

        return response()->json(['ok' => true]);
    }
}
