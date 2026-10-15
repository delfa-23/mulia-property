<?php

use App\Models\Block;
use App\Models\Budget;
use App\Models\CommonFacility;
use App\Models\Division;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;

test('admin can create a project-level budget', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Mulia Residence']);

    $response = $this->actingAs($user)->post(route('admin.budgets.store'), [
        'purpose' => 'pembangunan',
        'property_id' => $property->id,
        'name' => 'Pekerjaan Infrastruktur',
        'category' => 'Infrastruktur',
        'amount' => '125000000.00',
        'period_start' => '2026-10-01',
        'period_end' => '2026-12-31',
        'notes' => 'Tahap pertama',
    ]);

    $response->assertRedirect(route('admin.budgets.index'))
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

    $lotResponse = $this->actingAs($user)->post(route('admin.budgets.store'), [
        'purpose' => 'pembangunan',
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'name' => 'Pekerjaan Kavling',
        'category' => 'Struktur',
        'amount' => 25000000,
    ]);
    $facilityResponse = $this->post(route('admin.budgets.store'), [
        'purpose' => 'pembangunan',
        'property_id' => $property->id,
        'facility_id' => $facility->id,
        'name' => 'Pekerjaan Taman',
        'category' => 'Fasilitas',
        'amount' => 10000000,
    ]);

    $lotResponse->assertRedirect(route('admin.budgets.index'))
        ->assertSessionDoesntHaveErrors();
    $facilityResponse->assertRedirect(route('admin.budgets.index'))
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

    $response = $this->actingAs($user)->get(route('admin.budgets.create'));

    $response->assertOk()
        ->assertSee('Tambah RAB')
        ->assertSee('Nama RAB')
        ->assertSee('Nominal (Rp)')
        ->assertSee('Pilih salah satu sasaran saja: Kavling atau Fasilitas. Keduanya tidak dapat dipilih bersamaan.')
        ->assertSee('aria-describedby="budget-target-note"', false)
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

    $response = $this->actingAs($user)->post(route('admin.budgets.store'), [
        'purpose' => 'pembangunan',
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

    $response = $this->actingAs($user)->post(route('admin.budgets.store'), [
        'purpose' => 'pembangunan',
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

    $response = $this->actingAs($user)->post(route('admin.budgets.store'), [
        'purpose' => 'pembangunan',
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

test('admin can create a marketing RAB without selecting a property', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user)
        ->post(route('admin.budgets.store'), [
            'purpose' => 'marketing',
            'name' => 'Promo Pembukaan',
            'category' => 'Promosi',
            'amount' => 15000000,
        ])
        ->assertRedirect(route('admin.budgets.index'))
        ->assertSessionDoesntHaveErrors();

    $this->assertDatabaseHas('budgets', [
        'purpose' => 'marketing',
        'property_id' => null,
        'name' => 'Promo Pembukaan',
        'amount' => 15000000,
        'created_by' => $user->id,
    ]);
});

test('admin cannot create a marketing RAB for material expenses', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user)
        ->post(route('admin.budgets.store'), [
            'purpose' => 'marketing',
            'name' => 'RAB Material Bangunan',
            'category' => 'Material Bangunan',
            'amount' => 15000000,
        ])
        ->assertSessionHasErrors([
            'category' => 'Kategori material harus dimasukkan ke RAB Pembangunan.',
        ]);

    expect(Budget::query()->exists())->toBeFalse();
});

test('admin can create a marketing RAB for a selected lot', function () {
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

    $this->actingAs($user)
        ->post(route('admin.budgets.store'), [
            'purpose' => 'marketing',
            'property_id' => $property->id,
            'lot_id' => $lot->id,
            'name' => 'Promo Kavling A-01',
            'category' => 'Promo',
            'amount' => 15000000,
        ])
        ->assertRedirect(route('admin.budgets.index'))
        ->assertSessionDoesntHaveErrors();

    $this->assertDatabaseHas('budgets', [
        'purpose' => 'marketing',
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'facility_id' => null,
        'name' => 'Promo Kavling A-01',
        'created_by' => $user->id,
    ]);
});

test('admin can filter RAB by property, block, lot, and facility', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Mulia Residence']);
    $otherProperty = Property::create(['name' => 'Mulia Garden']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $otherBlock = Block::create([
        'property_id' => $property->id,
        'name' => 'B',
    ]);
    $otherPropertyBlock = Block::create([
        'property_id' => $otherProperty->id,
        'name' => 'C',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => 'A-01',
        'house_price' => 350000000,
    ]);
    $otherLot = Lot::create([
        'block_id' => $otherBlock->id,
        'lot_number' => 'B-01',
        'house_price' => 350000000,
    ]);
    $otherPropertyLot = Lot::create([
        'block_id' => $otherPropertyBlock->id,
        'lot_number' => 'C-01',
        'house_price' => 350000000,
    ]);
    $facility = CommonFacility::create([
        'property_id' => $property->id,
        'name' => 'Taman',
        'created_by' => $user->id,
    ]);

    Budget::create([
        'property_id' => $property->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Project',
        'category' => 'Umum',
        'amount' => 1000,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Kavling A',
        'category' => 'Struktur',
        'amount' => 2000,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'lot_id' => $otherLot->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Kavling B',
        'category' => 'Struktur',
        'amount' => 3000,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'facility_id' => $facility->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Taman',
        'category' => 'Fasilitas',
        'amount' => 4000,
    ]);
    Budget::create([
        'property_id' => $otherProperty->id,
        'lot_id' => $otherPropertyLot->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Perumahan Lain',
        'category' => 'Struktur',
        'amount' => 5000,
    ]);

    $propertyResponse = $this->actingAs($user)->get(route('admin.budgets.index', [
        'purpose' => 'pembangunan',
        'property_id' => $property->id,
    ]));
    $propertyResponse->assertSee('RAB Project')
        ->assertSee('RAB Kavling A')
        ->assertSee('RAB Kavling B')
        ->assertSee('RAB Taman')
        ->assertDontSee('RAB Perumahan Lain');

    $lotResponse = $this->get(route('admin.budgets.index', [
        'purpose' => 'pembangunan',
        'property_id' => $property->id,
        'block_id' => $block->id,
        'lot_id' => $lot->id,
    ]));
    $lotResponse->assertSee('RAB Project')
        ->assertSee('RAB Kavling A')
        ->assertDontSee('RAB Kavling B')
        ->assertDontSee('RAB Taman')
        ->assertDontSee('RAB Perumahan Lain');

    $facilityResponse = $this->get(route('admin.budgets.index', [
        'purpose' => 'pembangunan',
        'property_id' => $property->id,
        'facility_id' => $facility->id,
    ]));
    $facilityResponse->assertSee('RAB Project')
        ->assertSee('RAB Taman')
        ->assertDontSee('RAB Kavling A')
        ->assertDontSee('RAB Kavling B')
        ->assertDontSee('RAB Perumahan Lain');
});

test('non-admin users cannot access RAB management', function () {
    $division = Division::firstOrCreate(
        ['slug' => 'marketing'],
        ['name' => 'Marketing', 'is_active' => true]
    );
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);

    $this->actingAs($user)
        ->get(route('admin.budgets.index'))
        ->assertForbidden();
});
