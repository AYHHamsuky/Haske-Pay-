<?php

namespace App\Services\Providers;

use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;

/**
 * In-process stand-in for VTpass and Baxi, used when HASKE_PROVIDER_MODE=fake.
 * The admin "Sandbox" panel sets the next outcome, the same switch as the HTML demo.
 */
class FakeProvider implements BillProvider
{
    public const OUTCOMES = ['auto', 'success', 'pending', 'failed', 'down'];

    private const NAMES = ['Aisha Bello', 'Musa Ibrahim', 'Fatima Sani', 'Usman Garba', 'Hauwa Yusuf', 'Abdullahi Aliyu', 'Zainab Lawal', 'Yakubu Danjuma'];

    private const AREAS = ['Barnawa', 'Ungwan Rimi', 'Malali', 'Tudun Wada', 'Sabon Tasha', 'Kawo', 'Narayi', 'Kabala Costain'];

    private const PLANS = [
        'mtn-data' => [['1GB', 1, 500], ['2GB', 7, 1000], ['3.5GB', 30, 2500], ['7GB', 30, 3500], ['20GB', 30, 7500]],
        'airtel-data' => [['1GB', 1, 500], ['2GB', 7, 1000], ['4GB', 30, 2500], ['10GB', 30, 4000], ['18GB', 30, 6000]],
        'glo-data' => [['1GB', 1, 350], ['2.6GB', 30, 1000], ['5.8GB', 30, 2000], ['10GB', 30, 3000], ['18GB', 30, 5000]],
        'etisalat-data' => [['1GB', 1, 400], ['2.5GB', 30, 1000], ['5GB', 30, 2500], ['11GB', 30, 4000]],
    ];

    public const TARIFF = ['A' => 209.5, 'B' => 63.2, 'C' => 50.1];

    public function __construct(private readonly string $name) {}

    public function name(): string
    {
        return $this->name;
    }

    public static function nextOutcome(): string
    {
        return Cache::get('haske.sandbox.outcome', 'auto');
    }

    public static function setNextOutcome(string $outcome): void
    {
        Cache::forever('haske.sandbox.outcome', $outcome);
    }

    public function verifyMeter(Service $service, string $meter, string $meterType): VerifyResult
    {
        if (preg_match('/^0+$/', $meter)) {
            return VerifyResult::notFound();
        }
        $h = crc32($meter.$service->code);
        $city = explode(' ', $service->name)[0];

        return VerifyResult::found(
            self::NAMES[$h % count(self::NAMES)],
            self::AREAS[($h >> 3) % count(self::AREAS)].', '.$city,
            ['A', 'A', 'B', 'C'][($h >> 5) % 4],
        );
    }

    public function purchase(Transaction $tx): ProviderResult
    {
        $mode = self::nextOutcome();
        if ($mode === 'auto') {
            $r = mt_rand(1, 100);
            $mode = $r <= 85 ? 'success' : ($r <= 95 ? 'pending' : 'failed');
        }

        if ($mode === 'down') {
            // Only the primary is down; the backup route delivers.
            if ($this->name === 'vtpass') {
                return ProviderResult::unreachable('vtpass unreachable: connection timed out (sandbox)');
            }
            $mode = 'success';
        }

        return match ($mode) {
            'success' => $this->remember($tx, $this->deliver($tx)),
            'failed' => $this->remember($tx, ProviderResult::failed('The provider declined the transaction.')),
            'pending' => tap(ProviderResult::pending('TRANSACTION PROCESSING'), fn () => Cache::put($this->key($tx, 'checks'), 2, now()->addDay())),
        };
    }

    public function requery(Transaction $tx): ProviderResult
    {
        if ($final = Cache::get($this->key($tx, 'final'))) {
            return new ProviderResult(...$final);
        }
        $left = Cache::get($this->key($tx, 'checks'));
        if ($left === null) {
            return ProviderResult::notFound();
        }
        if ($left > 1) {
            Cache::put($this->key($tx, 'checks'), $left - 1, now()->addDay());

            return ProviderResult::pending('TRANSACTION PROCESSING');
        }

        return $this->remember($tx, $this->deliver($tx));
    }

    private function deliver(Transaction $tx): ProviderResult
    {
        $ref = (string) random_int(10_000_000_000, 99_999_999_999);
        if ($tx->category === 'electricity' && $tx->meter_type === 'prepaid') {
            $token = '';
            for ($i = 0; $i < 20; $i++) {
                $token .= random_int(0, 9);
            }
            $band = $this->verifyMeter($tx->service, $tx->target, 'prepaid')->band ?? 'A';
            $units = number_format($tx->amount / 100 / 1.075 / (self::TARIFF[$band] ?? 209.5), 1, '.', '');

            return ProviderResult::success($ref, $token, $units.' kWh');
        }

        return ProviderResult::success($ref);
    }

    private function remember(Transaction $tx, ProviderResult $r): ProviderResult
    {
        Cache::put($this->key($tx, 'final'), [$r->status, $r->message, $r->providerRef, $r->token, $r->units], now()->addDay());

        return $r;
    }

    private function key(Transaction $tx, string $what): string
    {
        return "fake.{$this->name}.{$tx->request_id}.{$what}";
    }

    public function dataPlans(Service $service): array
    {
        return collect(self::PLANS[$service->code] ?? [])->map(fn ($p, $i) => [
            'code' => "{$service->code}-{$i}",
            'name' => "{$p[0]} · {$p[1]} ".($p[1] === 1 ? 'day' : 'days'),
            'amount' => $p[2] * 100,
        ])->all();
    }

    public function balance(): ?int
    {
        return (int) Cache::get("haske.sandbox.float.{$this->name}", 5_000_000_00);
    }
}
