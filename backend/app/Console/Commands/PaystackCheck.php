<?php

namespace App\Console\Commands;

use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/** Checks the Paystack secret key and whether dedicated virtual accounts are enabled. Creates nothing. */
class PaystackCheck extends Command
{
    protected $signature = 'haske:paystack-check';

    protected $description = 'Check the Paystack key, balance and virtual-account banks';

    public function handle(): int
    {
        $key = (string) config('haske.paystack.secret_key');
        if ($key === '') {
            $this->error('PAYSTACK_SECRET_KEY is empty in .env');

            return self::FAILURE;
        }
        $mode = str_starts_with($key, 'sk_test_') ? 'TEST' : (str_starts_with($key, 'sk_live_') ? 'LIVE' : null);
        if (! $mode) {
            $this->error('PAYSTACK_SECRET_KEY should start with sk_test_ or sk_live_. (The pk_ public key is not used by the server.)');

            return self::FAILURE;
        }
        $this->line("Key: {$mode} secret key · payments driver: ".config('haske.drivers.payments'));
        $http = Http::baseUrl(config('haske.paystack.base_url'))->withToken($key)->acceptJson()->timeout(20);

        $bal = $http->get('/balance');
        if ($bal->status() === 401) {
            $this->error('Paystack rejected the key (401). Copy the secret key again from Settings → API Keys & Webhooks.');

            return self::FAILURE;
        }
        foreach ($bal->json('data') ?? [] as $b) {
            $this->info("Balance: {$b['currency']} ".Money::format((int) $b['balance']));
        }

        $banks = $http->get('/dedicated_account/available_providers');
        $slugs = collect($banks->json('data') ?? [])->pluck('provider_slug')->all();
        $want = config('haske.paystack.preferred_bank');
        if (! $banks->successful()) {
            $this->warn('Virtual accounts: '.($banks->json('message') ?? 'HTTP '.$banks->status()).'. Ask Paystack to enable Dedicated Virtual Accounts.');
        } else {
            $this->info('Virtual-account banks: '.(implode(', ', $slugs) ?: 'none'));
            // Test keys list the live banks but take "test-bank" (Paystack DVA docs).
            if ($mode === 'TEST' && $want !== 'test-bank') {
                $this->warn('With a test key, set PAYSTACK_DVA_BANK=test-bank.');
            } elseif ($mode === 'LIVE' && ! in_array($want, $slugs, true)) {
                $this->warn("PAYSTACK_DVA_BANK={$want} is not in that list. Use ".($slugs[0] ?? 'a listed bank').'.');
            }
        }
        $this->line('Webhook URL to set in the Paystack dashboard: '.route('webhooks.paystack'));

        return self::SUCCESS;
    }
}
