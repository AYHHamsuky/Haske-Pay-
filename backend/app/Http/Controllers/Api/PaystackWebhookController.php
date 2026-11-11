<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\VendCheckoutOrder;
use App\Models\CheckoutOrder;
use App\Services\Funding;
use App\Services\Payments\Paystack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, Paystack $paystack, Funding $funding)
    {
        $raw = $request->getContent();
        if (! $paystack->validSignature($raw, $request->header('x-paystack-signature'))) {
            Log::warning('paystack.bad_signature', ['ip' => $request->ip()]);

            return response()->json(['message' => 'invalid signature'], 401);
        }

        $event = json_decode($raw, true) ?: [];
        $result = $funding->handlePaystackEvent($event);
        if ($result === 'checkout' && ($order = CheckoutOrder::where('reference', $event['data']['reference'])->first())) {
            VendCheckoutOrder::dispatch($order->id);
        }

        // Always 200 for a valid signature, including duplicates, so Paystack stops retrying.
        return response()->json(['ok' => true, 'result' => $result]);
    }
}
