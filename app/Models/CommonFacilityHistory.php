<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommonFacilityHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'common_facility_id',
        'previous_realization',
        'realization',
        'updated_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'previous_realization' => 'decimal:2',
            'realization' => 'decimal:2',
        ];
    }

    public function commonFacility(): BelongsTo
    {
        return $this->belongsTo(CommonFacility::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
