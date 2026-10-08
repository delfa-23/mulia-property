<?php

use App\Models\AkadSchedule;
use App\Models\Block;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Division;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Carbon;

test('booking pages alert on expired active blocking and akad deadlines only', function () {
    $this->travelTo(Carbon::parse('2026-10-06 12:00:00'));
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
