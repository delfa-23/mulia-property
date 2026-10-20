<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BookingPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'booking_payment_order_id',
        'stage',
        'amount',
        'paid_at',
        'payment_method',
        'reference',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function financeCashFlow(): HasOne
    {
        return $this->hasOne(FinanceCashFlow::class);
    }

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(BookingPaymentOrder::class, 'booking_payment_order_id');
    }
}
