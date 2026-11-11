<?php

namespace App\Console\Commands;

use App\Jobs\RequeryTransaction;
use App\Models\Transaction;
use Illuminate\Console\Command;

class RequeryPending extends Command
{
    protected $signature = 'haske:requery {--sync : Run in this process instead of the queue}';

    protected $description = 'Queue a status check for every pending purchase that is due';

    public function handle(): int
    {
        $n = 0;
        Transaction::where('status', 'pending')
            ->whereNotNull('next_requery_at')
            ->where('next_requery_at', '<=', now())
            ->orderBy('next_requery_at')
            ->limit(500)
            ->pluck('id')
            ->each(function ($id) use (&$n) {
                $this->option('sync') ? RequeryTransaction::dispatchSync($id) : RequeryTransaction::dispatch($id);
                $n++;
            });

        $this->info("Queued {$n} requery job(s).");

        return self::SUCCESS;
    }
}
