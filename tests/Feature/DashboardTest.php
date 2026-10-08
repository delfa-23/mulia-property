<?php

use App\Models\Block;
use App\Models\Booking;
use App\Models\Budget;
use App\Models\ConstructionProgress;
use App\Models\ConstructionStage;
use App\Models\Customer;
use App\Models\Division;
use App\Models\ExpenseTransaction;
use App\Models\IncomeTransaction;
use App\Models\Lot;
use App\Models\Property;
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
        ->assertDontSee(route('pembangunan.common-facilities.index'), false);
});

test('admin sidebar shows progress report under each division', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    expect(substr_count($response->getContent(), 'Progress Report'))->toBe(3);
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

test('admin dashboard averages each lot highest construction progress within selected filters', function () {
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
    $otherLot = Lot::create([
        'block_id' => $otherBlock->id,
        'lot_number' => '03',
        'house_price' => 500000000,
        'status' => 'available',
    ]);
    $stages = collect([
        ['name' => 'Tahap 1', 'min_progress' => 1, 'max_progress' => 20],
        ['name' => 'Tahap 2', 'min_progress' => 21, 'max_progress' => 50],
        ['name' => 'Tahap 3', 'min_progress' => 1, 'max_progress' => 10],
        ['name' => 'Tahap 4', 'min_progress' => 51, 'max_progress' => 100],
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

    $response->assertViewHas('stats', fn (array $stats): bool => $stats['physical_progress'] === 26.5);
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
        ->assertViewHas('stats', fn (array $stats): bool => $stats['total_properties'] === 1
            && $stats['total_blocks'] === 1
            && $stats['total_lots'] === 1
            && $stats['available_lots'] === 1
            && $stats['finish_lots'] === 0);

    $response->assertViewHas('lotStatusChart', fn (Collection $chart): bool => (int) data_get($chart->firstWhere('status', 'available'), 'value') === 1
        && (int) data_get($chart->firstWhere('status', 'finish'), 'value') === 0)
        ->assertViewHas('monthlyPhysicalProgressChart', fn (Collection $chart): bool => $chart->count() === 6
            && $chart->sum('updates') === 0);
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
        'transaction_number' => 'EXP-MARKETING-01',
        'transaction_date' => '2026-09-30',
        'category' => 'Material',
        'amount' => 3000000,
    ]);
    ExpenseTransaction::create([
        'property_id' => $otherProperty->id,
        'transaction_number' => 'EXP-MARKETING-02',
        'transaction_date' => '2026-09-30',
        'category' => 'Material',
        'amount' => 7000000,
    ]);

    $response = $this->actingAs($marketingUser)
        ->get(route('marketing.dashboard', ['property_id' => $property->id]));

    $response->assertSee('Total RAB')
        ->assertSee('Total Pengeluaran')
        ->assertSee('Deviasi RAB')
        ->assertDontSee('Total Pemasukan')
        ->assertDontSee('Saldo Bersih')
        ->assertSee('Rp 5.000.000')
        ->assertSee('Rp 3.000.000')
        ->assertSee('Rp 2.000.000')
        ->assertSee('Pembeli Terpilih')
        ->assertDontSee('Pembeli Lain')
        ->assertSee('A/01')
        ->assertDontSee('B/02')
        ->assertViewHas('financialStats', fn (array $financialStats): bool => (float) $financialStats['total_budget'] === 5000000.0
            && (float) $financialStats['total_income'] === 12000000.0
            && (float) $financialStats['total_expense'] === 3000000.0
            && (float) $financialStats['deviation'] === 2000000.0
            && (float) $financialStats['balance'] === 9000000.0)
        ->assertViewHas('stats', fn (array $stats): bool => $stats['total_customers'] === 1
            && $stats['active_bookings'] === 1
            && $stats['booked_lots'] === 1)
        ->assertViewHas('bookingStatusChart', fn (Collection $chart): bool => (int) data_get($chart->firstWhere('status', 'booking'), 'value') === 1)
        ->assertViewHas('monthlyBookingChart', fn (Collection $chart): bool => (int) data_get($chart->firstWhere('key', '2026-09'), 'bookings') === 1);
});
