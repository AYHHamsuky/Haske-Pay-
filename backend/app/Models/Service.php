<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'float',
            'fee' => 'integer',
            'min_amount' => 'integer',
            'max_amount' => 'integer',
            'active' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true);
    }

    public static function byCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }

    /** Provider commission on a face value, kobo. */
    public function commissionOn(int $amount): int
    {
        return (int) floor($amount * $this->commission_rate / 100);
    }
}
