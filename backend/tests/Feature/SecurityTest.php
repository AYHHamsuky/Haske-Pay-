<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OtpService;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    public function test_three_wrong_pins_lock_purchases(): void
    {
        $user = $this->customer();
        foreach (range(1, 3) as $_) {
            $this->buyElectricity($user, 2_000, ['pin' => '0000'])->assertStatus(422);
        }

        $this->buyElectricity($user->fresh(), 2_000)->assertStatus(422)->assertJsonValidationErrors('pin');
        $this->assertTrue($user->fresh()->pin_locked_until->isFuture());
    }

    public function test_token_is_rejected_from_another_device(): void
    {
        $user = $this->customer();
        $this->actingAsDevice($user, 'phone-a')->withHeader('X-Device-Id', 'phone-b')
            ->getJson('/api/v1/me')->assertStatus(401)->assertJsonPath('code', 'device_mismatch');
    }

    public function test_otp_login_creates_customer_and_binds_device(): void
    {
        $code = $this->postJson('/api/v1/auth/otp', ['phone' => '+234 803 111 2222'])->assertOk()->json('dev_code');

        $res = $this->postJson('/api/v1/auth/verify', ['phone' => '08031112222', 'code' => $code, 'device_id' => 'pixel-7'])->assertOk();

        $res->assertJsonPath('needs_pin', true);
        $user = User::where('phone', '08031112222')->first();
        $this->assertSame('pixel-7', $user->device_id);
        $this->assertNotNull($user->wallet);

        $this->withToken($res->json('token'))->withHeader('X-Device-Id', 'pixel-7')->getJson('/api/v1/me')->assertOk()->assertJsonPath('phone', '08031112222');
    }

    public function test_wrong_otp_is_rejected_and_codes_are_single_use(): void
    {
        $otp = app(OtpService::class);
        $code = $otp->send('08031112222');

        $this->assertFalse($otp->check('08031112222', $code === '111111' ? '222222' : '111111'));
        $this->assertTrue($otp->check('08031112222', $code));
        $this->assertFalse($otp->check('08031112222', $code));
    }

    public function test_easy_pins_are_refused(): void
    {
        $user = User::factory()->create();
        $this->actingAsDevice($user)->postJson('/api/v1/me/pin', ['pin' => '1234'])->assertStatus(422);
        $this->actingAsDevice($user)->postJson('/api/v1/me/pin', ['pin' => '7391'])->assertOk();
    }

    public function test_admin_pages_require_admin_role(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }
}
