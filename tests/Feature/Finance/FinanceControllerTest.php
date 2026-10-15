<?php

use App\Models\Block;
use App\Models\Budget;
use App\Models\CommonFacility;
use App\Models\Division;
use App\Models\ExpenseTransaction;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Carbon;

test('finance page filters prices and marketing expenses by property and lot', function () {
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
        'division' => 'marketing',
        'transaction_number' => 'EXP-FILTER-001',
        'transaction_date' => '2026-10-01',
        'category' => 'Material Umum',
        'amount' => 100,
        'created_by' => $user->id,
    ]);
    ExpenseTransaction::create([
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'division' => 'marketing',
        'transaction_number' => 'EXP-FILTER-002',
        'transaction_date' => '2026-10-02',
        'category' => 'Material Kavling',
        'amount' => 200,
        'created_by' => $user->id,
    ]);
    ExpenseTransaction::create([
        'property_id' => $property->id,
        'lot_id' => $otherLot->id,
        'division' => 'marketing',
        'transaction_number' => 'EXP-FILTER-003',
        'transaction_date' => '2026-10-03',
        'category' => 'Material Kavling Lain',
        'amount' => 500,
        'created_by' => $user->id,
    ]);
    ExpenseTransaction::create([
        'property_id' => $otherProperty->id,
        'lot_id' => $otherPropertyLot->id,
        'division' => 'marketing',
        'transaction_number' => 'EXP-FILTER-004',
        'transaction_date' => '2026-10-04',
        'category' => 'Material Project Lain',
        'amount' => 900,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('finance.index', [
        'division' => 'marketing',
        'property_id' => $property->id,
        'lot_id' => $lot->id,
    ]));

    $response->assertSee('Perumahan Terpilih')
        ->assertSee('Blok A / Kavling 01')
        ->assertDontSee('Blok B / Kavling 02')
        ->assertDontSee('Blok C / Kavling 03')
        ->assertDontSee('RAB Umum Project')
        ->assertDontSee('RAB Kavling Terpilih')
        ->assertDontSee('RAB Kavling Lain')
        ->assertDontSee('RAB Project Lain')
        ->assertSee('Material Umum')
        ->assertSee('Material Kavling')
        ->assertDontSee('Material Kavling Lain')
        ->assertDontSee('Material Project Lain')
        ->assertViewHas('totalExpense', fn (int|float|string $total): bool => (float) $total === 300.0)
        ->assertViewHas('propertyId', $property->id)
        ->assertViewHas('lotId', $lot->id);
});

test('finance page lists admin-created lots before a property is selected', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Terpilih']);
    $otherProperty = Property::create(['name' => 'Perumahan Lain']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $otherBlock = Block::create([
        'property_id' => $otherProperty->id,
        'name' => 'B',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
    ]);
    $otherLot = Lot::create([
        'block_id' => $otherBlock->id,
        'lot_number' => '02',
        'house_price' => 450000000,
    ]);

    $response = $this->actingAs($user)->get(route('finance.index', ['division' => 'pembangunan']));

    $response->assertSee('Perumahan Terpilih')
        ->assertSee('Perumahan Lain')
        ->assertSee('Blok A / Kavling 01')
        ->assertSee('Blok B / Kavling 02')
        ->assertSee('Rp 350.000.000')
        ->assertSee('Rp 450.000.000')
        ->assertSee('value="'.$lot->id.'" data-property-id="'.$property->id.'"', false)
        ->assertSee('value="'.$otherLot->id.'" data-property-id="'.$otherProperty->id.'"', false)
        ->assertViewHas('housePrices', fn ($housePrices): bool => $housePrices->contains('id', $lot->id))
        ->assertViewHas('totalHousePrice', fn (int|float|string $total): bool => (float) $total === 800000000.0);
});

