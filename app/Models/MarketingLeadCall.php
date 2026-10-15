<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingLeadCall extends Model
{
    public const OUTCOMES = ['answered', 'unanswered', 'inactive_number', 'call_back'];

    protected $fillable = [
        'lead_id',
        'performed_by',
        'called_at',
        'outcome',
        'duration_minutes',
        'notes',
        'next_follow_up_at',
    ];

    protected function casts(): array
    {
        return [
            'called_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MarketingLead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(MarketingLead::class, 'lead_id');
    }

    /** @return BelongsTo<User, $this> */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
