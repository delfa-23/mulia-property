<?php

use App\Models\Block;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingPaymentSchedule;
use App\Models\Customer;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;
use App\Services\BookingPaymentSummaryService;

test('admin configures booking payment schedules per property', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Jadwal']);
    $otherProperty = Property::create(['name' => 'Perumahan Lain']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 100000000,
        'status' => 'booked',
    ]);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => Customer::create(['name' => 'Customer Jadwal'])->id,
        'booking_date' => '2026-10-01',
        'house_price_snapshot' => 100000000,
        'status' => 'booking',
    ]);

    $this->actingAs($admin)
        ->from(route('admin.properties.payment-schedules.index', $property))
        ->post(route('admin.properties.payment-schedules.store', $property), [
            'booking_id' => $booking->id,
            'title' => 'Pembayaran 1',
            'amount' => '1.000.000',
        ])
        ->assertRedirect(route('admin.properties.payment-schedules.index', $property));

    $schedule = BookingPaymentSchedule::query()->sole();

    expect($schedule->property_id)->toBe($property->id)
        ->and($schedule->booking_id)->toBe($booking->id)
        ->and((float) $schedule->amount)->toBe(1000000.0)
        ->and($schedule->is_active)->toBeTrue();

    $this->get(route('admin.properties.payment-schedules.index', $property))
        ->assertSee('Tambah jadwal untuk booking tertentu')
        ->assertDontSee('Semua booking di perumahan ini');
    $this->get(route('admin.properties.payment-schedules.index', $otherProperty))
        ->assertDontSee('>Pembayaran 1</p>', false);

    $this->actingAs($admin)
        ->put(route('admin.bookings.payment-schedules.update', [$booking, $schedule]), [
            'title' => 'Pembayaran Revisi',
            'amount' => '2.000.000',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('booking_payment_schedules', [
        'id' => $schedule->id,
        'booking_id' => $booking->id,
        'title' => 'Pembayaran Revisi',
        'amount' => '2000000.00',
    ]);
});

test('admin cannot add a payment schedule without choosing a booking', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Tanpa Booking']);

    $this->actingAs($admin)
        ->post(route('admin.properties.payment-schedules.store', $property), [
            'title' => 'Pembayaran Umum',
            'amount' => '1.000.000',
        ])
        ->assertSessionHasErrors('booking_id');

    $this->assertDatabaseCount('booking_payment_schedules', 0);
});

test('admin cannot configure booking payment schedules above the booking price', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Batas Nominal']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 1000000,
        'status' => 'booked',
    ]);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => Customer::create(['name' => 'Customer Batas'])->id,
        'booking_date' => '2026-10-01',
        'house_price_snapshot' => 1000000,
        'status' => 'booking',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.properties.payment-schedules.store', $property), [
            'booking_id' => $booking->id,
            'title' => 'Pembayaran Melebihi Harga',
            'amount' => 1000001,
        ])
        ->assertSessionHasErrors('amount');

    $this->assertDatabaseCount('booking_payment_schedules', 0);
});

test('adding a schedule from one booking affects only that booking', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Jadwal Per Booking']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lots = collect(['01', '02'])->map(fn (string $number) => Lot::create([
        'block_id' => $block->id,
        'lot_number' => $number,
        'house_price' => 100000000,
        'status' => 'booked',
    ]));
    $bookings = $lots->map(fn (Lot $lot) => Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => Customer::create(['name' => 'Customer '.$lot->lot_number])->id,
        'booking_date' => '2026-10-01',
        'house_price_snapshot' => 100000000,
        'status' => 'booking',
    ]));
    $legacySchedule = BookingPaymentSchedule::create([
        'property_id' => $property->id,
        'title' => 'Jadwal Lama Tanpa Booking',
        'amount' => 20000000,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.properties.payment-schedules.store', $property), [
            'booking_id' => $bookings[0]->id,
            'title' => 'Jadwal Khusus',
            'amount' => '10.000.000',
        ])
        ->assertRedirect();

    $customSchedule = BookingPaymentSchedule::query()->where('title', 'Jadwal Khusus')->sole();
    $firstSummary = app(BookingPaymentSummaryService::class)->summarize($bookings[0]);
    $secondSummary = app(BookingPaymentSummaryService::class)->summarize($bookings[1]);

    expect($customSchedule->booking_id)->toBe($bookings[0]->id)
        ->and(collect($firstSummary['stages'])->pluck('label')->all())->toContain('Jadwal Khusus')
        ->and(collect($firstSummary['stages'])->pluck('label')->all())->not->toContain('Jadwal Lama Tanpa Booking')
        ->and(collect($secondSummary['stages'])->pluck('label')->all())->not->toContain('Jadwal Lama Tanpa Booking')
        ->and(collect($secondSummary['stages'])->pluck('label')->all())->not->toContain('Jadwal Khusus');

    $this->assertModelExists($legacySchedule);
});