test('construction finance displays RAB expenses and remaining budget summary for selected filters', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Ringkasan']);
    $otherProperty = Property::create(['name' => 'Perumahan Lain']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $otherBlock = Block::create([
        'property_id' => $otherProperty->id,
        'name' => 'B',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
    ]);
    $otherLot = Lot::create([
        'block_id' => $otherBlock->id,
        'lot_number' => '02',
        'house_price' => 450000000,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Project',
        'category' => 'Infrastruktur',
        'amount' => 1000,
        'created_by' => $user->id,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Kavling',
        'category' => 'Struktur',
        'amount' => 2000,
        'created_by' => $user->id,
    ]);
    Budget::create([
        'property_id' => $otherProperty->id,
        'lot_id' => $otherLot->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Project Lain',
        'category' => 'Struktur',
        'amount' => 9000,
        'created_by' => $user->id,
    ]);
    ExpenseTransaction::create([
        'property_id' => $property->id,
        'division' => 'pembangunan',
        'transaction_number' => 'EXP-SUMMARY-PROJECT',
        'transaction_date' => '2026-10-12',
        'category' => 'Infrastruktur',
        'amount' => 250,
        'created_by' => $user->id,
    ]);
    ExpenseTransaction::create([
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'division' => 'pembangunan',
        'transaction_number' => 'EXP-SUMMARY-LOT',
        'transaction_date' => '2026-10-12',
        'category' => 'Struktur',
        'amount' => 500,
        'created_by' => $user->id,
    ]);
    ExpenseTransaction::create([
        'property_id' => $otherProperty->id,
        'lot_id' => $otherLot->id,
        'division' => 'pembangunan',
        'transaction_number' => 'EXP-SUMMARY-OTHER',
        'transaction_date' => '2026-10-12',
        'category' => 'Struktur',
        'amount' => 750,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('finance.index', [
        'division' => 'pembangunan',
        'property_id' => $property->id,
        'lot_id' => $lot->id,
    ]));

    $response->assertSee('Total RAB Pembangunan')
        ->assertSee('Total Pengeluaran Pembangunan')
        ->assertSee('Deviasi Anggaran (RAB - Pengeluaran)')
        ->assertSee('Rp 3.000')
        ->assertSee('Rp 750')
        ->assertSee('Rp 2.250')
        ->assertViewHas('totalBudget', fn (int|float|string $total): bool => (float) $total === 3000.0)
        ->assertViewHas('totalExpense', fn (int|float|string $total): bool => (float) $total === 750.0)
        ->assertViewHas('totalConstructionDeviation', fn (int|float|string $total): bool => (float) $total === 2250.0);
});

test('construction finance shows RAB expenses and deviation per category and project', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $firstProperty = Property::create(['name' => 'Project Anggrek']);
    $secondProperty = Property::create(['name' => 'Project Melati']);
    Budget::create([
        'property_id' => $firstProperty->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Anggrek',
        'category' => 'Infrastruktur',
        'amount' => 1000,
        'created_by' => $user->id,
    ]);
    Budget::create([
        'property_id' => $secondProperty->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Melati',
        'category' => 'Infrastruktur',
        'amount' => 2000,
        'created_by' => $user->id,
    ]);
    Budget::create([
        'property_id' => $firstProperty->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Struktur Anggrek',
        'category' => 'Struktur',
        'amount' => 2000,
        'created_by' => $user->id,
    ]);
    ExpenseTransaction::create([
        'property_id' => $firstProperty->id,
        'division' => 'pembangunan',
        'transaction_number' => 'EXP-PROJECT-ANGGREK',
        'transaction_date' => '2026-10-12',
        'category' => 'Infrastruktur',
        'amount' => 300,
        'created_by' => $user->id,
    ]);
    ExpenseTransaction::create([
        'property_id' => $secondProperty->id,
        'division' => 'pembangunan',
        'transaction_number' => 'EXP-PROJECT-MELATI',
        'transaction_date' => '2026-10-12',
        'category' => 'Infrastruktur',
        'amount' => 2500,
        'created_by' => $user->id,
    ]);
    ExpenseTransaction::create([
        'property_id' => $firstProperty->id,
        'division' => 'pembangunan',
        'transaction_number' => 'EXP-STRUKTUR-ANGGREK',
        'transaction_date' => '2026-10-12',
        'category' => 'Struktur',
        'amount' => 2500,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('finance.index', ['division' => 'pembangunan']));

    $response->assertSee('Rincian RAB & Pengeluaran per Kategori')
        ->assertSee('Project Anggrek')
        ->assertSee('Project Melati')
        ->assertSee('Infrastruktur')
        ->assertSee('Struktur')
        ->assertSee('Rp 1.000')
        ->assertSee('Rp 300')
        ->assertSee('Rp 700')
        ->assertSee('Sisa anggaran')
        ->assertSee('Rp 2.000')
        ->assertSee('Rp 2.500')
        ->assertSee('Rp -500')
        ->assertSee('Melebihi RAB')
        ->assertViewHas('constructionCategorySummaries', fn ($summaries): bool => $summaries->count() === 3
            && $summaries->first(fn (array $summary): bool => $summary['property']->name === 'Project Anggrek'
                && $summary['category'] === 'Infrastruktur')['deviation'] === 700.0
            && $summaries->first(fn (array $summary): bool => $summary['property']->name === 'Project Anggrek'
                && $summary['category'] === 'Struktur')['deviation'] === -500.0
            && $summaries->first(fn (array $summary): bool => $summary['property']->name === 'Project Melati'
                && $summary['category'] === 'Infrastruktur')['deviation'] === -500.0);
});

test('marketing finance displays admin-created RAB realization for selected property', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Promo']);
    $otherProperty = Property::create(['name' => 'Perumahan Lain']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 765432100,
    ]);

    $budget = Budget::create([
        'property_id' => $property->id,
        'purpose' => 'marketing',
        'name' => 'RAB Promo Marketing',
        'category' => 'Promosi',
        'amount' => 1000,
        'created_by' => $user->id,
    ]);
    ExpenseTransaction::create([
        'property_id' => $property->id,
        'division' => 'marketing',
        'budget_id' => $budget->id,
        'transaction_number' => 'EXP-RAB-MARKETING-001',
        'transaction_date' => '2026-10-12',
        'category' => 'Promosi',
        'amount' => 250,
        'created_by' => $user->id,
    ]);
    ExpenseTransaction::create([
        'property_id' => $property->id,
        'division' => 'marketing',
        'transaction_number' => 'EXP-MARKETING-MATERIAL-001',
        'transaction_date' => '2026-10-12',
        'category' => 'Material',
        'amount' => 500,
        'created_by' => $user->id,
    ]);
    Budget::create([
        'property_id' => $otherProperty->id,
        'purpose' => 'marketing',
        'name' => 'RAB Promo Lain',
        'category' => 'Promosi',
        'amount' => 9000,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('admin.budgets.index'))
        ->assertSee('RAB Promo Marketing');
    $this->get(route('finance.index', [
        'division' => 'marketing',
        'property_id' => $property->id,
    ]))
        ->assertSee('RAB Promo Marketing')
        ->assertDontSee('RAB Promo Lain')
        ->assertSee('Rp 1.000')
        ->assertSee('Rp 250')
        ->assertSee('Rp 750')
        ->assertDontSee('Material')
        ->assertSee('Total RAB Marketing')
        ->assertSee('Total Pengeluaran Marketing')
        ->assertSee('Deviasi Anggaran (RAB - Pengeluaran)')
        ->assertSee('dark:!text-white')
        ->assertDontSee('Daftar Harga Kavling')
        ->assertDontSee('Total Harga Kavling Terfilter')
        ->assertDontSee('Rp 765.432.100')
        ->assertViewHas('totalMarketingBudget', fn (int|float|string $total): bool => (float) $total === 1000.0)
        ->assertViewHas('totalExpense', fn (int|float|string $total): bool => (float) $total === 250.0)
        ->assertViewHas('totalMarketingDeviation', fn (float $total): bool => $total === 750.0)
        ->assertDontSee('RAB Pembangunan dari Admin');
});

