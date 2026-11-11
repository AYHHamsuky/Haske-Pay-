<?php

namespace App\Console\Commands;

use App\Jobs\SendSms;
use App\Models\SystemCheck;
use App\Services\Providers\ProviderManager;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CheckProviderBalance extends Command
{
    protected $signature = 'haske:provider-balance';

    protected $description = 'Alert when our float with VTpass or Baxi runs low';

    public function handle(ProviderManager $providers): int
    {
        $threshold = config('haske.alerts.provider_low_balance');
        $out = [];
        foreach ($providers->names() as $name) {
            $bal = $providers->get($name)->balance();
            $out[$name] = $bal;
            $low = $bal !== null && $bal < $threshold;
            if ($low && Cache::add("alert.low-balance.{$name}", true, now()->addHours(2))) {
                foreach (config('haske.alerts.phones') as $p) {
                    SendSms::dispatch($p, "Haske ALERT: {$name} balance is ".Money::format($bal).'. Top up now.');
                }
            }
            $this->line($name.': '.($bal === null ? 'unknown' : Money::format($bal)).($low ? '  LOW' : ''));
        }
        SystemCheck::create([
            'name' => 'provider_balance',
            'ok' => collect($out)->every(fn ($b) => $b === null || $b >= $threshold),
            'data' => $out + ['threshold' => $threshold],
        ]);

        return self::SUCCESS;
    }
}
