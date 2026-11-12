<?php

use App\Exports\ConstructionProgressByLotExport;
use App\Models\Block;
use App\Models\Booking;
use App\Models\ConstructionProgress;
use App\Models\ConstructionProgressHistory;
use App\Models\ConstructionStage;
use App\Models\Customer;
use App\Models\Division;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

test('construction staff can export filtered lot progress with office categories', function () {
    $division = Division::firstOrCreate(
        ['slug' => 'pembangunan'],
        ['name' => 'Pembangunan', 'is_active' => true]
    );
    $user = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Progress Export']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lots = collect(['001', '002', '003'])->map(fn (string $lotNumber): Lot => Lot::create([
        'block_id' => $block->id,
        'lot_number' => $lotNumber,
        'house_price' => 350000000,
    ]));
    $stage = $property->constructionStages()->create([
        'name' => 'Pembangunan',
        'order' => 1,
        'min_progress' => 0,
        'max_progress' => 100,
    ]);
    $customer = Customer::create(['name' => 'Customer Kavling 001']);
    Booking::create([
        'lot_id' => $lots[0]->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-10-01',
        'status' => 'booking',
    ]);
    ConstructionProgress::create([
        'lot_id' => $lots[0]->id,
        'stage_id' => $stage->id,
        'progress' => 25,
        'updated_by' => $user->id,
        'notes' => 'Progress 25 persen.',
    ]);
    ConstructionProgress::create([
        'lot_id' => $lots[1]->id,
        'stage_id' => $stage->id,
        'progress' => 80,
        'updated_by' => $user->id,
        'notes' => 'Progress 80 persen.',
    ]);
    $otherProperty = Property::create(['name' => 'Perumahan Lain']);
    $otherBlock = Block::create(['property_id' => $otherProperty->id, 'name' => 'B']);
    Lot::create([
        'block_id' => $otherBlock->id,
        'lot_number' => '999',
        'house_price' => 350000000,
    ]);

    $response = $this->actingAs($user)
        ->get(route('pembangunan.progress.export', ['property_id' => $property->id]));

    $response->assertDownload();

    $filePath = $response->baseResponse->getFile()->getPathname();
    $spreadsheet = IOFactory::load($filePath);

    try {
        $sheet = $spreadsheet->getActiveSheet();

        expect($sheet->getTitle())->toBe('Progress Kavling')
            ->and($sheet->getHighestDataRow())->toBe(4)
            ->and($sheet->getAutoFilter()->getRange())->toBe('A1:H4')
            ->and($sheet->getCell('A1')->getValue())->toBe('Perumahan')
            ->and($sheet->getCell('C1')->getValue())->toBe('No. Kavling')
            ->and($sheet->getCell('D1')->getValue())->toBe('Konsumen')
            ->and($sheet->getCell('A2')->getValue())->toBe($property->name)
            ->and($sheet->getCell('C2')->getValue())->toBe('001')
            ->and($sheet->getCell('D2')->getValue())->toBe($customer->name)
            ->and($sheet->getCell('E2')->getValue())->toBe(25.0)
            ->and($sheet->getCell('F2')->getValue())->toBe('Progres 25-54%')
            ->and($sheet->getCell('H2')->getValue())->toBe('Progress 25 persen.')
            ->and($sheet->getCell('C3')->getValue())->toBe('002')
            ->and($sheet->getCell('D3')->getValue())->toBe('-')
            ->and($sheet->getCell('F3')->getValue())->toBe('Progres 80-100%')
            ->and($sheet->getCell('C4')->getValue())->toBe('003')
            ->and($sheet->getCell('F4')->getValue())->toBe('Belum dibangun')
            ->and($sheet->getCell('E2')->getStyle()->getFill()->getStartColor()->getRGB())->toBe('FFD966')
            ->and($sheet->getCell('F3')->getStyle()->getFill()->getStartColor()->getRGB())->toBe('70AD47')
            ->and($sheet->getCell('A5')->getValue())->toBeNull();
    } finally {
        $spreadsheet->disconnectWorksheets();

        if (is_file($filePath)) {
            unlink($filePath);
        }
    }
});

