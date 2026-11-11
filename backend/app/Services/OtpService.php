<?php

namespace App\Services;

use App\Jobs\SendSms;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class OtpService
{
    /** Sends a 6-digit code. Returns the code in fake mode so local testing needs no SMS. */
    public function send(string $phone, string $purpose = 'login', string $locale = 'en'): ?string
    {
        $key = "otp-send:{$phone}";
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $wait = RateLimiter::availableIn($key);
            throw ValidationException::withMessages(['phone' => "Too many codes requested. Try again in {$wait} seconds."]);
        }
        RateLimiter::hit($key, 600);

        $code = (string) random_int(100000, 999999);
        OtpCode::where('phone', $phone)->whereNull('used_at')->update(['used_at' => now()]);
        OtpCode::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(config('haske.limits.otp_ttl_minutes')),
        ]);

        $msg = $locale === 'ha'
            ? "Lambar Haske ɗinka: {$code}. Kada ka ba kowa. Za ta ƙare cikin minti 5."
            : "Your Haske code is {$code}. Do not share it. It expires in 5 minutes.";
        SendSms::dispatchSync($phone, $msg);

        return config('haske.drivers.sms') === 'fake' ? $code : null;
    }

    public function check(string $phone, string $code, string $purpose = 'login'): bool
    {
        $otp = OtpCode::where('phone', $phone)->where('purpose', $purpose)
            ->whereNull('used_at')->where('expires_at', '>', now())->latest('id')->first();
        if (! $otp) {
            return false;
        }
        if ($otp->attempts >= config('haske.limits.otp_max_attempts')) {
            $otp->update(['used_at' => now()]);

            return false;
        }
        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            return false;
        }
        $otp->update(['used_at' => now()]);

        return true;
    }

    /** Verify a login code and return the user, creating a customer on first login. */
    public function login(string $phone, string $code): User
    {
        if (! $this->check($phone, $code)) {
            throw ValidationException::withMessages(['code' => 'That code is wrong or has expired.']);
        }
        $user = User::firstOrCreate(['phone' => $phone], ['role' => 'customer']);
        if ($user->status !== 'active') {
            throw ValidationException::withMessages(['phone' => 'This account is suspended. Contact support.']);
        }
        $user->walletOrCreate();
        $user->forceFill(['last_login_at' => now()])->save();

        return $user;
    }
}
