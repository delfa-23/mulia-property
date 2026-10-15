<?php

use App\Models\CommonFacility;
use App\Models\Division;
use App\Models\Property;
use App\Models\User;

test('construction staff cannot access common facility management', function () {
    $division = Division::create([
        'name' => 'Pembangunan',
        'slug' => 'pembangunan',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
    $this->actingAs($user)
        ->get('/pembangunan/common-facilities')
        ->assertNotFound();
});

test('admin lists only facilities for the selected housing', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Mulia Residence']);
    $otherProperty = Property::create(['name' => 'Griya Asri']);
    $facility = CommonFacility::create([
        'property_id' => $property->id,
        'name' => 'Taman Utama',
    ]);
    CommonFacility::create([
        'property_id' => $otherProperty->id,
        'name' => 'Gerbang Utama',
    ]);

    $response = $this->actingAs($user)->get(route('admin.common-facilities.index', [
        'property_id' => $property->id,
    ]));

    $response->assertOk()
        ->assertSee('Mulia Residence')
        ->assertSee('Taman Utama')
        ->assertDontSee('Gerbang Utama')
        ->assertViewHas('selectedProperty', fn (?Property $selectedProperty): bool => $selectedProperty?->is($property) ?? false)
        ->assertViewHas('facilities', fn ($facilities): bool => $facilities->contains('id', $facility->id)
            && $facilities->count() === 1);
});
