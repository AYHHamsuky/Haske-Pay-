<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\PurchaseService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RequeryTransaction implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1; // the scheduler picks it up again on the next tick

    public int $uniqueFor = 120;

    public function __construct(public int $transactionId)
    {
        $this->onQueue('requery');
    }

    public function uniqueId(): string
    {
        return (string) $this->transactionId;
    }

    public function handle(PurchaseService $purchases): void
    {
        $tx = Transaction::find($this->transactionId);
        if ($tx && $tx->status === 'pending') {
            $purchases->requery($tx);
        }
    }
}
