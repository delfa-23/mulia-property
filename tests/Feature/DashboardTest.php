<?php

use App\Models\BankProcess;
use App\Models\Block;
use App\Models\Booking;
use App\Models\Budget;
use App\Models\CommonFacility;
use App\Models\ConstructionProgress;
use App\Models\ConstructionStage;
use App\Models\Customer;
use App\Models\Division;
use App\Models\DocumentProcess;
use App\Models\ExpenseTransaction;
use App\Models\IncomeTransaction;
use App\Models\Lot;
use App\Models\Property;
use App\Models\Sp3;
use App\Models\User;
use Illuminate\Support\Collection;

test('guests are redirected to the login page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
});

test('users can only visit their own role dashboard', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Admin Dashboard');

    $this->actingAs($user)
        ->get(route('tl.marketing.dashboard'))
        ->assertForbidden();
});

test('admin sidebar only shows the admin common facilities link', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertSee(route('admin.common-facilities.index'), false)
        ->assertDontSee('/pembangunan/common-facilities', false);
});

test('admin sidebar shows progress report under each division', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    expect(substr_count($response->getContent(), 'Progress Report'))->toBe(3);
});

test('admin sidebar places separate RAB links in their divisions', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));
    $content = $response->getContent();

    $constructionRab = route('admin.budgets.index', ['purpose' => 'pembangunan']);
    $marketingRab = route('admin.budgets.index', ['purpose' => 'marketing']);

    $response->assertSee('RAB Pembangunan')
        ->assertSee($constructionRab, false)
        ->assertSee('RAB Marketing')
        ->assertSee($marketingRab, false)
        ->assertDontSee('href="'.route('admin.budgets.index').'"', false);

    expect(strpos($content, 'Pembangunan'))
        ->toBeLessThan(strpos($content, $constructionRab))
        ->and(strpos($content, 'Marketing &amp; Keuangan'))
        ->toBeLessThan(strpos($content, $marketingRab));
});

test('non-admin users do not see admin RAB management links in the sidebar', function () {
    $marketingUser = User::factory()->create([
        'role' => 'staff_marketing',
    ]);

    $response = $this->actingAs($marketingUser)->get(route('marketing.dashboard'));

    $response->assertDontSee('RAB Pembangunan')
        ->assertDontSee('RAB Marketing')
        ->assertDontSee(route('admin.budgets.index', ['purpose' => 'pembangunan']), false)
        ->assertDontSee(route('admin.budgets.index', ['purpose' => 'marketing']), false);
});

test('admin dashboard filters property block and lot statistics', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Utama']);
    $otherProperty = Property::create(['name' => 'Perumahan Lain']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $otherBlock = Block::create(['property_id' => $otherProperty->id, 'name' => 'B']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'available',
    ]);
    Lot::create([
        'block_id' => $block->id,
        'lot_number' => '02',
        'house_price' => 500000000,
        'status' => 'booked',
    ]);
    Lot::create([
        'block_id' => $otherBlock->id,
        'lot_number' => '03',
        'house_price' => 600000000,
        'status' => 'finish',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.dashboard', [
            'property_id' => $property->id,
            'block_id' => $block->id,
            'lot_id' => $lot->id,
        ]));

    $response->assertOk()
        ->assertViewHas('stats', fn (array $stats): bool => $stats['total_properties'] === 1
            && $stats['total_blocks'] === 1
            && $stats['total_lots'] === 1
            && $stats['available_lots'] === 1
            && $stats['booked_lots'] === 0
            && $stats['finish_lots'] === 0)
        ->assertViewHas('propertyId', $property->id)
        ->assertViewHas('blockId', $block->id)
        ->assertViewHas('lotId', $lot->id);
});

test('admin dashboard charts show only the selected lot status and financial totals', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Grafik']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $selectedLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'available',
    ]);
    $otherLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '02',
        'house_price' => 500000000,
        'status' => 'booked',
    ]);
    $monthStart = now()->startOfMonth()->toDateString();

    IncomeTransaction::create([
        'property_id' => $property->id,
        'lot_id' => $selectedLot->id,
        'transaction_number' => 'INC-GRAPH-01',
        'transaction_date' => $monthStart,
        'category' => 'Booking Fee',
        'amount' => 1200000,
    ]);
    IncomeTransaction::create([
        'property_id' => $property->id,
        'lot_id' => $otherLot->id,
        'transaction_number' => 'INC-GRAPH-02',
        'transaction_date' => $monthStart,
        'category' => 'Booking Fee',
        'amount' => 9000000,
    ]);
    ExpenseTransaction::create([
        'property_id' => $property->id,
        'lot_id' => $selectedLot->id,
        'transaction_number' => 'EXP-GRAPH-01',
        'transaction_date' => $monthStart,
        'category' => 'Material',
        'amount' => 300000,
    ]);
    ExpenseTransaction::create([
        'property_id' => $property->id,
        'lot_id' => $otherLot->id,
        'transaction_number' => 'EXP-GRAPH-02',
        'transaction_date' => $monthStart,
        'category' => 'Material',
        'amount' => 5000000,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.dashboard', ['lot_id' => $selectedLot->id]));

    $response->assertOk()
        ->assertViewHas('lotStatusChart', function (Collection $chart): bool {
            return (int) data_get($chart->firstWhere('status', 'available'), 'value') === 1
                && (int) data_get($chart->firstWhere('status', 'booked'), 'value') === 0;
        })
        ->assertViewHas('monthlyFinancialChart', function (Collection $chart): bool {
            $currentMonth = collect($chart)->firstWhere('key', now()->format('Y-m'));

            return $currentMonth !== null
                && (float) data_get($currentMonth, 'income') === 1200000.0
                && (float) data_get($currentMonth, 'expense') === 300000.0;
        });
});

