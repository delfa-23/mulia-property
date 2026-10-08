<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommonFacility extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'name',
        'description',
        'budget',
        'progress',
        'realization',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'realization' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(CommonFacilityHistory::class)
            ->latest();
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(
            ExpenseTransaction::class,
            'facility_id'
        );
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(
            Budget::class,
            'facility_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function getProgressAttribute(): float
    {
        if ((float) $this->budget <= 0) {
            return 0;
        }

        return min(
            100,
            ((float) $this->realization / (float) $this->budget) * 100
        );
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'planned' => 'Belum Dimulai',
            'in_progress' => 'Dalam Proses',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            default => ucfirst((string) $this->status),
        };
    }
}