test('construction finance displays only admin construction RAB matching the selected lot', function () {
    $admin = User::factory()->create(['name' => 'Admin Keuangan', 'role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan RAB Pembangunan']);
    $otherProperty = Property::create(['name' => 'Perumahan RAB Lain']);
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
        'name' => 'RAB Pembangunan Project',
        'category' => 'Infrastruktur',
        'purpose' => 'pembangunan',
        'amount' => 15000,
        'created_by' => $admin->id,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'name' => 'RAB Pembangunan Kavling Terpilih',
        'category' => 'Struktur',
        'purpose' => 'pembangunan',
        'amount' => 5000,
        'period_start' => '2026-10-01',
        'period_end' => '2026-10-31',
        'notes' => 'Pekerjaan pondasi kavling',
        'created_by' => $admin->id,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'name' => 'RAB Finishing Kavling',
        'category' => 'Finishing',
        'purpose' => 'pembangunan',
        'amount' => 3000,
        'created_by' => $admin->id,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'lot_id' => $otherLot->id,
        'name' => 'RAB Pembangunan Kavling Lain',
        'category' => 'Struktur',
        'purpose' => 'pembangunan',
        'amount' => 9000,
        'created_by' => $admin->id,
    ]);
    Budget::create([
        'property_id' => $otherProperty->id,
        'lot_id' => $otherPropertyLot->id,
        'name' => 'RAB Pembangunan Project Lain',
        'category' => 'Struktur',
        'purpose' => 'pembangunan',
        'amount' => 12000,
        'created_by' => $admin->id,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'name' => 'RAB Promo Marketing',
        'category' => 'Promosi',
        'purpose' => 'marketing',
        'amount' => 25000,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('finance.index', [
            'division' => 'pembangunan',
            'property_id' => $property->id,
            'lot_id' => $lot->id,
        ]))
        ->assertSee('RAB Pembangunan dari Admin')
        ->assertSee('Detail RAB')
        ->assertSee('Detail')
        ->assertSee('RAB Pembangunan Project')
        ->assertSee('RAB Pembangunan Kavling Terpilih')
        ->assertSee('Harga rumah')
        ->assertSee('Rincian RAB kavling')
        ->assertSee('RAB Finishing Kavling')
        ->assertSee('Kategori: Finishing')
        ->assertSee('Total RAB kavling')
        ->assertSee('Harga rumah + total RAB')
        ->assertSee('Rp 350.008.000')
        ->assertSee('Kategori: Struktur')
        ->assertSee('01/10/2026')
        ->assertSee('31/10/2026')
        ->assertSee('Pekerjaan pondasi kavling')
        ->assertSee('Admin Keuangan')
        ->assertDontSee('RAB Pembangunan Kavling Lain')
        ->assertDontSee('RAB Pembangunan Project Lain')
        ->assertDontSee('RAB Promo Marketing')
        ->assertSee('Total RAB: Rp 23.000');
});

