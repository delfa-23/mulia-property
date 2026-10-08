<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConstructionStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'name',
        'order',
        'description',
        'min_progress',
        'max_progress',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_progress' => 'decimal:2',
            'max_progress' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function progresses(): HasMany
    {
        return $this->hasMany(ConstructionProgress::class, 'stage_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
