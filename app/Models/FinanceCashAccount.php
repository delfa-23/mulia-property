<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceCashAccount extends Model
{
    protected $fillable = [
        'account_key',
        'name',
        'balance',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function balanceHistories(): HasMany
    {
        return $this->hasMany(FinanceCashAccountBalanceHistory::class);
    }
}