test('admin dashboard averages weighted construction progress within selected filters', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Progress']);
    $otherProperty = Property::create(['name' => 'Perumahan Lain']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $otherBlock = Block::create(['property_id' => $otherProperty->id, 'name' => 'B']);
    $firstLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'available',
    ]);
    $secondLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '02',
        'house_price' => 500000000,
        'status' => 'available',
    ]);
    Lot::create([
        'block_id' => $block->id,
        'lot_number' => '04',
        'house_price' => 500000000,
        'status' => 'available',
    ]);
    $otherLot = Lot::create([
        'block_id' => $otherBlock->id,
        'lot_number' => '03',
        'house_price' => 500000000,
        'status' => 'available',
    ]);
    $stages = collect([
        ['name' => 'Tahap 1', 'max_progress' => 20],
        ['name' => 'Tahap 2', 'max_progress' => 40],
        ['name' => 'Tahap 3', 'max_progress' => 30],
        ['name' => 'Tahap 4', 'max_progress' => 10],
    ])->map(fn (array $attributes, int $order): ConstructionStage => ConstructionStage::create([
        ...$attributes,
        'order' => $order + 1,
        'is_active' => true,
    ]));

    ConstructionProgress::create([
        'lot_id' => $firstLot->id,
        'stage_id' => $stages[0]->id,
        'progress' => 12,
        'updated_by' => $admin->id,
    ]);
    ConstructionProgress::create([
        'lot_id' => $firstLot->id,
        'stage_id' => $stages[1]->id,
        'progress' => 45,
        'updated_by' => $admin->id,
    ]);
    ConstructionProgress::create([
        'lot_id' => $secondLot->id,
        'stage_id' => $stages[2]->id,
        'progress' => 8,
        'updated_by' => $admin->id,
    ]);
    ConstructionProgress::create([
        'lot_id' => $secondLot->id,
        'stage_id' => null,
        'progress' => 12,
        'updated_by' => $admin->id,
    ]);
    ConstructionProgress::create([
        'lot_id' => $otherLot->id,
        'stage_id' => $stages[3]->id,
        'progress' => 99,
        'updated_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.dashboard', [
        'property_id' => $property->id,
    ]));

    $response->assertViewHas('stats', fn (array $stats): bool => $stats['physical_progress'] === 7.6);
});

test('construction dashboard filters lot progress statistics by property and block', function () {
    $division = Division::create([
        'name' => 'Pembangunan',
        'slug' => 'pembangunan',
        'is_active' => true,
    ]);
    $constructionUser = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Pembangunan']);
    $otherProperty = Property::create(['name' => 'Perumahan Lain']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $otherBlock = Block::create(['property_id' => $otherProperty->id, 'name' => 'B']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'available',
    ]);
    Lot::create([
        'block_id' => $otherBlock->id,
        'lot_number' => '02',
        'house_price' => 500000000,
        'status' => 'finish',
    ]);

    $response = $this->actingAs($constructionUser)
        ->get(route('pembangunan.dashboard', ['block_id' => $block->id, 'lot_id' => $lot->id]));

    $response->assertOk()
        ->assertSee('Tahapan Pembangunan')
        ->assertSee('Fasilitas Umum')
        ->assertSee('Denah Perumahan')
        ->assertViewHas('stats', fn (array $stats): bool => $stats['total_properties'] === 1
            && $stats['total_blocks'] === 1
            && $stats['total_lots'] === 1
            && $stats['available_lots'] === 1
            && $stats['finish_lots'] === 0);

    $response->assertViewHas('lotStatusChart', fn (Collection $chart): bool => (int) data_get($chart->firstWhere('status', 'available'), 'value') === 1
        && (int) data_get($chart->firstWhere('status', 'finish'), 'value') === 0
        && $chart->firstWhere('status', 'blocked') === null)
        ->assertDontSee('Tren Progress Fisik');
});

