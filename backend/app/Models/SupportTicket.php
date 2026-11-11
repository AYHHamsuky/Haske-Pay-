<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicket extends Model
{
    public const TYPES = [
        'token_not_received' => 'Token not received',
        'token_rejected' => 'Meter rejected the token',
        'wrong_number' => 'Wrong meter or phone number',
        'funding' => 'Wallet funding issue',
        'pending' => 'Payment pending too long',
        'other' => 'Other',
    ];

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'open', 'reply' => null, 'resolved_at' => null, 'resolved_by' => null, 'transaction_id' => null, 'reference' => null];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
