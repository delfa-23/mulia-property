<?php

use App\Models\Block;
use App\Models\Budget;
use App\Models\CommonFacility;
use App\Models\ExpenseTransaction;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;

test('finance page filters budgets and expenses by property and combined block lot', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Terpilih']);
    $otherProperty = Property::create(['name' => 'Perumahan Lain']);
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
        'lot_number' => '01',
        'house_price' => 350000000,
    ]);
    $otherLot = Lot::create([
        'block_id' => $otherBlock->id,
        'lot_number' => '02',
        'house_price' => 350000000,
    ]);
    $otherPropertyLot = Lot::create([
        'block_id' => $otherPropertyBlock->id,
        'lot_number' => '03',
        'house_price' => 350000000,
    ]);

    Budget::create([
        'property_id' => $property->id,
        'name' => 'RAB Umum Project',
        'category' => 'Infrastruktur',
        'amount' => 1000,
        'created_by' => $user->id,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'name' => 'RAB Kavling Terpilih',
        'category' => 'Struktur',
        'amount' => 2000,
        'created_by' => $user->id,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'lot_id' => $otherLot->id,
        'name' => 'RAB Kavling Lain',
        'category' => 'Struktur',
        'amount' => 5000,
        'created_by' => $user->id,
    ]);
    Budget::create([
        'property_id' => $otherProperty->id,
        'lot_id' => $otherPropertyLot->id,
        'name' => 'RAB Project Lain',
        'category' => 'Struktur',
        'amount' => 9000,
        'created_by' => $user->id,
    ]);

    ExpenseTransaction::create([
        'property_id' => $property->id,
        'transaction_number' => 'EXP-FILTER-001',
        'transaction_date' => '2026-10-01',
        'category' => 'Material Umum',
        'amount' => 100,
        'created_by' => $user->id,
    ]);
    ExpenseTransaction::create([
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'transaction_number' => 'EXP-FILTER-002',
        'transaction_date' => '2026-10-02',
        'category' => 'Material Kavling',
        'amount' => 200,
        'created_by' => $user->id,
    ]);
    ExpenseTransaction::create([
        'property_id' => $property->id,
        'lot_id' => $otherLot->id,
        'transaction_number' => 'EXP-FILTER-003',
        'transaction_date' => '2026-10-03',
        'category' => 'Material Kavling Lain',
        'amount' => 500,
        'created_by' => $user->id,
    ]);
    ExpenseTransaction::create([
        'property_id' => $otherProperty->id,
        'lot_id' => $otherPropertyLot->id,
        'transaction_number' => 'EXP-FILTER-004',
        'transaction_date' => '2026-10-04',
        'category' => 'Material Project Lain',
        'amount' => 900,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('finance.index', [
        'property_id' => $property->id,
        'lot_id' => $lot->id,
    ]));

    $response->assertSee('Perumahan Terpilih')
        ->assertSee('Blok A / Kavling 01')
        ->assertDontSee('Blok B / Kavling 02')
        ->assertDontSee('Blok C / Kavling 03')
        ->assertSee('RAB Umum Project')
        ->assertSee('RAB Kavling Terpilih')
        ->assertDontSee('RAB Kavling Lain')
        ->assertDontSee('RAB Project Lain')
        ->assertSee('Material Umum')
        ->assertSee('Material Kavling')
        ->assertDontSee('Material Kavling Lain')
        ->assertDontSee('Material Project Lain')
        ->assertViewHas('totalBudget', fn (int|float|string $total): bool => (float) $total === 3000.0)
        ->assertViewHas('totalExpense', fn (int|float|string $total): bool => (float) $total === 300.0)
        ->assertViewHas('propertyId', $property->id)
        ->assertViewHas('lotId', $lot->id);
});

test('finance page does not list lots until a property is selected', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Terpilih']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
    ]);

    $response = $this->actingAs($user)->get(route('finance.index'));

    $response->assertSee('Pilih perumahan terlebih dahulu')
        ->assertDontSee('Blok A / Kavling 01');
});

