<?php

use App\Models\Block;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingPaymentSchedule;
use App\Models\BookingPaymentSetting;
use App\Models\Customer;
use App\Models\Division;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;
use App\Services\BookingPaymentSummaryService;
use Illuminate\Support\Carbon;

test('admin configures category amounts and deadlines for matching bookings', function () {
    $this->travelTo(Carbon::parse('2026-11-18 10:00:00'));
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Setting Pembayaran']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 100000000,
        'status' => 'booked',
    ]);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => Customer::create(['name' => 'Customer Cash'])->id,
        'booking_date' => '2026-11-18',
        'payment_category' => 'cash',
        'house_price_snapshot' => 100000000,
        'blocking_until' => now()->addDay(),
        'dp_deadline_at' => now()->addDays(2),
        'status' => 'booking',
    ]);
    BookingPaymentSchedule::create([
        'property_id' => $property->id,
        'booking_id' => $booking->id,
        'title' => 'Pelunasan',
        'amount' => 98000000,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.payment-settings.update', 'cash'), [
            'booking_fee_amount' => '500.000',
            'booking_fee_deadline_hours' => 12,
            'dp_amount' => '1.500.000',
            'dp_deadline_days' => 12,
        ])
        ->assertRedirect(route('admin.payment-settings.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('booking_payment_settings', [
        'payment_category' => 'cash',
        'booking_fee_amount' => '500000.00',
        'dp_amount' => '1500000.00',
        'booking_fee_deadline_hours' => 12,
        'dp_deadline_days' => 12,
    ]);
    expect($booking->fresh()->blocking_until->toDateTimeString())->toBe('2026-11-18 22:00:00')
        ->and($booking->fresh()->dp_deadline_at->toDateTimeString())->toBe('2026-11-30 10:00:00');

    $summary = app(BookingPaymentSummaryService::class)->summarize($booking->fresh());

    expect(collect($summary['stages'])->pluck('label')->all())->toBe([
        'Booking Fee',
        'Down Payment (DP)',
        'Pelunasan',
        'Biaya Akad',
    ]);
    expect(collect($summary['stages'])->pluck('due')->all())->toBe([500000.0, 1500000.0, 98000000.0, 0.0]);

    $this->get(route('admin.payment-settings.index'))
        ->assertSee('Konfigurasi Pembayaran Booking')
        ->assertSee('Cara Pembayaran')
        ->assertSee('name="payment_category"', false)
        ->assertSee('Konfigurasi Cash')
        ->assertSee('Konfigurasi KPR')
        ->assertSee('Isi jumlah jam sejak booking dibuat')
        ->assertSee('Isi jumlah hari sejak booking dibuat');
});

test('existing bookings without a payment category use the configured cash amounts', function () {
    $cashSetting = BookingPaymentSetting::query()->where('payment_category', 'cash')->firstOrFail();
    $cashSetting->update([
        'booking_fee_amount' => 500000,
        'dp_amount' => 1500000,
    ]);
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $staff = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);

    $property = Property::create(['name' => 'Perumahan Booking Cash Default']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 100000000,
    ]);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => Customer::create(['name' => 'Customer Existing'])->id,
        'created_by' => $staff->id,
        'booking_date' => '2026-11-18',
        'status' => 'booking',
    ]);

    $summary = app(BookingPaymentSummaryService::class)->summarize($booking);
    $configuredStages = collect($summary['stages'])
        ->whereIn('stage', ['booking_fee', 'dp'])
        ->pluck('due')
        ->all();

    expect($configuredStages)->toBe([500000.0, 1500000.0]);

    $this->actingAs($staff)
        ->get(route('marketing.payments.index'))
        ->assertSee('Booking Fee')
        ->assertSee('Down Payment (DP)')
        ->assertDontSee('Sisa Pembayaran Booking');
});

