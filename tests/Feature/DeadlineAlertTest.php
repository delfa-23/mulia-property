<?php

use App\Models\AkadSchedule;
use App\Models\Block;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Customer;
use App\Models\Division;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

test('booking pages alert on expired active blocking and akad deadlines only', function () {
    $this->travelTo(Carbon::parse('2026-10-06 12:00:00'));
    Cache::put('booking-expiry-request-last-run', true, 55);
    $marketingDivision = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $pemberkasanDivision = Division::create([
        'name' => 'Pemberkasan',
        'slug' => 'pemberkasan',
        'is_active' => true,
    ]);
    $marketingUser = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $marketingDivision->id,
    ]);
    $pemberkasanUser = User::factory()->create([
        'role' => 'staff_pemberkasan',
        'division_id' => $pemberkasanDivision->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Deadline']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $expiredBlockingLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
    ]);
    $nonBlockingLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '02',
        'house_price' => 350000000,
    ]);
    $expiredAkadLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '03',
        'house_price' => 350000000,
    ]);
    $completedAkadLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '04',
        'house_price' => 350000000,
    ]);
    $expiredBlockingBooking = Booking::create([
        'lot_id' => $expiredBlockingLot->id,
        'customer_id' => Customer::create(['name' => 'Customer Blocking Terlewati'])->id,
        'booking_date' => '2026-10-01',
        'blocking_until' => '2026-10-05 12:00:00',
        'status' => 'booking',
    ]);
    Booking::create([
        'lot_id' => $nonBlockingLot->id,
        'customer_id' => Customer::create(['name' => 'Customer Blocking Tidak Aktif'])->id,
        'booking_date' => '2026-10-01',
        'blocking_until' => '2026-10-05 12:00:00',
        'status' => 'process',
    ]);
    $expiredAkadBooking = Booking::create([
        'lot_id' => $expiredAkadLot->id,
        'customer_id' => Customer::create(['name' => 'Customer Akad Terlewati'])->id,
        'booking_date' => '2026-10-01',
        'status' => 'process',
    ]);
    $completedAkadBooking = Booking::create([
        'lot_id' => $completedAkadLot->id,
        'customer_id' => Customer::create(['name' => 'Customer Akad Selesai'])->id,
        'booking_date' => '2026-10-01',
        'status' => 'process',
    ]);
    AkadSchedule::create([
        'booking_id' => $expiredAkadBooking->id,
        'scheduled_at' => '2026-10-05 12:00:00',
        'status' => 'scheduled',
        'created_by' => $pemberkasanUser->id,
    ]);
    AkadSchedule::create([
        'booking_id' => $completedAkadBooking->id,
        'scheduled_at' => '2026-10-05 12:00:00',
        'status' => 'completed',
        'created_by' => $pemberkasanUser->id,
    ]);

    $marketingResponse = $this->actingAs($marketingUser)->get(route('marketing.bookings.index'));
    $marketingDetailResponse = $this->get(route('marketing.bookings.show', $expiredBlockingBooking));
    $pemberkasanResponse = $this->actingAs($pemberkasanUser)->get(route('pemberkasan.bookings.index'));
    $pemberkasanDetailResponse = $this->get(route('pemberkasan.bookings.show', $expiredAkadBooking));

    $marketingResponse->assertSee('Deadline blocking Customer Blocking Terlewati terlewati')
        ->assertDontSee('Deadline blocking Customer Blocking Tidak Aktif terlewati');
    $marketingDetailResponse->assertSee('Deadline blocking Customer Blocking Terlewati terlewati');
    $pemberkasanResponse->assertSee('Jadwal akad Customer Akad Terlewati terlewati')
        ->assertDontSee('Jadwal akad Customer Akad Selesai terlewati');
    $pemberkasanDetailResponse->assertSee('Jadwal akad untuk Customer Akad Terlewati terlewati')
        ->assertDontSee('Jadwal akad untuk Customer Akad Selesai terlewati');
});

test('expired bookings are cancelled automatically and their lots are released', function () {
    $this->travelTo(Carbon::parse('2026-10-06 12:00:00'));
    $marketingDivision = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $marketingUser = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $marketingDivision->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Auto Cancel']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $expiredLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
        'status' => 'booked',
    ]);
    $activeLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '02',
        'house_price' => 350000000,
        'status' => 'process',
    ]);
    $expiredBooking = Booking::create([
        'lot_id' => $expiredLot->id,
        'customer_id' => Customer::create(['name' => 'Pembeli Expired'])->id,
        'booking_date' => '2026-10-01',
        'blocking_until' => '2026-10-05 12:00:00',
        'status' => 'booking',
    ]);
    $activeBooking = Booking::create([
        'lot_id' => $activeLot->id,
        'customer_id' => Customer::create(['name' => 'Pembeli Process'])->id,
        'booking_date' => '2026-10-01',
        'blocking_until' => '2026-10-05 12:00:00',
        'status' => 'process',
    ]);

    $this->artisan('bookings:expire')
        ->expectsOutput('1 booking expired.')
        ->assertSuccessful();

    $this->assertDatabaseMissing('bookings', ['id' => $expiredBooking->id]);
    $this->assertDatabaseHas('lots', ['id' => $expiredLot->id, 'status' => 'available']);
    $this->assertDatabaseHas('bookings', ['id' => $activeBooking->id, 'status' => 'process']);
    $this->assertDatabaseHas('lots', ['id' => $activeLot->id, 'status' => 'process']);
    $this->assertDatabaseHas('alerts', [
        'lot_id' => $expiredLot->id,
        'type' => 'booking_expired',
    ]);
    $this->assertDatabaseHas('activity_logs', [
        'action' => 'booking.auto_deleted',
        'subject_type' => Booking::class,
        'subject_id' => $expiredBooking->id,
    ]);

});

test('approved payments for a zero akad fee do not prevent booking expiry', function () {
    $this->travelTo(Carbon::parse('2026-10-06 12:00:00'));
    $property = Property::create(['name' => 'Perumahan Akad Nol']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
        'status' => 'booked',
    ]);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => Customer::create(['name' => 'Pembeli Akad Nol'])->id,
        'booking_date' => '2026-10-01',
        'blocking_until' => '2026-10-05 12:00:00',
        'akad_fee' => 0,
        'status' => 'booking',
    ]);
    BookingPayment::create([
        'booking_id' => $booking->id,
        'stage' => 'akad_fee',
        'amount' => 2500000,
        'paid_at' => now(),
        'approval_status' => 'approved',
    ]);

    $this->artisan('bookings:expire')
        ->expectsOutput('1 booking expired.')
        ->assertSuccessful();

    $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    $this->assertDatabaseHas('lots', ['id' => $lot->id, 'status' => 'available']);
});

test('dashboard requests expire overdue unpaid bookings when the scheduler is unavailable', function () {
    $this->travelTo(Carbon::parse('2026-10-06 12:00:00'));
    Cache::forget('booking-expiry-request-last-run');
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Auto Cancel Request']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
        'status' => 'booked',
    ]);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => Customer::create(['name' => 'Pembeli Lewat Request'])->id,
        'booking_date' => '2026-10-01',
        'blocking_until' => '2026-10-05 12:00:00',
        'status' => 'booking',
    ]);

    $this->actingAs($user)
        ->get(route('marketing.dashboard'))
        ->assertOk();

    $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    $this->assertDatabaseHas('lots', ['id' => $lot->id, 'status' => 'available']);
});
