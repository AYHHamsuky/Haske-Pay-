<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemCheck extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['ok' => 'boolean', 'data' => 'array'];
    }

    public static function latestFor(string $name): ?self
    {
        return static::where('name', $name)->latest('id')->first();
    }
}