test('lot progress export colors percentage bands like the office workbook', function () {
    $sheet = (new Spreadsheet)->getActiveSheet();
    $progressValues = [0, 24, 25, 54, 55, 79, 80, 100];

    foreach ($progressValues as $index => $progress) {
        $sheet->setCellValue('E'.($index + 2), $progress);
    }

    $export = new ConstructionProgressByLotExport(collect());
    $export->styles($sheet);

    $colors = collect(range(2, 9))
        ->map(fn (int $row): array => [
            $sheet->getStyle("E{$row}")->getFill()->getStartColor()->getRGB(),
            $sheet->getStyle("F{$row}")->getFill()->getStartColor()->getRGB(),
        ])
        ->all();

    expect($colors)->toBe([
        ['F8696B', 'F8696B'],
        ['F8696B', 'F8696B'],
        ['FFD966', 'FFD966'],
        ['FFD966', 'FFD966'],
        ['5B9BD5', '5B9BD5'],
        ['5B9BD5', '5B9BD5'],
        ['70AD47', '70AD47'],
        ['70AD47', '70AD47'],
    ]);
});

test('users from other divisions cannot export construction lot progress', function () {
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
        ->get(route('pembangunan.progress.export'))
        ->assertForbidden();
});

test('construction staff can update progress without changing admin fields', function () {
    $division = Division::firstOrCreate(
        ['slug' => 'pembangunan'],
        ['name' => 'Pembangunan', 'is_active' => true]
    );
    $user = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Progress']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
        'status' => 'process',
    ]);
    $stage = $property->constructionStages()->create([
        'name' => 'Pondasi',
        'order' => 1,
        'min_progress' => 1,
        'max_progress' => 30,
    ]);
    $progress = ConstructionProgress::create([
        'lot_id' => $lot->id,
        'stage_id' => $stage->id,
        'progress' => 10,
        'realization' => 12500000,
        'updated_by' => $user->id,
        'notes' => 'Realisasi dan catatan admin.',
    ]);

    $this->actingAs($user)
        ->put(route('pembangunan.progress.records.update', [$property, $block, $lot, $progress]), [
            'stage_id' => $stage->id,
            'progress' => 25,
        ])
        ->assertRedirect(route('pembangunan.progress.show', [$property, $block, $lot]))
        ->assertSessionHas('success', 'Progress pembangunan berhasil diperbarui.');

    $progress = ConstructionProgress::query()
        ->where('lot_id', $lot->id)
        ->where('stage_id', $stage->id)
        ->firstOrFail();

    $this->assertDatabaseHas('construction_progress', [
        'id' => $progress->id,
        'progress' => 25,
        'realization' => '12500000.00',
        'notes' => 'Realisasi dan catatan admin.',
        'updated_by' => $user->id,
    ]);
    $this->assertDatabaseHas('construction_progress_histories', [
        'construction_progress_id' => $progress->id,
        'progress' => 25,
        'previous_progress' => 10,
        'realization' => '12500000.00',
        'previous_realization' => '12500000.00',
        'notes' => 'Realisasi dan catatan admin.',
        'updated_by' => $user->id,
    ]);
    $this->get(route('pembangunan.progress.show', [$property, $block, $lot]))
        ->assertSee('Realisasi dan catatan admin.')
        ->assertSee('bg-orange-50', false)
        ->assertSee('bg-red-50', false)
        ->assertDontSee('name="realization"', false)
        ->assertDontSee('name="notes"', false)
        ->assertDontSee(route('pembangunan.progress.records.destroy', [$property, $block, $lot, $progress]), false);

    $this->put(route('pembangunan.progress.records.update', [$property, $block, $lot, $progress]), [
        'stage_id' => $stage->id,
        'progress' => 30,
        'realization' => 1,
        'notes' => 'Tidak boleh diubah tim.',
    ])
        ->assertSessionHasErrors(['realization', 'notes']);

    $this->assertDatabaseHas('construction_progress', [
        'id' => $progress->id,
        'progress' => 25,
        'realization' => '12500000.00',
        'notes' => 'Realisasi dan catatan admin.',
    ]);

    expect(ConstructionProgressHistory::query()->where('construction_progress_id', $progress->id)->count())->toBe(1);
});