test('construction finance alerts when an RAB period has passed its end date', function () {
    $this->travelTo(Carbon::parse('2026-10-12 14:00:00'));
    $admin = User::factory()->create(['role' => 'admin']);
    Budget::create([
        'name' => 'RAB Deadline Terlewati',
        'category' => 'Infrastruktur',
        'purpose' => 'pembangunan',
        'amount' => 10000,
        'period_end' => '2026-10-11',
        'created_by' => $admin->id,
    ]);
    Budget::create([
        'name' => 'RAB Deadline Hari Ini',
        'category' => 'Infrastruktur',
        'purpose' => 'pembangunan',
        'amount' => 12000,
        'period_end' => '2026-10-12',
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->get(route('finance.index', ['division' => 'pembangunan']));

    $response->assertSee('RAB Deadline Terlewati')
        ->assertSee('Deadline RAB terlewati')
        ->assertSee('Periode RAB sudah berakhir pada 11/10/2026.')
        ->assertSee('RAB Deadline Hari Ini')
        ->assertSee('Deadline: 12/10/2026');
});

test('construction finance ignores another division filter and shows only its expenses', function () {
    $division = Division::firstOrCreate(
        ['slug' => 'pembangunan'],
        ['name' => 'Pembangunan', 'is_active' => true]
    );
    $user = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Divisi']);
    ExpenseTransaction::create([
        'property_id' => $property->id,
        'division' => 'marketing',
        'transaction_number' => 'EXP-DIVISION-MARKETING',
        'transaction_date' => '2026-10-01',
        'category' => 'Promo Marketing',
        'amount' => 100,
    ]);
    ExpenseTransaction::create([
        'property_id' => $property->id,
        'division' => 'pembangunan',
        'transaction_number' => 'EXP-DIVISION-CONSTRUCTION',
        'transaction_date' => '2026-10-02',
        'category' => 'Material Konstruksi',
        'amount' => 200,
    ]);

    $this->actingAs($user)
        ->get(route('finance.index', [
            'division' => 'marketing',
            'property_id' => $property->id,
        ]))
        ->assertSee('Material Konstruksi')
        ->assertDontSee('Promo Marketing')
        ->assertViewHas('division', 'pembangunan')
        ->assertViewHas('totalExpense', fn (int|float|string $total): bool => (float) $total === 200.0);
});

test('expense creation renders the selected budget and transaction fields only', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Form']);
    $marketingBudget = Budget::create([
        'property_id' => $property->id,
        'purpose' => 'marketing',
        'name' => 'RAB Promo',
        'category' => 'Promo',
        'amount' => 5000,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('finance.expense-transactions.create'))
        ->assertSee('data-expense-budget-select', false)
        ->assertSee('value="'.$marketingBudget->id.'"', false)
        ->assertSee('name="transaction_date"', false)
        ->assertSee('name="amount"', false)
        ->assertSee('name="description"', false)
        ->assertDontSee('name="property_id"', false)
        ->assertDontSee('name="lot_id"', false)
        ->assertDontSee('name="facility_id"', false)
        ->assertDontSee('name="category"', false);
});

test('marketing expenses require a selected admin-created marketing RAB', function () {
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $this->actingAs($user)
        ->post(route('finance.expense-transactions.store'), [
            'transaction_date' => '2026-10-12',
            'amount' => 100000,
        ])
        ->assertSessionHasErrors([
            'marketing_budget_id' => 'The marketing budget id field is required.',
        ]);

    expect(ExpenseTransaction::query()->exists())->toBeFalse();
});

test('construction expense form lists admin RAB with its target and category', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Form Pembangunan']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'B',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '02',
        'house_price' => 350000000,
    ]);
    $budget = Budget::create([
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Struktur Kavling',
        'category' => 'Struktur',
        'amount' => 25000000,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('finance.expense-transactions.create', ['division' => 'pembangunan']))
        ->assertSee('name="construction_budget_id"', false)
        ->assertSee('data-expense-budget-select', false)
        ->assertSee('value="'.$budget->id.'"', false)
        ->assertSee('RAB Struktur Kavling')
        ->assertSee('Rp 25.000.000')
        ->assertSee('name="transaction_date"', false)
        ->assertSee('name="amount"', false)
        ->assertSee('name="description"', false)
        ->assertDontSee('name="property_id"', false)
        ->assertDontSee('name="lot_id"', false)
        ->assertDontSee('name="facility_id"', false)
        ->assertDontSee('name="category"', false);
});

