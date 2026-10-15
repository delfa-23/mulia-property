<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingLeadFollowUpHistory extends Model
{
    protected $fillable = [
        'follow_up_id',
        'from_status',
        'to_status',
        'changed_by',
        'notes',
        'changed_at',
    ];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    /** @return BelongsTo<MarketingLeadFollowUp, $this> */
    public function followUp(): BelongsTo
    {
        return $this->belongsTo(MarketingLeadFollowUp::class, 'follow_up_id');
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
