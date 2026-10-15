<?php

use App\Models\Block;
use App\Models\Division;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;

test('construction staff can view the housing block and lot hierarchy without edit controls', function () {
    $division = Division::firstOrCreate(
        ['slug' => 'pembangunan'],
        ['name' => 'Pembangunan', 'is_active' => true]
    );
    $user = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
    $property = Property::create([
        'name' => 'Mulia Residence',
        'address' => 'Jl. Pembangunan',
    ]);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    Lot::create([
        'block_id' => $block->id,
        'lot_number' => 'A-01',
        'house_price' => 350000000,
        'status' => 'available',
    ]);

    $this->actingAs($user)
        ->get(route('pembangunan.properties.index'))
        ->assertSee('Mulia Residence')
        ->assertSee('1 blok · 1 kavling')
        ->assertSee('!text-orange-900')
        ->assertSee('Blok A')
        ->assertSee('A-01')
        ->assertSee('Rp 350.000.000')
        ->assertSee('Data dikelola oleh Admin.')
        ->assertDontSee('Buat Project Perumahan')
        ->assertDontSee('Hapus');

    $this->post('/pembangunan/properties')
        ->assertNotFound();
});
