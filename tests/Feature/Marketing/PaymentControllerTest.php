<?php

use App\Models\Block;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Division;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;

it('shows filtered booking payment details and a Midtrans checkout for the next stage', function () {
    $marketingDivision = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $marketingDivision->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Pembayaran']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'B']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '08',
        'house_price' => 120000000,
    ]);
    $customer = Customer::create(['name' => 'Customer Pembayaran']);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-10-10',
        'house_price_snapshot' => 120000000,
        'akad_fee' => 2500000,
        'status' => 'booking',
    ]);
    $otherCustomer = Customer::create(['name' => 'Customer Lain']);
    $searchFilteredLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '09',
        'house_price' => 120000000,
    ]);
    Booking::create([
        'lot_id' => $searchFilteredLot->id,
        'customer_id' => $otherCustomer->id,
        'booking_date' => '2026-10-09',
        'house_price_snapshot' => 120000000,
        'akad_fee' => 2500000,
        'status' => 'booking',
    ]);
    $statusFilteredLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '10',
        'house_price' => 120000000,
    ]);
    Booking::create([
        'lot_id' => $statusFilteredLot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-10-08',
        'house_price_snapshot' => 120000000,
        'akad_fee' => 2500000,
        'status' => 'process',
    ]);
    $otherProperty = Property::create(['name' => 'Perumahan Lain']);
    $otherBlock = Block::create(['property_id' => $otherProperty->id, 'name' => 'C']);
    $propertyFilteredLot = Lot::create([
        'block_id' => $otherBlock->id,
        'lot_number' => '11',
        'house_price' => 120000000,
    ]);
    Booking::create([
        'lot_id' => $propertyFilteredLot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-10-07',
        'house_price_snapshot' => 120000000,
        'akad_fee' => 2500000,
        'status' => 'booking',
    ]);

    $response = $this->actingAs($user)
        ->get(route('marketing.payments.index', [
            'property_id' => $property->id,
            'status' => 'booking',
            'search' => 'Customer Pembayaran',
        ]));

    $response
        ->assertViewIs('marketing.payments.index')
        ->assertSee('Customer Pembayaran')
        ->assertSee('Perumahan Pembayaran')
        ->assertSee('Rp 120.000.000')
        ->assertSee('Virtual Account, QRIS, atau e-wallet')
        ->assertSee('B/08')
        ->assertSee(route('marketing.bookings.payments.midtrans.store', $booking), false)
        ->assertDontSee('B/09')
        ->assertDontSee('B/10')
        ->assertDontSee('B/11')
        ->assertDontSee('Perumahan Lain')
        ->assertDontSee('Customer Lain')
        ->assertDontSee('Tidak ada booking yang cocok dengan filter.');
});

it('requires marketing access to view booking payments', function () {
    $financeDivision = Division::create([
        'name' => 'Finance',
        'slug' => 'finance',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_finance',
        'division_id' => $financeDivision->id,
    ]);

    $this->actingAs($user)
        ->get(route('marketing.payments.index'))
        ->assertForbidden();
});

it('redirects guests to sign in before showing booking payments', function () {
    $this->get(route('marketing.payments.index'))
        ->assertRedirect(route('login'));
});