test('construction expenses require selecting an admin-created construction RAB', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan RAB Wajib']);
    Budget::create([
        'property_id' => $property->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Infrastruktur Project',
        'category' => 'Infrastruktur',
        'amount' => 5000000,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('finance.expense-transactions.store'), [
            'division' => 'pembangunan',
            'transaction_date' => '2026-10-12',
            'amount' => 1000000,
        ])
        ->assertSessionHasErrors([
            'construction_budget_id' => 'Pilih RAB Pembangunan yang akan digunakan.',
        ]);

    expect(ExpenseTransaction::query()->exists())->toBeFalse();
});

test('marketing expenses reject a material category even when a marketing RAB exists', function () {
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Kategori Material']);
    $budget = Budget::create([
        'property_id' => $property->id,
        'purpose' => 'marketing',
        'name' => 'RAB Material Keliru',
        'category' => 'Material',
        'amount' => 5000000,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('finance.expense-transactions.store'), [
            'marketing_budget_id' => $budget->id,
            'transaction_date' => '2026-10-12',
            'amount' => 1000000,
        ])
        ->assertSessionHasErrors([
            'marketing_budget_id' => 'Kategori material dicatat pada Keuangan Pembangunan, bukan Marketing.',
        ]);

    expect(ExpenseTransaction::query()->exists())->toBeFalse();
});

test('marketing expense accepts a category from the selected lot marketing RAB', function () {
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Promo Kavling']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
    ]);
    $marketingBudget = Budget::create([
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'purpose' => 'marketing',
        'name' => 'Promo Kavling A-01',
        'category' => 'Promo Khusus',
        'amount' => 5000000,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('finance.expense-transactions.store'), [
            'marketing_budget_id' => $marketingBudget->id,
            'transaction_date' => '2026-10-12',
            'amount' => 1000000,
        ])
        ->assertRedirect(route('finance.index', ['division' => 'marketing']))
        ->assertSessionDoesntHaveErrors();

    $this->assertDatabaseHas('expense_transactions', [
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'division' => 'marketing',
        'budget_id' => $marketingBudget->id,
        'category' => 'Promo Khusus',
        'amount' => 1000000,
    ]);
});