test('construction dashboard summarizes filtered finances and booking handover progress', function () {
    $division = Division::firstOrCreate(
        ['slug' => 'pembangunan'],
        ['name' => 'Pembangunan', 'is_active' => true]
    );
    $user = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Ringkasan']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $otherBlock = Block::create(['property_id' => $property->id, 'name' => 'B']);
    $customer = Customer::create(['name' => 'Pembeli Dashboard']);
    $createLot = fn (string $number, string $status, ?Block $lotBlock = null): Lot => Lot::create([
        'block_id' => ($lotBlock ?? $block)->id,
        'lot_number' => $number,
        'house_price' => 500000000,
        'status' => $status,
    ]);
    $createBooking = function (Lot $lot, string $status) use ($customer): Booking {
        return Booking::create([
            'lot_id' => $lot->id,
            'customer_id' => $customer->id,
            'booking_date' => '2026-10-01',
            'status' => $status,
        ]);
    };
    $availableLot = $createLot('01', 'available');
    $createLot('02', 'blocked');
    $bookedLot = $createLot('03', 'booked');
    $processLot = $createLot('04', 'process');
    $sp3Lot = $createLot('05', 'process');
    $akadLot = $createLot('06', 'akad');
    $completedLot = $createLot('07', 'finish');
    $incompleteLot = $createLot('08', 'finish');
    $otherBlockLot = $createLot('09', 'available', $otherBlock);

    $createBooking($bookedLot, 'booking');
    $processBooking = $createBooking($processLot, 'process');
    $sp3Booking = $createBooking($sp3Lot, 'process');
    $createBooking($akadLot, 'akad');
    $createBooking($completedLot, 'finish');
    $createBooking($incompleteLot, 'finish');
    Sp3::create([
        'booking_id' => $processBooking->id,
        'status' => 'pending',
    ]);
    Sp3::create([
        'booking_id' => $sp3Booking->id,
        'status' => 'issued',
    ]);

    $firstStage = $property->constructionStages()->create([
        'name' => 'Pekerjaan Struktur',
        'order' => 1,
        'min_progress' => 0,
        'max_progress' => 100,
    ]);
    $secondStage = $property->constructionStages()->create([
        'name' => 'Pekerjaan Finishing',
        'order' => 2,
        'min_progress' => 0,
        'max_progress' => 100,
    ]);
    foreach ([$firstStage, $secondStage] as $stage) {
        ConstructionProgress::create([
            'lot_id' => $completedLot->id,
            'stage_id' => $stage->id,
            'progress' => 100,
            'updated_by' => $user->id,
        ]);
    }
    ConstructionProgress::create([
        'lot_id' => $incompleteLot->id,
        'stage_id' => $firstStage->id,
        'progress' => 100,
        'updated_by' => $user->id,
    ]);

    Budget::create([
        'property_id' => $property->id,
        'lot_id' => $availableLot->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB kavling A-01',
        'category' => 'Struktur',
        'amount' => 1000,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'name' => 'RAB umum',
        'category' => 'Infrastruktur',
        'purpose' => 'pembangunan',
        'amount' => 250,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'lot_id' => $otherBlockLot->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB block lain',
        'category' => 'Struktur',
        'amount' => 9000,
    ]);
    ExpenseTransaction::create([
        'property_id' => $property->id,
        'lot_id' => $availableLot->id,
        'division' => 'pembangunan',
        'transaction_number' => 'EXP-DASHBOARD-LOT',
        'transaction_date' => '2026-10-01',
        'category' => 'Struktur',
        'amount' => 300,
    ]);
    ExpenseTransaction::create([
        'property_id' => $property->id,
        'division' => 'pembangunan',
        'transaction_number' => 'EXP-DASHBOARD-GENERAL',
        'transaction_date' => '2026-10-01',
        'category' => 'Infrastruktur',
        'amount' => 50,
    ]);
    ExpenseTransaction::create([
        'property_id' => $property->id,
        'lot_id' => $otherBlockLot->id,
        'division' => 'pembangunan',
        'transaction_number' => 'EXP-DASHBOARD-OTHER-BLOCK',
        'transaction_date' => '2026-10-01',
        'category' => 'Struktur',
        'amount' => 9000,
    ]);

    $response = $this->actingAs($user)
        ->get(route('pembangunan.dashboard', ['block_id' => $block->id]));

    $response->assertSee('RAB Pembangunan')
        ->assertSee('Total Pengeluaran')
        ->assertSee('Deviasi Anggaran')
        ->assertSee('Tahapan Pembangunan')
        ->assertSee('Fasilitas Umum')
        ->assertSee('Denah Perumahan')
        ->assertSee('Rp 1.250')
        ->assertSee('Rp 350')
        ->assertSee('Rp 900')
        ->assertSee('SP3')
        ->assertSee('Serah Terima Rumah')
        ->assertViewHas('stats', fn (array $stats): bool => $stats['total_lots'] === 8
            && $stats['booked_lots'] === 2
            && $stats['process_lots'] === 1
            && $stats['sp3_lots'] === 1
            && $stats['akad_lots'] === 2
            && $stats['finish_lots'] === 1)
        ->assertViewHas('lotStatusChart', fn (Collection $chart): bool => (int) data_get($chart->firstWhere('status', 'sp3'), 'value') === 1
            && (int) data_get($chart->firstWhere('status', 'finish'), 'value') === 1
            && (int) data_get($chart->firstWhere('status', 'akad'), 'value') === 2
            && $chart->pluck('status')->all() === ['available', 'booked', 'process', 'sp3', 'akad', 'finish', 'cancelled']
            && $chart->firstWhere('status', 'blocked') === null)
        ->assertViewHas('totalBudget', fn (int|float|string $total): bool => (float) $total === 1250.0)
        ->assertViewHas('totalExpense', fn (int|float|string $total): bool => (float) $total === 350.0)
        ->assertViewHas('totalBudgetDeviation', fn (int|float|string $total): bool => (float) $total === 900.0);
});

