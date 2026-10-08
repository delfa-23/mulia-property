<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'description',
    ];

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class);
    }

    public function lots()
    {
        return $this->hasManyThrough(
            Lot::class,
            Block::class
        );
    }

    public function commonFacilities(): HasMany
    {
        return $this->hasMany(CommonFacility::class);
    }

    public function pemberkasanDocuments(): HasMany
    {
        return $this->hasMany(PemberkasanDocument::class);
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

    public function constructionStages(): HasMany
    {
        return $this->hasMany(ConstructionStage::class);
    }
}
