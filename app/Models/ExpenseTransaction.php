<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'lot_id',
        'facility_id',
        'division',
        'budget_id',
        'transaction_number',
        'transaction_date',
        'category',
        'amount',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(
            CommonFacility::class,
            'facility_id'
        );
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ExpenseTransactionHistory::class)
            ->latest();
    }
}
