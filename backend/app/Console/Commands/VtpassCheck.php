<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Services\Providers\VtpassProvider;
use App\Support\Money;
use Illuminate\Console\Command;

/** Quick connectivity check against VTpass using the keys in .env. Makes no purchases. */
class VtpassCheck extends Command
{
    protected $signature = 'haske:vtpass-check {--meter=1111111111111 : Meter to verify} {--disco=ikeja-electric}';

    protected $description = 'Check VTpass keys: wallet balance, a meter lookup and data plans';

    public function handle(): int
    {
        $cfg = config('haske.vtpass');
        $this->line('Base URL: '.$cfg['base_url'].(str_contains($cfg['base_url'], 'sandbox') ? '  (sandbox)' : '  (LIVE)'));
        foreach (['api_key' => 'VTPASS_API_KEY', 'public_key' => 'VTPASS_PUBLIC_KEY', 'secret_key' => 'VTPASS_SECRET_KEY'] as $k => $env) {
            if (blank($cfg[$k])) {
                $this->error("{$env} is empty in .env");

                return self::FAILURE;
            }
        }
        $vt = new VtpassProvider($cfg);
        $ok = true;

        // GET with api-key + public-key
        $bal = $vt->balance();
        $bal === null ? $this->error('Balance: failed (check VTPASS_PUBLIC_KEY)') : $this->info('Balance: '.Money::format($bal));
        $ok = $ok && $bal !== null;

        // POST with api-key + secret-key
        $service = Service::byCode($this->option('disco')) ?? new Service(['code' => $this->option('disco'), 'name' => $this->option('disco')]);
        $v = $vt->verifyMeter($service, $this->option('meter'), 'prepaid');
        $v->ok ? $this->info("Meter {$this->option('meter')}: {$v->name}".($v->address ? " · {$v->address}" : '')) : $this->error("Meter lookup failed: {$v->error} (check VTPASS_SECRET_KEY)");
        $ok = $ok && $v->ok;

        $mtn = Service::byCode('mtn-data') ?? new Service(['code' => 'mtn-data']);
        try {
            $plans = $vt->dataPlans($mtn);
            $this->info('MTN data plans: '.count($plans).' (first: '.($plans[0]['name'] ?? '—').')');
        } catch (\Throwable $e) {
            $this->error('Data plans failed: '.$e->getMessage());
            $ok = false;
        }

        $ok ? $this->info('VTpass connection OK.') : $this->warn('Fix the errors above, then run this again.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
