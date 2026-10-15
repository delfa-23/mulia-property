<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingLeadFollowUp extends Model
{
    public const STATUSES = ['pending', 'in_progress', 'completed', 'cancelled'];

    public const PRIORITIES = ['low', 'medium', 'high'];

    protected $fillable = [
        'lead_id',
        'assigned_user_id',
        'created_by',
        'title',
        'description',
        'due_at',
        'priority',
        'status',
        'result_notes',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MarketingLead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(MarketingLead::class, 'lead_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<MarketingLeadFollowUpHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(MarketingLeadFollowUpHistory::class, 'follow_up_id')->latest('changed_at');
    }
}