test('marketing selects cash or kpr and booking deadlines are filled from category settings', function () {
    $this->travelTo(Carbon::parse('2026-11-18 10:00:00'));
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $staff = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $setting = BookingPaymentSetting::query()->where('payment_category', 'kpr')->firstOrFail();
    $setting->update([
        'booking_fee_amount' => 750000,
        'dp_amount' => 20000000,
        'booking_fee_deadline_hours' => 24,
        'dp_deadline_days' => 21,
    ]);
    $property = Property::create(['name' => 'Perumahan KPR']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 300000000,
        'status' => 'available',
    ]);

    $this->actingAs($staff)
        ->get(route('marketing.bookings.create'))
        ->assertSee('name="payment_category"', false)
        ->assertSee('>Cash</option>', false)
        ->assertSee('>KPR</option>', false);

    $this->post(route('marketing.bookings.store'), [
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'customer_mode' => 'new',
        'new_customer' => [
            'name' => 'Pembeli KPR',
            'nik' => '1234567890123456',
            'phone' => '081234567890',
        ],
        'booking_date' => '2026-11-18',
        'payment_category' => 'kpr',
        'status' => 'booking',
    ])
        ->assertRedirect(route('marketing.bookings.index'))
        ->assertSessionHasNoErrors();

    $booking = Booking::query()->where('payment_category', 'kpr')->sole();

    expect($booking->blocking_until->toDateTimeString())->toBe('2026-11-19 10:00:00')
        ->and($booking->dp_deadline_at->toDateTimeString())->toBe('2026-12-09 10:00:00');
    $this->assertDatabaseHas('lots', ['id' => $lot->id, 'status' => 'booked']);
});

test('expired booking fee releases its lot while an unpaid overdue dp only creates an alert', function () {
    $this->travelTo(Carbon::parse('2026-11-18 10:00:00'));
    User::factory()->create(['role' => 'admin']);
    $setting = BookingPaymentSetting::query()->where('payment_category', 'cash')->firstOrFail();
    $setting->update([
        'booking_fee_amount' => 500000,
        'dp_amount' => 1500000,
    ]);

    $property = Property::create(['name' => 'Perumahan Deadline']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $feeLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 100000000,
        'status' => 'booked',
    ]);
    $dpLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '02',
        'house_price' => 100000000,
        'status' => 'booked',
    ]);
    $feeExpiredBooking = Booking::create([
        'lot_id' => $feeLot->id,
        'customer_id' => Customer::create(['name' => 'Fee Belum Dibayar'])->id,
        'booking_date' => '2026-11-17',
        'payment_category' => 'cash',
        'blocking_until' => now()->subHour(),
        'dp_deadline_at' => now()->subHour(),
        'status' => 'booking',
    ]);
    $dpExpiredBooking = Booking::create([
        'lot_id' => $dpLot->id,
        'customer_id' => Customer::create(['name' => 'DP Belum Dibayar'])->id,
        'booking_date' => '2026-11-17',
        'payment_category' => 'cash',
        'blocking_until' => now()->subHour(),
        'dp_deadline_at' => now()->subHour(),
        'status' => 'booking',
    ]);
    BookingPayment::create([
        'booking_id' => $dpExpiredBooking->id,
        'stage' => 'booking_fee',
        'amount' => 500000,
        'paid_at' => now()->subDay(),
        'approval_status' => 'approved',
    ]);

    $this->artisan('bookings:expire')
        ->expectsOutput('1 booking expired.')
        ->expectsOutput('1 DP deadline alerts created.')
        ->assertSuccessful();

    $this->assertDatabaseMissing('bookings', ['id' => $feeExpiredBooking->id]);
    $this->assertDatabaseHas('lots', ['id' => $feeLot->id, 'status' => 'available']);
    $this->assertModelExists($dpExpiredBooking);
    $this->assertDatabaseHas('lots', ['id' => $dpLot->id, 'status' => 'booked']);
    $this->assertDatabaseHas('alerts', [
        'lot_id' => $dpLot->id,
        'type' => 'dp_deadline',
    ]);
    expect($dpExpiredBooking->fresh()->dp_deadline_alerted_at)->not->toBeNull();

    $this->artisan('bookings:expire')
        ->expectsOutput('0 booking expired.')
        ->expectsOutput('0 DP deadline alerts created.')
        ->assertSuccessful();

    $this->assertDatabaseCount('alerts', 2);
});