test('admin assigns a legacy schedule to the booking that owns its transactions', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Penetapan Jadwal']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $bookings = collect(['01', '02'])->map(function (string $number) use ($block): Booking {
        $lot = Lot::create([
            'block_id' => $block->id,
            'lot_number' => $number,
            'house_price' => 100000000,
            'status' => 'booked',
        ]);

        return Booking::create([
            'lot_id' => $lot->id,
            'customer_id' => Customer::create(['name' => 'Customer '.$number])->id,
            'booking_date' => '2026-10-01',
            'status' => 'booking',
        ]);
    });
    $legacySchedule = BookingPaymentSchedule::create([
        'property_id' => $property->id,
        'title' => 'Pembayaran Tiang',
        'amount' => 1000000,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    BookingPayment::create([
        'booking_id' => $bookings[0]->id,
        'booking_payment_schedule_id' => $legacySchedule->id,
        'stage' => 'booking',
        'amount' => 1000000,
        'paid_at' => now(),
        'payment_channel' => 'manual',
        'approval_status' => 'approved',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.properties.payment-schedules.assign', [$property, $legacySchedule]), [
            'booking_id' => $bookings[0]->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('booking_payment_schedules', [
        'id' => $legacySchedule->id,
        'booking_id' => $bookings[0]->id,
    ]);
    $firstSummary = app(BookingPaymentSummaryService::class)->summarize($bookings[0]);
    $secondSummary = app(BookingPaymentSummaryService::class)->summarize($bookings[1]);
    $paymentStage = collect($firstSummary['stages'])->firstWhere('label', 'Pembayaran Tiang');

    expect($paymentStage['paid'])->toBe(1000000.0)
        ->and(collect($secondSummary['stages'])->pluck('label')->all())->not->toContain('Pembayaran Tiang');
});

test('admin cannot assign a legacy schedule to a booking with no matching transactions', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Transaksi Jadwal']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $bookings = collect(['01', '02'])->map(function (string $number) use ($block): Booking {
        $lot = Lot::create([
            'block_id' => $block->id,
            'lot_number' => $number,
            'house_price' => 100000000,
            'status' => 'booked',
        ]);

        return Booking::create([
            'lot_id' => $lot->id,
            'customer_id' => Customer::create(['name' => 'Customer '.$number])->id,
            'booking_date' => '2026-10-01',
            'status' => 'booking',
        ]);
    });
    $legacySchedule = BookingPaymentSchedule::create([
        'property_id' => $property->id,
        'title' => 'Pembayaran Tiang',
        'amount' => 1000000,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    BookingPayment::create([
        'booking_id' => $bookings[0]->id,
        'booking_payment_schedule_id' => $legacySchedule->id,
        'stage' => 'booking',
        'amount' => 1000000,
        'paid_at' => now(),
        'payment_channel' => 'manual',
        'approval_status' => 'approved',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.properties.payment-schedules.assign', [$property, $legacySchedule]), [
            'booking_id' => $bookings[1]->id,
        ])
        ->assertSessionHasErrors('booking_id');

    $this->assertDatabaseHas('booking_payment_schedules', [
        'id' => $legacySchedule->id,
        'booking_id' => null,
    ]);
});

test('booking-specific schedule actions are scoped to that booking', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Scope Jadwal']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $firstLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 100000000,
        'status' => 'booked',
    ]);
    $secondLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '02',
        'house_price' => 100000000,
        'status' => 'booked',
    ]);
    $firstBooking = Booking::create([
        'lot_id' => $firstLot->id,
        'customer_id' => Customer::create(['name' => 'Customer Satu'])->id,
        'booking_date' => '2026-10-01',
        'status' => 'booking',
    ]);
    $secondBooking = Booking::create([
        'lot_id' => $secondLot->id,
        'customer_id' => Customer::create(['name' => 'Customer Dua'])->id,
        'booking_date' => '2026-10-01',
        'status' => 'booking',
    ]);
    $schedule = BookingPaymentSchedule::create([
        'property_id' => $property->id,
        'booking_id' => $firstBooking->id,
        'title' => 'Jadwal Khusus',
        'amount' => 10000000,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.bookings.payment-schedules.update', [$firstBooking, $schedule]), [
            'title' => 'Jadwal Diperbarui',
            'amount' => '12.000.000',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('booking_payment_schedules', [
        'id' => $schedule->id,
        'booking_id' => $firstBooking->id,
        'title' => 'Jadwal Diperbarui',
        'amount' => '12000000.00',
    ]);

    $this->delete(route('admin.bookings.payment-schedules.destroy', [$secondBooking, $schedule]))
        ->assertNotFound();

    $this->delete(route('admin.bookings.payment-schedules.destroy', [$firstBooking, $schedule]))
        ->assertRedirect();

    $this->assertDatabaseMissing('booking_payment_schedules', ['id' => $schedule->id]);
});

