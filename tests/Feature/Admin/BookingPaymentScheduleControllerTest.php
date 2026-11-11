<?php

use App\Models\Block;
use App\Models\BookingPaymentSchedule;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;

test('admin configures booking payment schedules per property', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Jadwal']);
    $otherProperty = Property::create(['name' => 'Perumahan Lain']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 100000000,
        'status' => 'available',
    ]);

    $this->actingAs($admin)
        ->from(route('admin.properties.payment-schedules.index', $property))
        ->post(route('admin.properties.payment-schedules.store', $property), [
            'title' => 'Pembayaran 1',
            'amount' => '1.000.000',
        ])
        ->assertRedirect(route('admin.properties.payment-schedules.index', $property));

    $schedule = BookingPaymentSchedule::query()->sole();

    expect($schedule->property_id)->toBe($property->id)
        ->and((float) $schedule->amount)->toBe(1000000.0)
        ->and($schedule->is_active)->toBeTrue();

    $this->get(route('admin.properties.payment-schedules.index', $property))
        ->assertSee('Pembayaran 1')
        ->assertSee('Rp 1.000.000');
    $this->get(route('admin.properties.payment-schedules.index', $otherProperty))
        ->assertDontSee('>Pembayaran 1</p>', false);
});

test('admin cannot configure booking payment schedules above the lowest lot price', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Batas Nominal']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 1000000,
        'status' => 'available',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.properties.payment-schedules.store', $property), [
            'title' => 'Pembayaran Melebihi Harga',
            'amount' => 1000001,
        ])
        ->assertSessionHasErrors('amount');

    $this->assertDatabaseCount('booking_payment_schedules', 0);
});

test('non-admin users cannot configure booking payment schedules', function () {
    $user = User::factory()->create(['role' => 'staff_marketing']);
    $property = Property::create(['name' => 'Perumahan Terbatas']);

    $this->actingAs($user)
        ->get(route('admin.properties.payment-schedules.index', $property))
        ->assertForbidden();
});