test('construction dashboard totals RAB and facility budgets for the selected property', function () {
    $division = Division::firstOrCreate(
        ['slug' => 'pembangunan'],
        ['name' => 'Pembangunan', 'is_active' => true]
    );
    $user = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Anggaran']);
    $otherProperty = Property::create(['name' => 'Perumahan Lain']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);

    foreach (range(1, 32) as $number) {
        $lot = Lot::create([
            'block_id' => $block->id,
            'lot_number' => sprintf('D-%02d', $number),
            'house_price' => 500000000,
            'status' => 'available',
        ]);
        Budget::create([
            'property_id' => $property->id,
            'lot_id' => $lot->id,
            'name' => 'RAB Couple',
            'category' => 'Bangunan Rumah',
            'purpose' => 'pembangunan',
            'amount' => 78000000,
        ]);
    }

    foreach (range(1, 98) as $number) {
        $lot = Lot::create([
            'block_id' => $block->id,
            'lot_number' => sprintf('C-%03d', $number),
            'house_price' => 500000000,
            'status' => 'available',
        ]);
        Budget::create([
            'property_id' => $property->id,
            'lot_id' => $lot->id,
            'name' => 'RAB Deret',
            'category' => 'Bangunan Rumah',
            'purpose' => 'pembangunan',
            'amount' => 75000000,
        ]);
    }

    CommonFacility::create([
        'property_id' => $property->id,
        'name' => 'Jalan Komplek',
        'budget' => 200000000,
    ]);
    CommonFacility::create([
        'property_id' => $otherProperty->id,
        'name' => 'Jalan Komplek Perumahan Lain',
        'budget' => 900000000,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'name' => 'RAB Marketing',
        'category' => 'Marketing',
        'purpose' => 'marketing',
        'amount' => 1000000000,
    ]);

    $response = $this->actingAs($user)
        ->get(route('pembangunan.dashboard', ['property_id' => $property->id]));

    $response->assertSee('RAB')
        ->assertSee('Total Pengeluaran')
        ->assertSee('Deviasi')
        ->assertSee('Pembangunan')
        ->assertSee('Fasum')
        ->assertSee('Total')
        ->assertSee('Rp 9.846.000.000')
        ->assertDontSee('estimasi')
        ->assertSee('Rp 200.000.000')
        ->assertViewHas('constructionBudget', fn (int|float|string $total): bool => (float) $total === 9846000000.0)
        ->assertViewHas('totalFacilityBudget', fn (int|float|string $total): bool => (float) $total === 200000000.0)
        ->assertViewHas('totalBudget', fn (int|float|string $total): bool => (float) $total === 10046000000.0)
        ->assertViewHas('totalBudgetDeviation', fn (int|float|string $total): bool => (float) $total === 10046000000.0);
});