test('fully paid booking schedules cannot be edited or deleted', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Jadwal Lunas']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 100000000,
        'status' => 'booked',
    ]);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => Customer::create(['name' => 'Customer Lunas'])->id,
        'booking_date' => '2026-10-01',
        'status' => 'booking',
    ]);
    $bookingSchedule = BookingPaymentSchedule::create([
        'property_id' => $property->id,
        'booking_id' => $booking->id,
        'title' => 'Jadwal Khusus Lunas',
        'amount' => 5000000,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    BookingPayment::create([
        'booking_id' => $booking->id,
        'booking_payment_schedule_id' => $bookingSchedule->id,
        'stage' => 'booking',
        'amount' => $bookingSchedule->amount,
        'paid_at' => now(),
        'payment_channel' => 'manual',
        'approval_status' => 'approved',
    ]);

    $this->actingAs($admin)->put(route('admin.bookings.payment-schedules.update', [$booking, $bookingSchedule]), [
        'title' => 'Jadwal Khusus Diubah',
        'amount' => 6000000,
    ])->assertSessionHasErrors('schedule');

    $this->delete(route('admin.bookings.payment-schedules.destroy', [$booking, $bookingSchedule]))
        ->assertSessionHasErrors('schedule');

    $this->assertDatabaseHas('booking_payment_schedules', [
        'id' => $bookingSchedule->id,
        'title' => 'Jadwal Khusus Lunas',
        'is_active' => true,
    ]);
});

test('a booking-specific schedule cannot be added through another property', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Salah']);
    $otherProperty = Property::create(['name' => 'Perumahan Benar']);
    $otherBlock = Block::create(['property_id' => $otherProperty->id, 'name' => 'A']);
    $otherLot = Lot::create([
        'block_id' => $otherBlock->id,
        'lot_number' => '01',
        'house_price' => 100000000,
        'status' => 'booked',
    ]);
    $booking = Booking::create([
        'lot_id' => $otherLot->id,
        'customer_id' => Customer::create(['name' => 'Customer Properti Lain'])->id,
        'booking_date' => '2026-10-01',
        'status' => 'booking',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.properties.payment-schedules.store', $property), [
            'booking_id' => $booking->id,
            'title' => 'Jadwal Tidak Valid',
            'amount' => 1000000,
        ])
        ->assertNotFound();

    $this->assertDatabaseCount('booking_payment_schedules', 0);
});

test('non-admin users cannot configure booking payment schedules', function () {
    $user = User::factory()->create(['role' => 'staff_marketing']);
    $property = Property::create(['name' => 'Perumahan Terbatas']);

    $this->actingAs($user)
        ->get(route('admin.properties.payment-schedules.index', $property))
        ->assertForbidden();
});
