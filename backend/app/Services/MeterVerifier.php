<?php

namespace App\Services;

use App\Models\Service;
use App\Services\Providers\ProviderManager;
use App\Services\Providers\VerifyResult;
use Illuminate\Support\Facades\Cache;

/**
 * Meter lookups are cached for 30 minutes so the purchase step can trust the
 * customer name without asking the provider again.
 */
class MeterVerifier
{
    public function __construct(private readonly ProviderManager $providers) {}

    public function verify(Service $service, string $meter, string $meterType): VerifyResult
    {
        $key = "verify:{$service->code}:{$meterType}:{$meter}";
        if ($hit = Cache::get($key)) {
            return VerifyResult::found($hit['name'], $hit['address'], $hit['band'], $hit['min_amount']);
        }

        $result = $this->providers->get($service->provider)->verifyMeter($service, $meter, $meterType);
        if ($result->ok) {
            Cache::put($key, $result->toArray(), now()->addMinutes(30));
        }

        return $result;
    }
}
