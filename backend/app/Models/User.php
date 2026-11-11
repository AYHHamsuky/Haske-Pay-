<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'phone', 'email', 'role', 'status', 'locale', 'device_id'];

    protected $hidden = ['pin_hash', 'two_factor_secret', 'remember_token'];

    // Mirrors the column defaults so a freshly created model has every attribute under strict mode.
    protected $attributes = [
        'role' => 'customer', 'status' => 'active', 'locale' => 'en', 'pin_attempts' => 0,
        'name' => null, 'email' => null, 'pin_hash' => null, 'pin_locked_until' => null, 'two_factor_secret' => null,
        'two_factor_confirmed_at' => null, 'device_id' => null, 'last_login_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'pin_locked_until' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
        ];
    }

    /** Login is by SMS code, so there is no password; the session guard still asks for one. */
    public function getAuthPassword(): string
    {
        return '';
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function virtualAccount(): HasOne
    {
        return $this->hasOne(VirtualAccount::class);
    }

    public function agentProfile(): HasOne
    {
        return $this->hasOne(AgentProfile::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function savedMeters(): HasMany
    {
        return $this->hasMany(SavedMeter::class);
    }

    public function isAgent(): bool
    {
        return $this->role === 'agent';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasPin(): bool
    {
        return $this->pin_hash !== null;
    }

    public function setPin(string $pin): void
    {
        $this->forceFill(['pin_hash' => Hash::make($pin), 'pin_attempts' => 0, 'pin_locked_until' => null])->save();
    }

    public function walletOrCreate(): Wallet
    {
        return $this->wallet ?? $this->wallet()->firstOrCreate([]);
    }

    public function dailyLimit(): int
    {
        return config('haske.limits.daily.'.$this->role);
    }

    /** Kobo spent today on purchases that were not refunded. */
    public function spentToday(): int
    {
        return (int) $this->transactions()
            ->where('created_at', '>=', now()->startOfDay())
            ->whereIn('status', ['pending', 'success'])
            ->sum(DB::raw('amount + fee'));
    }
}
