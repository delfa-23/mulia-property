<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingPaymentSetting extends Model
{
    public const CATEGORIES = ['cash', 'kpr'];

    protected $fillable = [
        'payment_category',
        'booking_fee_amount',
        'dp_amount',
        'booking_fee_deadline_hours',
        'dp_deadline_days',
    ];

    protected function casts(): array
    {
        return [
            'booking_fee_amount' => 'decimal:2',
            'dp_amount' => 'decimal:2',
            'booking_fee_deadline_hours' => 'integer',
            'dp_deadline_days' => 'integer',
        ];
    }
}
