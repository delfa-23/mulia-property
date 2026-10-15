<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingLeadSurvey extends Model
{
    public const STATUSES = ['scheduled', 'completed', 'cancelled', 'no_show'];

    protected $fillable = [
        'lead_id',
        'property_id',
        'assigned_user_id',
        'scheduled_at',
        'status',
        'result_notes',
        'next_follow_up_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MarketingLead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(MarketingLead::class, 'lead_id');
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