test('finance page marks budgets complete only after their end date has passed', function () {
    $this->travelTo(now()->setDate(2026, 10, 6)->setTime(12, 0));
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Deadline']);

    Budget::create([
        'property_id' => $property->id,
        'name' => 'RAB Deadline Terlewati',
        'category' => 'Infrastruktur',
        'amount' => 1000,
        'period_end' => '2026-10-05',
        'created_by' => $user->id,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'name' => 'RAB Deadline Hari Ini',
        'category' => 'Infrastruktur',
        'amount' => 1000,
        'period_end' => '2026-10-06',
        'created_by' => $user->id,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'name' => 'RAB Deadline Mendatang',
        'category' => 'Infrastruktur',
        'amount' => 1000,
        'period_end' => '2026-10-07',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('finance.index'));

    $response->assertSee('RAB Deadline Terlewati telah selesai')
        ->assertDontSee('RAB Deadline Hari Ini telah selesai')
        ->assertDontSee('RAB Deadline Mendatang telah selesai');
});

test('expense creation renders project-filtered lot and facility selectors', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Form']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
    ]);
    $facility = CommonFacility::create([
        'property_id' => $property->id,
        'name' => 'Taman',
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('finance.expense-transactions.create'))
        ->assertSee('data-expense-target', false)
        ->assertSee('data-facility-select', false)
        ->assertSee('data-property-id="'.$property->id.'"', false)
        ->assertSee('value="'.$lot->id.'"', false)
        ->assertSee('value="'.$facility->id.'"', false);
});

test('expense editing retains its lot while rendering the facility selector', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Form']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
    ]);
    $facility = CommonFacility::create([
        'property_id' => $property->id,
        'name' => 'Taman',
        'created_by' => $user->id,
    ]);
    $expense = ExpenseTransaction::create([
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'transaction_number' => 'EXP-FORM-001',
        'transaction_date' => '2026-10-07',
        'category' => 'Material',
        'amount' => 1000,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)->get(route('finance.expense-transactions.edit', $expense))
        ->assertSee('data-expense-target', false)
        ->assertSee('data-facility-select', false)
        ->assertSee('data-property-id="'.$property->id.'"', false)
        ->assertSee('value="'.$lot->id.'" data-property-id="'.$property->id.'" selected', false)
        ->assertSee('value="'.$facility->id.'"', false);
});

test('expense creation rejects selecting a lot and facility together', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Pengeluaran']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
    ]);
    $facility = CommonFacility::create([
        'property_id' => $property->id,
        'name' => 'Taman',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(route('finance.expense-transactions.store'), [
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'facility_id' => $facility->id,
        'transaction_date' => '2026-10-07',
        'category' => 'Material',
        'amount' => 1000,
    ]);

    $response->assertSessionHasErrors([
        'lot_id' => 'Pilih kavling atau fasilitas saja, bukan keduanya.',
        'facility_id' => 'Pilih kavling atau fasilitas saja, bukan keduanya.',
    ]);
    expect(ExpenseTransaction::query()->exists())->toBeFalse();
});

test('expense creation allows assigning an expense to a lot', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Pengeluaran']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
    ]);

    $response = $this->actingAs($user)->post(route('finance.expense-transactions.store'), [
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'transaction_date' => '2026-10-07',
        'category' => 'Material Kavling',
        'amount' => 1000,
    ]);

    $response->assertRedirect(route('finance.index'));
    $this->assertDatabaseHas('expense_transactions', [
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'facility_id' => null,
        'category' => 'Material Kavling',
    ]);
});

test('expense creation allows assigning an expense to a facility', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Pengeluaran']);
    $facility = CommonFacility::create([
        'property_id' => $property->id,
        'name' => 'Taman',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(route('finance.expense-transactions.store'), [
        'property_id' => $property->id,
        'facility_id' => $facility->id,
        'transaction_date' => '2026-10-07',
        'category' => 'Material Fasilitas',
        'amount' => 1000,
    ]);

    $response->assertRedirect(route('finance.index'));
    $this->assertDatabaseHas('expense_transactions', [
        'property_id' => $property->id,
        'lot_id' => null,
        'facility_id' => $facility->id,
        'category' => 'Material Fasilitas',
    ]);
});

test('expense update rejects switching the lot and facility selections on together', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Pengeluaran']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
    ]);
    $facility = CommonFacility::create([
        'property_id' => $property->id,
        'name' => 'Taman',
        'created_by' => $user->id,
    ]);
    $expense = ExpenseTransaction::create([
        'property_id' => $property->id,
        'transaction_number' => 'EXP-UPDATE-001',
        'transaction_date' => '2026-10-06',
        'category' => 'Lama',
        'amount' => 1000,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->put(route('finance.expense-transactions.update', $expense), [
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'facility_id' => $facility->id,
        'transaction_date' => '2026-10-07',
        'category' => 'Baru',
        'amount' => 2000,
    ]);

    $response->assertSessionHasErrors([
        'lot_id' => 'Pilih kavling atau fasilitas saja, bukan keduanya.',
        'facility_id' => 'Pilih kavling atau fasilitas saja, bukan keduanya.',
    ]);
    $this->assertDatabaseHas('expense_transactions', [
        'id' => $expense->id,
        'category' => 'Lama',
        'amount' => 1000,
    ]);
});
