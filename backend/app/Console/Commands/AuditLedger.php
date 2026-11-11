<?php

namespace App\Console\Commands;

use App\Jobs\SendSms;
use App\Models\SystemCheck;
use App\Models\Wallet;
use App\Services\Ledger;
use Illuminate\Console\Command;

class AuditLedger extends Command
{
    protected $signature = 'haske:audit-ledger';

    protected $description = 'Check that every wallet balance equals the sum of its ledger entries';

    public function handle(Ledger $ledger): int
    {
        $bad = $ledger->audit();
        $total = (int) Wallet::sum('balance');
        SystemCheck::create(['name' => 'ledger', 'ok' => $bad === [], 'data' => ['wallets_total' => $total, 'mismatches' => $bad]]);

        if ($bad) {
            $this->error(count($bad).' wallet(s) do not match their ledger.');
            $this->table(['wallet', 'user', 'cached', 'ledger', 'last balance_after'], $bad);
            foreach (config('haske.alerts.phones') as $p) {
                SendSms::dispatch($p, 'Haske ALERT: '.count($bad).' wallet(s) do not match the ledger. Check admin now.');
            }

            return self::FAILURE;
        }

        $this->info('Ledger OK. All wallets match.');

        return self::SUCCESS;
    }
}
