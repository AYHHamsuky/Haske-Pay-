<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Transaction PIN: three wrong tries locks purchases for a while. */
class PinGuard
{
    public function verify(User $user, ?string $pin): void
    {
        if (! $user->hasPin()) {
            throw ValidationException::withMessages(['pin' => 'Set a transaction PIN first.']);
        }
        if ($user->pin_locked_until && $user->pin_locked_until->isFuture()) {
            $mins = (int) ceil(now()->diffInSeconds($user->pin_locked_until) / 60);
            throw ValidationException::withMessages(['pin' => "Too many wrong PINs. Try again in {$mins} min."]);
        }
        if ($pin !== null && Hash::check($pin, $user->pin_hash)) {
            if ($user->pin_attempts) {
                $user->forceFill(['pin_attempts' => 0])->save();
            }

            return;
        }

        $max = config('haske.limits.pin_max_attempts');
        $user->pin_attempts++;
        if ($user->pin_attempts >= $max) {
            $user->pin_attempts = 0;
            $user->pin_locked_until = now()->addMinutes(config('haske.limits.pin_lock_minutes'));
            AuditLog::record('pin.locked', $user);
        }
        $user->save();
        $left = $max - $user->pin_attempts;
        throw ValidationException::withMessages(['pin' => $user->pin_locked_until?->isFuture()
            ? 'Too many wrong PINs. Purchases are locked for '.config('haske.limits.pin_lock_minutes').' minutes.'
            : "Wrong PIN. {$left} tries left."]);
    }
}
