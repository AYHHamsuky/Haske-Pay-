<?php

namespace Tests;

use App\Models\User;
use App\Services\Ledger;
use App\Services\Providers\FakeProvider;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    private array $tokens = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ServiceSeeder::class);
        FakeProvider::setNextOutcome('success');
    }

    /** A customer with ₦ in their wallet, acting through a bound device token. */
    protected function customer(int $naira = 20_000, string $role = 'customer'): User
    {
        $factory = User::factory()->withPin('2580');
        $user = ($role === 'agent' ? $factory->agent() : $factory)->create();
        if ($naira) {
            app(Ledger::class)->credit($user, $naira * 100, 'TEST-'.Str::random(8), 'funding');
        }

        return $user->fresh();
    }

    protected function actingAsDevice(User $user, string $device = 'device-1'): static
    {
        $this->tokens[$user->id.$device] ??= $user->createToken($device, ['app'])->plainTextToken;
        app('auth')->forgetGuards();

        return $this->withToken($this->tokens[$user->id.$device])->withHeader('X-Device-Id', $device);
    }

    protected function buyElectricity(User $user, int $naira = 5_000, array $extra = [])
    {
        return $this->actingAsDevice($user)->postJson('/api/v1/purchases', $extra + [
            'service_code' => 'kaduna-electric',
            'target' => '04223456781',
            'meter_type' => 'prepaid',
            'amount' => $naira * 100,
            'pin' => '2580',
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    protected function balance(User $user): int
    {
        return $user->wallet()->value('balance');
    }
}