test('construction staff can create progress for an admin-defined stage', function () {
    $division = Division::firstOrCreate(
        ['slug' => 'pembangunan'],
        ['name' => 'Pembangunan', 'is_active' => true]
    );
    $user = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Progress Baru']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '02',
        'house_price' => 350000000,
    ]);
    $stage = ConstructionStage::create([
        'property_id' => $property->id,
        'name' => 'Struktur',
        'order' => 1,
        'min_progress' => 20,
        'max_progress' => 60,
        'max_progress' => 4,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('pembangunan.progress.store', [$property, $block, $lot]), [
            'stage_id' => $stage->id,
            'progress' => 35,
        ])
        ->assertRedirect(route('pembangunan.progress.show', [$property, $block, $lot]))
        ->assertSessionHas('success', 'Progress pembangunan berhasil ditambahkan.');

    $progress = ConstructionProgress::query()
        ->where('lot_id', $lot->id)
        ->where('stage_id', $stage->id)
        ->firstOrFail();
    $this->assertDatabaseHas('construction_progress', [
        'id' => $progress->id,
        'progress' => 35,
        'realization' => '0.00',
        'updated_by' => $user->id,
    ]);
    $this->assertDatabaseHas('construction_progress_histories', [
        'construction_progress_id' => $progress->id,
        'lot_id' => $lot->id,
        'stage_id' => $stage->id,
        'progress' => 35,
        'previous_progress' => null,
    ]);

    $this->actingAs($user)
        ->put(route('pembangunan.progress.records.update', [$property, $block, $lot, $progress]), [
            'stage_id' => $stage->id,
            'progress' => 50,
        ])
        ->assertRedirect(route('pembangunan.progress.show', [$property, $block, $lot]))
        ->assertSessionHas('success', 'Progress pembangunan berhasil diperbarui.');

    $this->assertDatabaseHas('construction_progress', [
        'id' => $progress->id,
        'progress' => 50,
        'realization' => '0.00',
        'notes' => null,
    ]);
    $this->get(route('pembangunan.progress.show', [$property, $block, $lot]))
        ->assertSee('2.00000%')
        ->assertSee('2.00%');
    expect(ConstructionProgressHistory::query()->where('construction_progress_id', $progress->id)->count())->toBe(2);
});

test('construction staff can report zero percent on a later stage before earlier work is complete', function () {
    $division = Division::firstOrCreate(
        ['slug' => 'pembangunan'],
        ['name' => 'Pembangunan', 'is_active' => true]
    );
    $user = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Progress Tidak Berurutan']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '05',
        'house_price' => 350000000,
    ]);
    $property->constructionStages()->create([
        'name' => 'Tahap Awal',
        'order' => 1,
        'max_progress' => 40,
    ]);
    $laterStage = $property->constructionStages()->create([
        'name' => 'Tahap Lanjutan',
        'order' => 2,
        'min_progress' => 50,
        'max_progress' => 100,
        'max_progress' => 60,
    ]);

    $this->actingAs($user)
        ->post(route('pembangunan.progress.store', [$property, $block, $lot]), [
            'stage_id' => $laterStage->id,
            'progress' => 0,
        ])
        ->assertRedirect(route('pembangunan.progress.show', [$property, $block, $lot]))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('construction_progress', [
        'lot_id' => $lot->id,
        'stage_id' => $laterStage->id,
        'progress' => 0,
    ]);
    $this->get(route('pembangunan.progress.show', [$property, $block, $lot]))
        ->assertSee('Progress Fisik')
        ->assertSee('0.00%');
});

