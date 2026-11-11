<?php

namespace App\Jobs;

use App\Exceptions\PurchaseRejected;
use App\Models\BulkBatch;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Agent bulk vending. Rows run one at a time; each row has its own idempotency
 * key, so a retried job skips rows that already went through.
 */
class ProcessBulkBatch implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    /** @param list<array{target:string, amount:int, phone:?string}> $rows */
    public function __construct(public int $batchId, public array $rows, public string $meterType = 'prepaid') {}

    public function handle(PurchaseService $purchases): void
    {
        $batch = BulkBatch::findOrFail($this->batchId);
        $user = User::findOrFail($batch->user_id);
        $batch->update(['status' => 'running']);
        $errors = Cache::get("bulk.{$batch->id}.errors", []);

        foreach ($this->rows as $i => $row) {
            try {
                $purchases->buy($user->fresh(), [
                    'service_code' => $batch->service_code,
                    'target' => $row['target'],
                    'amount' => $row['amount'],
                    'meter_type' => $this->meterType,
                    'customer_phone' => $row['phone'] ?? null,
                    'idempotency_key' => "bulk-{$batch->id}-{$i}",
                    'channel' => 'bulk',
                    'batch_id' => $batch->id,
                ]);
            } catch (PurchaseRejected $e) {
                $errors[$i] = ['target' => $row['target'], 'error' => $e->getMessage()];
                Cache::put("bulk.{$batch->id}.errors", $errors, now()->addDays(7));
                if ($e->errorCode === 'insufficient_funds' || $e->errorCode === 'daily_limit') {
                    break;
                }
            }
            $batch->increment('rows_done');
        }

        $batch->update(['status' => 'done']);
    }
}
