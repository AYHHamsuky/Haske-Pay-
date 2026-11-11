<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentProfile extends Model
{
    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** % of our provider commission that the agent keeps. */
    public function share(): int
    {
        return config('haske.agent_tiers.'.$this->commission_tier, 50);
    }
}
