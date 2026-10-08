<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseTransactionHistory extends Model
{
    protected $fillable = [
        'expense_transaction_id',
        'previous_amount',
        'amount',
        'previous_category',
        'category',
        'previous_transaction_date',
        'transaction_date',
        'previous_description',
        'description',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'previous_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'previous_transaction_date' => 'date',
            'transaction_date' => 'date',
        ];
    }

    public function expenseTransaction(): BelongsTo
    {
        return $this->belongsTo(ExpenseTransaction::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
