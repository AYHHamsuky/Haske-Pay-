<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reconciliation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['for_date' => 'date', 'details' => 'array'];
    }
}
