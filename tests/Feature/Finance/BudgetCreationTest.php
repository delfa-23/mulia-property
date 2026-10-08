<?php

use App\Models\Block;
use App\Models\Budget;
use App\Models\CommonFacility;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;

test('admin can create a project-level budget', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Mulia Residence']);

    $response = $this->actingAs($user)->post(route('finance.budgets.store'), [
        'property_id' => $property->id,
        'name' => 'Pekerjaan Infrastruktur',
        'category' => 'Infrastruktur',
        'amount' => '125000000.00',
        'period_start' => '2026-10-01',
        'period_end' => '2026-12-31',
        'notes' => 'Tahap pertama',
    ]);

    $response->assertRedirect(route('finance.index'))
        ->assertSessionDoesntHaveErrors();
    $this->assertDatabaseHas('budgets', [
        'property_id' => $property->id,
        'name' => 'Pekerjaan Infrastruktur',
        'amount' => '125000000.00',
        'created_by' => $user->id,
    ]);
});

test('admin can create a budget for a lot or facility without selecting both', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Mulia Residence']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => 'A-01',
        'house_price' => 350000000,
    ]);
    $facility = CommonFacility::create([
        'property_id' => $property->id,
        'name' => 'Taman',
        'created_by' => $user->id,
    ]);

    $lotResponse = $this->actingAs($user)->post(route('finance.budgets.store'), [
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'name' => 'Pekerjaan Kavling',
        'category' => 'Struktur',
        'amount' => 25000000,
    ]);
    $facilityResponse = $this->post(route('finance.budgets.store'), [
        'property_id' => $property->id,
        'facility_id' => $facility->id,
        'name' => 'Pekerjaan Taman',
        'category' => 'Fasilitas',
        'amount' => 10000000,
    ]);

    $lotResponse->assertRedirect(route('finance.index'))
        ->assertSessionDoesntHaveErrors();
    $facilityResponse->assertRedirect(route('finance.index'))
        ->assertSessionDoesntHaveErrors();
    $this->assertDatabaseHas('budgets', [
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'name' => 'Pekerjaan Kavling',
    ]);
    $this->assertDatabaseHas('budgets', [
        'property_id' => $property->id,
        'facility_id' => $facility->id,
        'name' => 'Pekerjaan Taman',
    ]);
});

test('budget form includes the supported RAB fields', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)->get(route('finance.budgets.create'));

    $response->assertOk()
        ->assertSee('Tambah RAB')
        ->assertSee('Nama RAB')
        ->assertSee('Nominal (Rp)')
        ->assertSee('Periode Mulai')
        ->assertSee('Periode Selesai');
});

test('budget cannot target a lot from another property', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $selectedProperty = Property::create(['name' => 'Project Terpilih']);
    $otherProperty = Property::create(['name' => 'Project Lain']);
    $block = Block::create([
        'property_id' => $otherProperty->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => 'A-01',
        'house_price' => 350000000,
    ]);

    $response = $this->actingAs($user)->post(route('finance.budgets.store'), [
        'property_id' => $selectedProperty->id,
        'lot_id' => $lot->id,
        'name' => 'Pekerjaan Kavling',
        'category' => 'Struktur',
        'amount' => 25000000,
    ]);

    $response->assertUnprocessable();
    expect(Budget::query()->exists())->toBeFalse();
});

test('budget cannot target a facility from another property', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $selectedProperty = Property::create(['name' => 'Project Terpilih']);
    $otherProperty = Property::create(['name' => 'Project Lain']);
    $facility = CommonFacility::create([
        'property_id' => $otherProperty->id,
        'name' => 'Taman',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(route('finance.budgets.store'), [
        'property_id' => $selectedProperty->id,
        'facility_id' => $facility->id,
        'name' => 'Pekerjaan Taman',
        'category' => 'Fasilitas',
        'amount' => 10000000,
    ]);

    $response->assertUnprocessable();
    expect(Budget::query()->exists())->toBeFalse();
});

test('budget cannot target both a lot and a facility', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Mulia Residence']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => 'A-01',
        'house_price' => 350000000,
    ]);
    $facility = CommonFacility::create([
        'property_id' => $property->id,
        'name' => 'Taman',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(route('finance.budgets.store'), [
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'facility_id' => $facility->id,
        'name' => 'Pekerjaan Khusus',
        'category' => 'Konstruksi',
        'amount' => 10000000,
    ]);

    $response->assertSessionHasErrors(['lot_id', 'facility_id']);
    expect(Budget::query()->exists())->toBeFalse();
});