test('construction expense choices show each admin RAB target and category', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Kategori']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
    ]);
    $otherLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '02',
        'house_price' => 350000000,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Struktur',
        'category' => 'Struktur',
        'amount' => 5000,
        'created_by' => $user->id,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'lot_id' => $otherLot->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Finishing',
        'category' => 'Finishing',
        'amount' => 3000,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('finance.expense-transactions.create', ['division' => 'pembangunan']))
        ->assertSee('RAB Struktur — Struktur · Perumahan Kategori · Kavling A/01')
        ->assertSee('RAB Finishing — Finishing · Perumahan Kategori · Kavling A/02');
});

test('construction expense records project and lot from selected RAB', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Pengeluaran Pembangunan']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
    ]);
    $budget = Budget::create([
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Pekerjaan Pondasi',
        'category' => 'Pondasi',
        'amount' => 5000000,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(route('finance.expense-transactions.store'), [
        'division' => 'pembangunan',
        'construction_budget_id' => $budget->id,
        'transaction_date' => '2026-10-12',
        'amount' => 1000000,
    ]);

    $response->assertRedirect(route('finance.index', ['division' => 'pembangunan']));
    $this->assertDatabaseHas('expense_transactions', [
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'division' => 'pembangunan',
        'budget_id' => $budget->id,
        'category' => 'Pondasi',
        'amount' => 1000000,
    ]);
});

test('construction expense uses the selected RAB rather than client-supplied target fields', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Batas Kategori']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $selectedLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
    ]);
    $otherLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '02',
        'house_price' => 350000000,
    ]);
    $budget = Budget::create([
        'property_id' => $property->id,
        'lot_id' => $otherLot->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Finishing Kavling Lain',
        'category' => 'Finishing',
        'amount' => 5000000,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(route('finance.expense-transactions.store'), [
        'division' => 'pembangunan',
        'property_id' => $property->id,
        'lot_id' => $selectedLot->id,
        'construction_budget_id' => $budget->id,
        'transaction_date' => '2026-10-12',
        'category' => 'Incorrect category',
        'amount' => 1000000,
    ]);

    $response->assertRedirect(route('finance.index', ['division' => 'pembangunan']));
    $this->assertDatabaseHas('expense_transactions', [
        'property_id' => $property->id,
        'lot_id' => $otherLot->id,
        'budget_id' => $budget->id,
        'division' => 'pembangunan',
        'category' => 'Finishing',
    ]);
});

