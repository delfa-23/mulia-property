<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceCashAccountBalanceHistory extends Model
{
    protected $fillable = [
        'cash_account_id',
        'account_name',
        'previous_balance',
        'new_balance',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'previous_balance' => 'decimal:2',
            'new_balance' => 'decimal:2',
        ];
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceCashAccount::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
