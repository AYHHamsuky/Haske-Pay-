<?php

namespace App\Services;

use App\Models\CheckoutOrder;
use App\Models\FundingEvent;
use App\Models\VirtualAccount;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns verified Paystack events into wallet credits. Each Paystack reference is
 * stored once in funding_events, so a replayed webhook changes nothing.
 */
class Funding
{
    public function __construct(private readonly Ledger $ledger) {}

    /** @return string what happened: credited, duplicate, ignored, checkout */
    public function handlePaystackEvent(array $event): string
    {
        if (($event['event'] ?? null) !== 'charge.success') {
            return 'ignored';
        }
        $data = $event['data'] ?? [];
        $reference = (string) ($data['reference'] ?? '');
        $amount = (int) ($data['amount'] ?? 0); // Paystack sends kobo
        if ($reference === '' || $amount <= 0 || ($data['status'] ?? 'success') !== 'success') {
            return 'ignored';
        }

        if (FundingEvent::where('reference', $reference)->exists()) {
            Log::info('paystack.duplicate', ['reference' => $reference]);

            return 'duplicate';
        }

        // Web checkout payment: credit the buyer's wallet, then vend.
        if ($order = CheckoutOrder::where('reference', $reference)->first()) {
            return $this->record($reference, $event, $amount, $order->user_id, 'checkout', function () use ($order, $amount, $reference) {
                $this->ledger->credit($order->user, $amount, 'PSK_'.$reference, 'funding', ['description' => 'Web checkout payment']);
                $order->update(['status' => 'paid']);
            });
        }

        // Dedicated virtual account transfer: match on the Paystack customer code.
        $customerCode = $data['customer']['customer_code'] ?? null;
        $account = $customerCode ? VirtualAccount::where('provider_ref', $customerCode)->first() : null;
        if (! $account) {
            $acct = $data['metadata']['receiver_account_number'] ?? $data['authorization']['receiver_bank_account_number'] ?? null;
            $account = $acct ? VirtualAccount::where('account_number', $acct)->first() : null;
        }
        if (! $account) {
            Log::warning('paystack.unmatched', ['reference' => $reference]);

            return $this->record($reference, $event, $amount, null, 'ignored', fn () => null);
        }

        return $this->record($reference, $event, $amount, $account->user_id, 'credited', function () use ($account, $amount, $reference) {
            $this->ledger->credit($account->user, $amount, 'PSK_'.$reference, 'funding', ['description' => 'Bank transfer to '.$account->bank]);
        });
    }

    private function record(string $reference, array $event, int $amount, ?int $userId, string $status, callable $apply): string
    {
        try {
            DB::transaction(function () use ($reference, $event, $amount, $userId, $status, $apply) {
                FundingEvent::create([
                    'event' => $event['event'],
                    'reference' => $reference,
                    'user_id' => $userId,
                    'amount' => $amount,
                    'status' => $status,
                    'payload' => $event,
                ]);
                $apply();
            });
        } catch (QueryException $e) {
            if (FundingEvent::where('reference', $reference)->exists()) {
                return 'duplicate';
            }
            throw $e;
        }

        return $status;
    }
}
