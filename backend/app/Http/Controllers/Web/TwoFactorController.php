<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use PragmaRX\Google2FA\Google2FA;

/** TOTP for admins (Google Authenticator, Authy, 1Password). Required every session. */
class TwoFactorController extends Controller
{
    public function show(Request $request, Google2FA $g2fa)
    {
        $user = $request->user();
        if ($user->two_factor_confirmed_at) {
            return Inertia::render('Auth/TwoFactor', ['setup' => null]);
        }

        $secret = $request->session()->get('2fa_setup_secret') ?? $g2fa->generateSecretKey(32);
        $request->session()->put('2fa_setup_secret', $secret);

        return Inertia::render('Auth/TwoFactor', ['setup' => [
            'secret' => $secret,
            'otpauth' => $g2fa->getQRCodeUrl('Haske Pay Admin', $user->phone, $secret),
        ]]);
    }

    public function verify(Request $request, Google2FA $g2fa)
    {
        $data = $request->validate(['code' => 'required|digits:6']);
        $user = $request->user();
        $key = '2fa:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Wait a minute.']);
        }

        $secret = $user->two_factor_confirmed_at ? $user->two_factor_secret : $request->session()->get('2fa_setup_secret');
        if (! $secret || ! $g2fa->verifyKey($secret, $data['code'], 1)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['code' => 'That code is not right. Check your authenticator app.']);
        }
        RateLimiter::clear($key);

        if (! $user->two_factor_confirmed_at) {
            $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
            $request->session()->forget('2fa_setup_secret');
            AuditLog::record('2fa.enabled', $user);
        }
        $request->session()->put('2fa_passed', true);

        return redirect()->intended('/admin');
    }
}
