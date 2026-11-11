<?php

namespace App\Providers;

use App\Services\Providers\ProviderManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderManager::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(90)->by($r->user()?->id ?: $r->ip()));
        // Per-IP limits stay loose: Nigerian carriers put many users behind one NAT address.
        // OtpService separately allows 3 codes per phone per 10 minutes.
        RateLimiter::for('otp', fn (Request $r) => [Limit::perMinute(20)->by($r->ip()), Limit::perHour(200)->by($r->ip())]);
        RateLimiter::for('verify', fn (Request $r) => Limit::perMinute(15)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('purchases', fn (Request $r) => Limit::perMinute(12)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('checkout', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
    }
}
