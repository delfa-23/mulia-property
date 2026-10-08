<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConstructionProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'lot_id',
        'stage_id',
        'progress',
        'updated_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'progress' => 'decimal:2',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(
            ConstructionStage::class,
            'stage_id'
        );
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    public function histories(): HasMany
    {
        return $this->hasMany(
            ConstructionProgressHistory::class,
            'construction_progress_id'
        )->latest();
    }
}