test('construction dashboard separates construction and facility budgets and expenses before combining totals', function () {
    $division = Division::firstOrCreate(
        ['slug' => 'pembangunan'],
        ['name' => 'Pembangunan', 'is_active' => true]
    );
    $user = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Ringkasan Keuangan']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $firstLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => 'A-01',
        'house_price' => 500000000,
        'status' => 'available',
    ]);
    $secondLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => 'A-02',
        'house_price' => 500000000,
        'status' => 'available',
    ]);
    $rabFacility = CommonFacility::create([
        'property_id' => $property->id,
        'name' => 'Taman',
        'budget' => 900,
    ]);
    $legacyFacility = CommonFacility::create([
        'property_id' => $property->id,
        'name' => 'Pos Keamanan',
        'budget' => 200,
    ]);

    Budget::create([
        'property_id' => $property->id,
        'lot_id' => $firstLot->id,
        'name' => 'RAB Rumah A-01',
        'category' => 'Bangunan Rumah',
        'purpose' => 'pembangunan',
        'amount' => 1000,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'lot_id' => $secondLot->id,
        'name' => 'RAB Rumah A-02',
        'category' => 'Bangunan Rumah',
        'purpose' => 'pembangunan',
        'amount' => 300,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'facility_id' => $rabFacility->id,
        'name' => 'RAB Taman',
        'category' => 'Lanskap',
        'purpose' => 'pembangunan',
        'amount' => 500,
    ]);
    ExpenseTransaction::create([
        'property_id' => $property->id,
        'lot_id' => $firstLot->id,
        'division' => 'pembangunan',
        'transaction_number' => 'EXP-DASHBOARD-UNIT-1',
        'transaction_date' => '2026-10-01',
        'category' => 'Bangunan Rumah',
        'amount' => 250,
    ]);
    ExpenseTransaction::create([
        'property_id' => $property->id,
        'lot_id' => $secondLot->id,
        'division' => 'pembangunan',
        'transaction_number' => 'EXP-DASHBOARD-UNIT-2',
        'transaction_date' => '2026-10-01',
        'category' => 'Bangunan Rumah',
        'amount' => 150,
    ]);
    ExpenseTransaction::create([
        'property_id' => $property->id,
        'facility_id' => $rabFacility->id,
        'division' => 'pembangunan',
        'transaction_number' => 'EXP-DASHBOARD-FACILITY-1',
        'transaction_date' => '2026-10-01',
        'category' => 'Lanskap',
        'amount' => 100,
    ]);
    ExpenseTransaction::create([
        'property_id' => $property->id,
        'facility_id' => $legacyFacility->id,
        'division' => 'pembangunan',
        'transaction_number' => 'EXP-DASHBOARD-FACILITY-2',
        'transaction_date' => '2026-10-01',
        'category' => 'Keamanan',
        'amount' => 40,
    ]);

    $response = $this->actingAs($user)
        ->get(route('pembangunan.dashboard', ['property_id' => $property->id]));

    $response->assertSee('Rp 1.300')
        ->assertSee('Rp 400')
        ->assertSee('Rp 900')
        ->assertSee('Rp 700')
        ->assertSee('Rp 140')
        ->assertSee('Rp 560')
        ->assertSee('Rp 2.000')
        ->assertSee('Rp 540')
        ->assertSee('Rp 1.460')
        ->assertViewHas('constructionBudget', fn (int|float|string $total): bool => (float) $total === 1300.0)
        ->assertViewHas('constructionExpense', fn (int|float|string $total): bool => (float) $total === 400.0)
        ->assertViewHas('constructionDeviation', fn (int|float|string $total): bool => (float) $total === 900.0)
        ->assertViewHas('totalFacilityBudget', fn (int|float|string $total): bool => (float) $total === 700.0)
        ->assertViewHas('facilityExpense', fn (int|float|string $total): bool => (float) $total === 140.0)
        ->assertViewHas('facilityDeviation', fn (int|float|string $total): bool => (float) $total === 560.0)
        ->assertViewHas('totalBudget', fn (int|float|string $total): bool => (float) $total === 2000.0)
        ->assertViewHas('totalExpense', fn (int|float|string $total): bool => (float) $total === 540.0)
        ->assertViewHas('totalBudgetDeviation', fn (int|float|string $total): bool => (float) $total === 1460.0);
});

test('pembangunan staff no longer see the construction finance menu link', function () {
    $division = Division::firstOrCreate(
        ['slug' => 'pembangunan'],
        ['name' => 'Pembangunan', 'is_active' => true]
    );
    $user = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);

    $this->actingAs($user)
        ->get(route('pembangunan.dashboard'))
        ->assertDontSee('href="'.route('finance.index').'"', false)
        ->assertSee('Progress Kavling')
        ->assertSee(route('pembangunan.progress.index'), false)
        ->assertDontSee('Tahapan Pembangunan');
});