test('expense editing shows the selected RAB and transaction fields only', function () {
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
    $constructionBudget = Budget::create([
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Kategori Marketing',
        'category' => 'Material',
        'amount' => 5000,
        'created_by' => $user->id,
    ]);
    $expense = ExpenseTransaction::create([
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'division' => 'pembangunan',
        'transaction_number' => 'EXP-FORM-001',
        'transaction_date' => '2026-10-07',
        'category' => 'Material',
        'amount' => 1000,
        'budget_id' => $constructionBudget->id,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)->get(route('finance.expense-transactions.edit', $expense))
        ->assertSee('name="construction_budget_id"', false)
        ->assertSee('value="'.$constructionBudget->id.'"', false)
        ->assertSee('name="transaction_date"', false)
        ->assertSee('name="amount"', false)
        ->assertSee('name="description"', false)
        ->assertDontSee('name="property_id"', false)
        ->assertDontSee('name="lot_id"', false)
        ->assertDontSee('name="facility_id"', false)
        ->assertDontSee('name="category"', false);
});

test('expense creation does not accept a RAB field for a different division', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)->post(route('finance.expense-transactions.store'), [
        'division' => 'pembangunan',
        'marketing_budget_id' => 999,
        'transaction_date' => '2026-10-07',
        'amount' => 1000,
    ]);

    $response->assertSessionHasErrors([
        'marketing_budget_id' => 'The marketing budget id field is prohibited.',
        'construction_budget_id' => 'Pilih RAB Pembangunan yang akan digunakan.',
    ]);
    expect(ExpenseTransaction::query()->exists())->toBeFalse();
});

test('construction expense creation allows assigning a material expense to a lot', function () {
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
    $budget = Budget::create([
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Material Kavling',
        'category' => 'Material Kavling',
        'amount' => 5000,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(route('finance.expense-transactions.store'), [
        'division' => 'pembangunan',
        'construction_budget_id' => $budget->id,
        'transaction_date' => '2026-10-07',
        'amount' => 1000,
    ]);

    $response->assertRedirect(route('finance.index', ['division' => 'pembangunan']));
    $this->assertDatabaseHas('expense_transactions', [
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'facility_id' => null,
        'division' => 'pembangunan',
        'budget_id' => $budget->id,
        'category' => 'Material Kavling',
    ]);
});

test('construction expense creation allows assigning a material expense to a facility', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Pengeluaran']);
    $facility = CommonFacility::create([
        'property_id' => $property->id,
        'name' => 'Taman',
        'created_by' => $user->id,
    ]);
    $budget = Budget::create([
        'property_id' => $property->id,
        'facility_id' => $facility->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Material Fasilitas',
        'category' => 'Material Fasilitas',
        'amount' => 5000,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(route('finance.expense-transactions.store'), [
        'division' => 'pembangunan',
        'construction_budget_id' => $budget->id,
        'transaction_date' => '2026-10-07',
        'amount' => 1000,
    ]);

    $response->assertRedirect(route('finance.index', ['division' => 'pembangunan']));
    $this->assertDatabaseHas('expense_transactions', [
        'property_id' => $property->id,
        'lot_id' => null,
        'facility_id' => $facility->id,
        'division' => 'pembangunan',
        'budget_id' => $budget->id,
        'category' => 'Material Fasilitas',
    ]);
});

test('expense update derives its project, target, and category from the selected RAB', function () {
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
    $budget = Budget::create([
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Baru',
        'category' => 'Struktur',
        'amount' => 5000,
        'created_by' => $user->id,
    ]);
    $expense = ExpenseTransaction::create([
        'property_id' => $property->id,
        'transaction_number' => 'EXP-UPDATE-001',
        'transaction_date' => '2026-10-06',
        'category' => 'Lama',
        'amount' => 1000,
        'division' => 'pembangunan',
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->put(route('finance.expense-transactions.update', $expense), [
        'division' => 'pembangunan',
        'construction_budget_id' => $budget->id,
        'lot_id' => $lot->id,
        'transaction_date' => '2026-10-07',
        'category' => 'Forged category',
        'amount' => 2000,
    ]);

    $response->assertRedirect(route('finance.index', ['division' => 'pembangunan']));
    $this->assertDatabaseHas('expense_transactions', [
        'id' => $expense->id,
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'budget_id' => $budget->id,
        'category' => 'Struktur',
        'amount' => 2000,
    ]);
});
