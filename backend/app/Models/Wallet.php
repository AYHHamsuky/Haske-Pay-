<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = ['user_id', 'currency'];

    protected $attributes = ['balance' => 0, 'currency' => 'NGN'];

    protected function casts(): array
    {
        return ['balance' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /** Balance rebuilt from the ledger, ignoring the cached column. */
    public function ledgerBalance(): int
    {
        return (int) $this->entries()->selectRaw("COALESCE(SUM(CASE WHEN type = 'credit' THEN amount ELSE -amount END), 0) as b")->value('b');
    }
}
