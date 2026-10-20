<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingPaymentOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookingPaymentOrder>
 */
class BookingPaymentOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'stage' => 'dp',
            'amount' => 1000000,
            'order_id' => 'BK-'.Str::ulid(),
            'status' => 'pending',
        ];
    }
}