test('document processing dashboard filters bookings by property and lot', function () {
    $division = Division::create([
        'name' => 'Pemberkasan',
        'slug' => 'pemberkasan',
        'is_active' => true,
    ]);
    $documentUser = User::factory()->create([
        'role' => 'staff_pemberkasan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Pemberkasan']);
    $otherProperty = Property::create(['name' => 'Perumahan Lain']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $otherBlock = Block::create(['property_id' => $otherProperty->id, 'name' => 'B']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'booked',
    ]);
    $otherLot = Lot::create([
        'block_id' => $otherBlock->id,
        'lot_number' => '02',
        'house_price' => 500000000,
        'status' => 'booked',
    ]);
    $customer = Customer::create(['name' => 'Pembeli Pemberkasan']);
    $otherCustomer = Customer::create(['name' => 'Pembeli Lain']);
    Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-09-30',
        'status' => 'booking',
    ]);
    Booking::create([
        'lot_id' => $otherLot->id,
        'customer_id' => $otherCustomer->id,
        'booking_date' => '2026-09-30',
        'status' => 'booking',
    ]);

    $response = $this->actingAs($documentUser)
        ->get(route('pemberkasan.dashboard', ['property_id' => $property->id, 'lot_id' => $lot->id]));

    $response->assertOk()
        ->assertSee('Pembeli Pemberkasan')
        ->assertDontSee('Pembeli Lain')
        ->assertViewHas('stats', fn (array $stats): bool => $stats['total_bookings'] === 1)
        ->assertViewHas('workflowChart', fn (Collection $chart): bool => (int) data_get($chart->firstWhere('key', 'total_bookings'), 'value') === 1)
        ->assertViewHas('monthlyBookingChart', fn (Collection $chart): bool => (int) data_get($chart->firstWhere('key', '2026-09'), 'bookings') === 1);
});

test('document processing dashboard filters workflow modal bookings by property and lot', function () {
    $division = Division::create([
        'name' => 'Pemberkasan',
        'slug' => 'pemberkasan',
        'is_active' => true,
    ]);
    $documentUser = User::factory()->create([
        'role' => 'staff_pemberkasan',
        'division_id' => $division->id,
    ]);
    $property = Property::create([
        'name' => 'Perumahan Workflow',
        'site_plan_path' => 'site-plans/workflow.png',
    ]);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '07',
        'house_price' => 500000000,
        'status' => 'process',
        'map_coordinates' => ['x' => 10, 'y' => 15, 'width' => 5, 'height' => 8],
    ]);
    $customer = Customer::create(['name' => 'Pembeli SP3']);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-10-01',
        'status' => 'process',
    ]);
    DocumentProcess::create([
        'booking_id' => $booking->id,
        'status' => 'incomplete',
        'bi_checking_status' => 'processing',
    ]);
    BankProcess::create([
        'booking_id' => $booking->id,
        'bank_name' => 'Bank Contoh',
        'status' => 'approved',
    ]);
    Sp3::create([
        'booking_id' => $booking->id,
        'sp3_number' => 'SP3-007',
        'status' => 'issued',
    ]);
    $otherLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '08',
        'house_price' => 500000000,
        'status' => 'process',
        'map_coordinates' => ['x' => 20, 'y' => 15, 'width' => 5, 'height' => 8],
    ]);
    $otherBooking = Booking::create([
        'lot_id' => $otherLot->id,
        'customer_id' => Customer::create(['name' => 'Pembeli Kavling Lain'])->id,
        'booking_date' => '2026-10-02',
        'status' => 'process',
    ]);
    Sp3::create([
        'booking_id' => $otherBooking->id,
        'sp3_number' => 'SP3-008',
        'status' => 'issued',
    ]);
    $otherProperty = Property::create(['name' => 'Perumahan Lain']);
    $otherBlock = Block::create(['property_id' => $otherProperty->id, 'name' => 'B']);
    $otherPropertyLot = Lot::create([
        'block_id' => $otherBlock->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'process',
        'map_coordinates' => ['x' => 10, 'y' => 15, 'width' => 5, 'height' => 8],
    ]);
    $otherPropertyBooking = Booking::create([
        'lot_id' => $otherPropertyLot->id,
        'customer_id' => Customer::create(['name' => 'Pembeli Perumahan Lain'])->id,
        'booking_date' => '2026-10-03',
        'status' => 'process',
    ]);
    Sp3::create([
        'booking_id' => $otherPropertyBooking->id,
        'sp3_number' => 'SP3-009',
        'status' => 'issued',
    ]);

    $response = $this->actingAs($documentUser)
        ->get(route('pemberkasan.dashboard', [
            'property_id' => $property->id,
            'workflow' => 'sp3_issued',
            'workflow_property_id' => $property->id,
            'workflow_lot_id' => $lot->id,
        ]));

    $response->assertOk()
        ->assertSee('BI Checking')
        ->assertSee('Berkas Belum Lengkap')
        ->assertSee('Akad')
        ->assertSee('Serah Terima Rumah')
        ->assertDontSee('Bank Disetujui')
        ->assertSee('Pembeli SP3')
        ->assertSee('A/07')
        ->assertSee('data-workflow-bookings-modal', false)
        ->assertSee('data-workflow-bookings-modal-open', false)
        ->assertSee('Detail kategori')
        ->assertSee('name="workflow_property_id"', false)
        ->assertSee('name="workflow_lot_id"', false)
        ->assertSee('Lihat detail booking')
        ->assertSee(route('pemberkasan.dashboard', ['property_id' => $property->id, 'workflow' => 'sp3_issued']), false)
        ->assertSee('data-lot-status="SP3"', false)
        ->assertViewHas('workflowChart', fn (Collection $chart): bool => $chart->pluck('key')->all() === [
            'total_bookings',
            'bi_checking',
            'incomplete_documents',
            'complete_documents',
            'bank_processing',
            'sp3_issued',
            'waiting_akad',
            'finish',
            'revision',
        ])
        ->assertViewHas('workflowBookings', fn ($bookings): bool => $bookings->total() === 1
            && $bookings->getCollection()->first()->is($booking));

    $propertyFilteredResponse = $this->actingAs($documentUser)
        ->get(route('pemberkasan.dashboard', [
            'property_id' => $property->id,
            'workflow' => 'sp3_issued',
            'workflow_property_id' => $otherProperty->id,
        ]));

    $propertyFilteredResponse->assertSee('Pembeli Perumahan Lain')
        ->assertViewHas('workflowBookings', fn ($bookings): bool => $bookings->total() === 1
            && $bookings->getCollection()->first()->is($otherPropertyBooking));

    $lotFilteredResponse = $this->actingAs($documentUser)
        ->get(route('pemberkasan.dashboard', [
            'property_id' => $property->id,
            'workflow' => 'sp3_issued',
            'workflow_property_id' => $otherProperty->id,
            'workflow_lot_id' => $otherPropertyLot->id,
        ]));

    $lotFilteredResponse->assertSee('Pembeli Perumahan Lain')
        ->assertViewHas('workflowBookings', fn ($bookings): bool => $bookings->total() === 1
            && $bookings->getCollection()->first()->is($otherPropertyBooking));
});

