<?php

namespace App\Jobs;

use App\Exceptions\PurchaseRejected;
use App\Models\CheckoutOrder;
use App\Services\PurchaseService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** Runs after Paystack confirms a web checkout payment; the wallet is already credited. */
class VendCheckoutOrder implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $orderId) {}

    public function handle(PurchaseService $purchases): void
    {
        $order = CheckoutOrder::with('user')->findOrFail($this->orderId);
        if ($order->transaction_id || $order->status !== 'paid') {
            return;
        }

        try {
            $tx = $purchases->buy($order->user, [
                'service_code' => $order->service_code,
                'target' => $order->target,
                'amount' => $order->amount,
                'meter_type' => $order->meter_type,
                'variation_code' => $order->variation_code,
                'customer_phone' => $order->user->phone,
                'idempotency_key' => 'checkout-'.$order->reference,
                'channel' => 'web',
            ]);
            $order->update(['transaction_id' => $tx->id]);
        } catch (PurchaseRejected $e) {
            // The money stays in the buyer's wallet; they can retry or use the app.
            Log::warning('checkout.vend_rejected', ['order' => $order->reference, 'error' => $e->getMessage()]);
        }
    }
}
