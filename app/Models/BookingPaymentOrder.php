<?php

namespace App\Models;

use Database\Factories\BookingPaymentOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingPaymentOrder extends Model
{
    /** @use HasFactory<BookingPaymentOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'booking_payment_schedule_id',
        'stage',
        'amount',
        'order_id',
        'status',
        'snap_token',
        'redirect_url',
        'transaction_id',
        'payment_type',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(BookingPaymentSchedule::class, 'booking_payment_schedule_id');
    }
}