test('construction finance and document dashboards show a read only site plan', function () {
    $divisionUsers = [];

    foreach ([
        'pembangunan' => 'staff_pembangunan',
        'finance' => 'staff_finance',
        'pemberkasan' => 'staff_pemberkasan',
    ] as $divisionSlug => $role) {
        $division = Division::create([
            'name' => ucfirst($divisionSlug),
            'slug' => $divisionSlug,
            'is_active' => true,
        ]);
        $divisionUsers[$divisionSlug] = User::factory()->create([
            'role' => $role,
            'division_id' => $division->id,
        ]);
    }

    $property = Property::create([
        'name' => 'Perumahan Denah Baca Saja',
        'site_plan_path' => 'site-plans/read-only.png',
    ]);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'available',
        'map_coordinates' => ['x' => 10, 'y' => 15, 'width' => 5, 'height' => 8],
    ]);

    $dashboardRequests = [
        ['pembangunan', 'pembangunan.dashboard', ['property_id' => $property->id], true],
        ['finance', 'keuangan.cash-position.index', ['site_plan_property_id' => $property->id], false],
        ['pemberkasan', 'pemberkasan.dashboard', ['property_id' => $property->id], false],
    ];

    foreach ($dashboardRequests as [$divisionSlug, $routeName, $query, $canEditLot]) {
        $response = $this->actingAs($divisionUsers[$divisionSlug])
            ->get(route($routeName, $query))
            ->assertSee('data-site-plan-viewer', false)
            ->assertSee('Kavling A/01', false)
            ->assertViewHas('sitePlanLots', fn (Collection $lots): bool => $lots->contains('id', $lot->id));

        if ($canEditLot) {
            $response->assertSee(route('pembangunan.properties.blocks.lots.edit', [$property, $block, $lot]), false)
                ->assertSee('data-lot-action-label="Edit kavling"', false);
        } else {
            $response->assertSee('data-lot-action-url=""', false)
                ->assertDontSee(route('admin.properties.blocks.lots.edit', [$property, $block, $lot]), false);
        }
    }
});

test('construction staff can edit a lot from its site plan but other divisions cannot', function () {
    $constructionDivision = Division::create([
        'name' => 'Pembangunan',
        'slug' => 'pembangunan',
        'is_active' => true,
    ]);
    $constructionUser = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $constructionDivision->id,
    ]);
    $financeDivision = Division::create([
        'name' => 'Finance',
        'slug' => 'finance',
        'is_active' => true,
    ]);
    $financeUser = User::factory()->create([
        'role' => 'staff_finance',
        'division_id' => $financeDivision->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Edit Kavling']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => 'A-01',
        'house_price' => 500000000,
        'status' => 'available',
    ]);
    $editUrl = route('pembangunan.properties.blocks.lots.edit', [$property, $block, $lot]);

    $this->actingAs($constructionUser)
        ->get($editUrl)
        ->assertSee('Edit Kavling A-01')
        ->assertSee(route('pembangunan.properties.blocks.lots.update', [$property, $block, $lot]), false)
        ->assertSee(route('pembangunan.dashboard', ['property_id' => $property->id]), false);

    $this->actingAs($constructionUser)
        ->put(route('pembangunan.properties.blocks.lots.update', [$property, $block, $lot]), [
            'lot_number' => '01',
            'house_price' => 525000000,
            'status' => 'blocked',
            'notes' => 'Menunggu pemeriksaan lapangan',
        ])
        ->assertRedirect(route('pembangunan.dashboard', ['property_id' => $property->id]))
        ->assertSessionHas('success', 'Kavling berhasil diperbarui.');

    expect($lot->fresh()->house_price)->toBe('525000000.00')
        ->and($lot->fresh()->status)->toBe('blocked')
        ->and($lot->fresh()->notes)->toBe('Menunggu pemeriksaan lapangan');

    $this->actingAs($financeUser)
        ->get($editUrl)
        ->assertForbidden();
});

