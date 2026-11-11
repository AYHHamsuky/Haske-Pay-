<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Payments\Paystack;
use Tests\TestCase;

class PaystackWebhookTest extends TestCase
{
    private function event(User $user, string $ref = 'T123456789', int $kobo = 5_000_00): array
    {
        return ['event' => 'charge.success', 'data' => [
            'reference' => $ref, 'amount' => $kobo, 'status' => 'success', 'channel' => 'dedicated_nuban',
            'customer' => ['customer_code' => $user->virtualAccount->provider_ref],
        ]];
    }

    private function send(array $payload, ?string $sig = null)
    {
        $raw = json_encode($payload);
        $sig ??= hash_hmac('sha512', $raw, 'sk_test_haske');

        return $this->call('POST', '/api/webhooks/paystack', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $sig,
        ], $raw);
    }

    public function test_bad_signature_is_rejected(): void
    {
        $user = User::factory()->create();
        app(Paystack::class)->ensureVirtualAccount($user);

        $this->send($this->event($user->fresh()), 'nope')->assertStatus(401);
        $this->assertSame(0, (int) $user->walletOrCreate()->balance);
    }

    public function test_transfer_credits_wallet_once_even_when_replayed(): void
    {
        $user = User::factory()->create();
        app(Paystack::class)->ensureVirtualAccount($user);
        $event = $this->event($user->fresh());

        $this->send($event)->assertOk()->assertJsonPath('result', 'credited');
        $this->send($event)->assertOk()->assertJsonPath('result', 'duplicate');
        $this->send($event)->assertOk()->assertJsonPath('result', 'duplicate');

        $this->assertSame(5_000_00, $this->balance($user));
    }

    public function test_unknown_events_are_acknowledged_and_ignored(): void
    {
        $this->send(['event' => 'transfer.success', 'data' => ['reference' => 'x']])->assertOk()->assertJsonPath('result', 'ignored');
    }
}
