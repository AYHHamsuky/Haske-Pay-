<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['request_payload', 'response_payload'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'fee' => 'integer',
            'commission' => 'integer',
            'agent_commission' => 'integer',
            'requery_count' => 'integer',
            'next_requery_at' => 'datetime',
            'escalated_at' => 'datetime',
            'completed_at' => 'datetime',
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_code', 'code');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function total(): int
    {
        return $this->amount + $this->fee;
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['success', 'refunded', 'failed'], true);
    }

    /** Our margin: provider commission plus fee, minus what the agent keeps. */
    public function margin(): int
    {
        return $this->status === 'success' ? $this->commission + $this->fee - $this->agent_commission : 0;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'category' => $this->category,
            'service_code' => $this->service_code,
            'service_name' => $this->service?->name,
            'short_name' => $this->service?->short_name,
            'target' => $this->target,
            'meter_type' => $this->meter_type,
            'plan' => $this->variation_name,
            'customer_name' => $this->customer_name,
            'amount' => $this->amount,
            'fee' => $this->fee,
            'total' => $this->total(),
            'agent_commission' => $this->agent_commission,
            'status' => $this->status,
            'provider' => $this->provider,
            'token' => $this->token,
            'units' => $this->units,
            'failure_reason' => $this->failure_reason,
            'requery_count' => $this->requery_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