test('deleting current lot progress preserves its history', function () {
    $division = Division::firstOrCreate(
        ['slug' => 'pembangunan'],
        ['name' => 'Pembangunan', 'is_active' => true]
    );
    $user = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Hapus Progress']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '03',
        'house_price' => 350000000,
    ]);
    $stage = ConstructionStage::create([
        'property_id' => $property->id,
        'name' => 'Pondasi',
        'order' => 1,
        'min_progress' => 1,
        'max_progress' => 30,
        'is_active' => true,
    ]);
    $progress = ConstructionProgress::create([
        'lot_id' => $lot->id,
        'stage_id' => $stage->id,
        'progress' => 25,
        'realization' => 12500000,
        'updated_by' => $user->id,
        'notes' => 'Progress awal.',
    ]);
    ConstructionProgressHistory::create([
        'construction_progress_id' => $progress->id,
        'lot_id' => $lot->id,
        'stage_id' => $stage->id,
        'progress' => 25,
        'previous_progress' => null,
        'realization' => 12500000,
        'previous_realization' => null,
        'updated_by' => $user->id,
        'notes' => 'Riwayat yang harus tetap ada.',
    ]);

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user)
        ->delete(route('pembangunan.progress.records.destroy', [$property, $block, $lot, $progress]))
        ->assertForbidden();

    $this->assertDatabaseHas('construction_progress', ['id' => $progress->id]);

    $this->actingAs($admin)
        ->delete(route('pembangunan.progress.records.destroy', [$property, $block, $lot, $progress]))
        ->assertRedirect(route('pembangunan.progress.show', [$property, $block, $lot]))
        ->assertSessionHas('success', 'Progress pembangunan dihapus. Riwayat perubahan tetap tersimpan.');

    $this->assertDatabaseMissing('construction_progress', [
        'id' => $progress->id,
    ]);
    $this->assertDatabaseHas('construction_progress_histories', [
        'construction_progress_id' => null,
        'lot_id' => $lot->id,
        'stage_id' => $stage->id,
        'notes' => 'Riwayat yang harus tetap ada.',
    ]);
    $this->assertDatabaseHas('activity_logs', [
        'action' => 'construction_progress.deleted',
        'subject_type' => ConstructionProgress::class,
        'subject_id' => $progress->id,
    ]);
    $this->get(route('pembangunan.progress.show', [$property, $block, $lot]))
        ->assertSee('Riwayat yang harus tetap ada.')
        ->assertSee('Pondasi');
});

test('lot progress screens total realization across all construction stages', function () {
    $division = Division::firstOrCreate(
        ['slug' => 'pembangunan'],
        ['name' => 'Pembangunan', 'is_active' => true]
    );
    $user = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Total Realisasi']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '04',
        'house_price' => 350000000,
        'construction_realization' => 60000000,
    ]);
    $firstStage = $property->constructionStages()->create([
        'name' => 'Pondasi',
        'order' => 1,
        'min_progress' => 1,
        'max_progress' => 50,
    ]);
    $secondStage = $property->constructionStages()->create([
        'name' => 'Struktur',
        'order' => 2,
        'min_progress' => 51,
        'max_progress' => 100,
    ]);

    ConstructionProgress::create([
        'lot_id' => $lot->id,
        'stage_id' => $firstStage->id,
        'progress' => 30,
        'realization' => 20000000,
        'updated_by' => $user->id,
    ]);
    ConstructionProgress::create([
        'lot_id' => $lot->id,
        'stage_id' => $secondStage->id,
        'progress' => 70,
        'realization' => 40000000,
        'updated_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('pembangunan.progress.index'))
        ->assertSee('Rp 60.000.000');

    $this->get(route('pembangunan.progress.show', [$property, $block, $lot]))
        ->assertSee('Realisasi Pengeluaran')
        ->assertSee('Rp 60.000.000');
});

test('only admin can update the total lot construction realization', function () {
    $division = Division::firstOrCreate(
        ['slug' => 'pembangunan'],
        ['name' => 'Pembangunan', 'is_active' => true]
    );
    $staff = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Realisasi Total']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '05',
        'house_price' => 350000000,
        'construction_realization' => 10000000,
    ]);

    $this->actingAs($staff)
        ->put(route('pembangunan.progress.realization.update', [$property, $block, $lot]), [
            'construction_realization' => 20000000,
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->put(route('pembangunan.progress.realization.update', [$property, $block, $lot]), [
            'construction_realization' => 23475250,
        ])
        ->assertRedirect(route('pembangunan.progress.show', [$property, $block, $lot]))
        ->assertSessionHas('success', 'Total realisasi pengeluaran berhasil diperbarui.');

    $this->assertDatabaseHas('lots', [
        'id' => $lot->id,
        'construction_realization' => '23475250.00',
    ]);
});
