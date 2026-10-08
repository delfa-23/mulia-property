<?php

use App\Models\CommonFacility;
use App\Models\Property;
use App\Models\User;

test('admin facility page shows housing choices before listing any facilities', function () {
    $admin = User::factory()->create(['role' => 'admin']);
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

    $response = $this->actingAs($admin)->get(route('admin.common-facilities.index'));

    $response->assertSee('Mulia Residence')
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

test('admin facility page lists only facilities for the selected housing', function () {
    $admin = User::factory()->create(['role' => 'admin']);
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

    $response = $this->actingAs($admin)->get(route('admin.common-facilities.index', [
        'property_id' => $property->id,
    ]));

    $response->assertSee('Mulia Residence')
        ->assertSee('Taman Utama')
        ->assertSee('Edit')
        ->assertDontSee('Gerbang Utama')
        ->assertViewHas('selectedProperty', fn (?Property $selectedProperty): bool => $selectedProperty?->is($property) ?? false)
        ->assertViewHas('facilities', fn ($facilities): bool => $facilities->contains('id', $facility->id)
            && $facilities->count() === 1);
});
