<?php

namespace App\Services;

use App\Exceptions\InsufficientFunds;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only place money moves. Each call locks the wallet row, appends one ledger
 * entry and updates the cached balance in the same DB transaction.
 *
 * References are unique, so posting the same reference twice is a no-op that
 * returns the existing entry. That makes refunds and webhook credits replay-safe.
 */
class Ledger
{
    public function credit(User|Wallet $owner, int $amount, string $reference, string $reason, array $extra = []): LedgerEntry
    {
        return $this->post($owner, 'credit', $amount, $reference, $reason, $extra);
    }

    /** @throws InsufficientFunds */
    public function debit(User|Wallet $owner, int $amount, string $reference, string $reason, array $extra = []): LedgerEntry
    {
        return $this->post($owner, 'debit', $amount, $reference, $reason, $extra);
    }

    private function post(User|Wallet $owner, string $type, int $amount, string $reference, string $reason, array $extra): LedgerEntry
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Ledger amounts must be positive kobo.');
        }
        $walletId = $owner instanceof Wallet ? $owner->id : $owner->walletOrCreate()->id;

        try {
            return DB::transaction(function () use ($walletId, $type, $amount, $reference, $reason, $extra) {
                if ($existing = LedgerEntry::where('reference', $reference)->first()) {
                    return $existing;
                }
                $wallet = Wallet::whereKey($walletId)->lockForUpdate()->firstOrFail();
                $after = $type === 'credit' ? $wallet->balance + $amount : $wallet->balance - $amount;
                if ($after < 0) {
                    throw new InsufficientFunds($amount - $wallet->balance);
                }
                $wallet->balance = $after;
                $wallet->save();

                return LedgerEntry::create([
                    'wallet_id' => $wallet->id,
                    'type' => $type,
                    'amount' => $amount,
                    'reference' => $reference,
                    'reason' => $reason,
                    'balance_after' => $after,
                    'description' => $extra['description'] ?? null,
                    'transaction_id' => $extra['transaction_id'] ?? null,
                    'created_by' => $extra['created_by'] ?? null,
                ]);
            }, 3);
        } catch (QueryException $e) {
            // Two workers raced on the same reference; the unique index kept one.
            if ($existing = LedgerEntry::where('reference', $reference)->first()) {
                return $existing;
            }
            throw $e;
        }
    }

    /** Refund a purchase in full. Safe to call more than once. */
    public function refund(Transaction $tx, string $reason): Transaction
    {
        return DB::transaction(function () use ($tx, $reason) {
            $tx = Transaction::whereKey($tx->id)->lockForUpdate()->first();
            if ($tx->status === 'refunded') {
                return $tx;
            }
            if ($tx->status === 'success') {
                throw new InvalidArgumentException("{$tx->reference} was delivered; it cannot be auto-refunded.");
            }
            $this->credit($tx->user, $tx->total(), $tx->reference.'-R', 'refund', [
                'transaction_id' => $tx->id,
                'description' => 'Refund: '.$reason,
            ]);
            $tx->forceFill([
                'status' => 'refunded',
                'failure_reason' => $reason,
                'next_requery_at' => null,
                'completed_at' => now(),
            ])->save();

            return $tx;
        });
    }

    /**
     * Compare every wallet's cached balance against its ledger. Returns the wallets that disagree.
     *
     * @return array<int, array{wallet_id:int, user_id:int, cached:int, ledger:int, last_after:int|null}>
     */
    public function audit(): array
    {
        $sums = LedgerEntry::query()
            ->selectRaw("wallet_id, SUM(CASE WHEN type = 'credit' THEN amount ELSE -amount END) as total, MAX(id) as last_id")
            ->groupBy('wallet_id')
            ->get()->keyBy('wallet_id');
        $lastAfter = LedgerEntry::whereIn('id', $sums->pluck('last_id'))->pluck('balance_after', 'wallet_id');

        $bad = [];
        Wallet::query()->select(['id', 'user_id', 'balance'])->chunkById(500, function ($wallets) use ($sums, $lastAfter, &$bad) {
            foreach ($wallets as $w) {
                $ledger = (int) ($sums[$w->id]->total ?? 0);
                $after = $lastAfter[$w->id] ?? null;
                if ($ledger !== $w->balance || ($after !== null && (int) $after !== $w->balance)) {
                    $bad[] = ['wallet_id' => $w->id, 'user_id' => $w->user_id, 'cached' => $w->balance, 'ledger' => $ledger, 'last_after' => $after];
                }
            }
        });

        return $bad;
    }
}
