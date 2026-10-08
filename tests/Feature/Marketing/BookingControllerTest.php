<?php

use App\Models\Block;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Division;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;

it('filters marketing bookings by selected customer', function () {
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Filter Customer']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $filteredCustomer = Customer::create(['name' => 'Customer Terpilih']);
    $otherCustomer = Customer::create(['name' => 'Customer Lain']);
    $filteredLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
    ]);
    $otherLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '02',
        'house_price' => 350000000,
    ]);
    Booking::create([
        'lot_id' => $filteredLot->id,
        'customer_id' => $filteredCustomer->id,
        'booking_date' => '2026-10-01',
        'status' => 'booking',
    ]);
    Booking::create([
        'lot_id' => $otherLot->id,
        'customer_id' => $otherCustomer->id,
        'booking_date' => '2026-10-02',
        'status' => 'booking',
    ]);

    $this->actingAs($user)
        ->get(route('marketing.bookings.index', ['customer_id' => $filteredCustomer->id]))
        ->assertSee('01/10/2026')
        ->assertDontSee('02/10/2026')
        ->assertSee('value="'.$filteredCustomer->id.'" selected', false);
});