test('marketing dashboard filters property data and shows block with lot number', function () {
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $marketingUser = User::factory()->create([
        'role' => 'tl_marketing',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Terpilih']);
    $otherProperty = Property::create(['name' => 'Perumahan Lain']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $otherBlock = Block::create(['property_id' => $otherProperty->id, 'name' => 'B']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'booked',
    ]);
    $otherLot = Lot::create([
        'block_id' => $otherBlock->id,
        'lot_number' => '02',
        'house_price' => 600000000,
        'status' => 'booked',
    ]);
    $customer = Customer::create(['name' => 'Pembeli Terpilih']);
    $otherCustomer = Customer::create(['name' => 'Pembeli Lain']);
    Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-09-30',
        'status' => 'booking',
    ]);
    Booking::create([
        'lot_id' => $otherLot->id,
        'customer_id' => $otherCustomer->id,
        'booking_date' => '2026-09-30',
        'status' => 'booking',
    ]);
    Budget::create([
        'property_id' => $property->id,
        'name' => 'RAB Perumahan Terpilih',
        'category' => 'Pembangunan',
        'amount' => 5000000,
    ]);
    Budget::create([
        'property_id' => $otherProperty->id,
        'name' => 'RAB Perumahan Lain',
        'category' => 'Pembangunan',
        'amount' => 9000000,
    ]);
    IncomeTransaction::create([
        'property_id' => $property->id,
        'transaction_number' => 'INC-MARKETING-01',
        'transaction_date' => '2026-09-30',
        'category' => 'Booking Fee',
        'amount' => 12000000,
    ]);
    IncomeTransaction::create([
        'property_id' => $otherProperty->id,
        'transaction_number' => 'INC-MARKETING-02',
        'transaction_date' => '2026-09-30',
        'category' => 'Booking Fee',
        'amount' => 18000000,
    ]);
    ExpenseTransaction::create([
        'property_id' => $property->id,
        'division' => 'marketing',
        'transaction_number' => 'EXP-MARKETING-01',
        'transaction_date' => '2026-09-30',
        'category' => 'Material',
        'amount' => 3000000,
    ]);
    ExpenseTransaction::create([
        'property_id' => $otherProperty->id,
        'division' => 'marketing',
        'transaction_number' => 'EXP-MARKETING-02',
        'transaction_date' => '2026-09-30',
        'category' => 'Material',
        'amount' => 7000000,
    ]);
    ExpenseTransaction::create([
        'property_id' => $property->id,
        'division' => 'pembangunan',
        'transaction_number' => 'EXP-CONSTRUCTION-01',
        'transaction_date' => '2026-09-30',
        'category' => 'Biaya Pembangunan',
        'amount' => 9000000,
    ]);

    $response = $this->actingAs($marketingUser)
        ->get(route('marketing.dashboard', ['property_id' => $property->id]));

    $response->assertSee('Total Harga Kavling')
        ->assertSee('Serah Terima Rumah')
        ->assertDontSee('Booking Finish')
        ->assertSee('Total Pengeluaran')
        ->assertDontSee('Total RAB')
        ->assertDontSee('Deviasi RAB')
        ->assertDontSee('Total Pemasukan')
        ->assertDontSee('Saldo Bersih')
        ->assertSee('Rp 500.000.000')
        ->assertSee('Rp 3.000.000')
        ->assertDontSee('Biaya Pembangunan')
        ->assertSee('Pembeli Terpilih')
        ->assertDontSee('Pembeli Lain')
        ->assertSee('A/01')
        ->assertDontSee('B/02')
        ->assertViewHas('financialStats', fn (array $financialStats): bool => (float) $financialStats['total_house_price'] === 500000000.0
            && (float) $financialStats['total_expense'] === 3000000.0)
        ->assertViewHas('stats', fn (array $stats): bool => $stats['total_customers'] === 1
            && $stats['active_bookings'] === 1
            && $stats['booked_lots'] === 1)
        ->assertViewHas('bookingStatusChart', fn (Collection $chart): bool => (int) data_get($chart->firstWhere('status', 'booking'), 'value') === 1)
        ->assertViewHas('monthlyBookingChart', fn (Collection $chart): bool => (int) data_get($chart->firstWhere('key', '2026-09'), 'bookings') === 1);
});
