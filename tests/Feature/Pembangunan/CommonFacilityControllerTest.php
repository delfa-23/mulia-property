<?php

use App\Models\CommonFacility;
use App\Models\Division;
use App\Models\Property;
use App\Models\User;

test('facility page shows housing choices before listing any facilities', function () {
    $division = Division::create([
        'name' => 'Pembangunan',
        'slug' => 'pembangunan',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
    $firstProperty = Property::create(['name' => 'Mulia Residence']);
    $secondProperty = Property::create(['name' => 'Griya Asri']);
    CommonFacility::create([
        'property_id' => $firstProperty->id,
        'name' => 'Taman Utama',
    ]);
    CommonFacility::create([
        'property_id' => $secondProperty->id,
        'name' => 'Gerbang Utama',
    ]);

    $response = $this->actingAs($user)->get(route('pembangunan.common-facilities.index'));

    $response->assertOk()
        ->assertSee('Mulia Residence')
        ->assertSee('Griya Asri')
        ->assertDontSee('Taman Utama')
        ->assertDontSee('Gerbang Utama')
        ->assertSee('name="property_id"', false)
        ->assertSee('type="submit"', false)
        ->assertSee('Tampilkan')
        ->assertDontSee('data-auto-filter', false)
        ->assertDontSee('name="facility_id"', false)
        ->assertDontSee('name="search"', false);
});

test('facility page lists only facilities for the selected housing', function () {
    $division = Division::create([
        'name' => 'Pembangunan',
        'slug' => 'pembangunan',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
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

    $response = $this->actingAs($user)->get(route('pembangunan.common-facilities.index', [
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
