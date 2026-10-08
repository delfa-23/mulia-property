<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Lot extends Model
{
    use HasFactory;

    protected $fillable = [
        'block_id',
        'lot_number',
        'house_price',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'house_price' => 'decimal:2',
        ];
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function constructionProgresses(): HasMany
    {
        return $this->hasMany(ConstructionProgress::class);
    }

    public function pemberkasanDocuments(): HasMany
    {
        return $this->hasMany(PemberkasanDocument::class);
    }

    public function constructionProgressHistories(): HasManyThrough
    {
        return $this->hasManyThrough(
            ConstructionProgressHistory::class,
            ConstructionProgress::class,
            'lot_id',
            'construction_progress_id'
        );
    }

    public function incomeTransactions(): HasMany
    {
        return $this->hasMany(IncomeTransaction::class);
    }

    public function expenseTransactions(): HasMany
    {
        return $this->hasMany(ExpenseTransaction::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
