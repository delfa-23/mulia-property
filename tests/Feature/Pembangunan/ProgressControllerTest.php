<?php

use App\Models\Block;
use App\Models\ConstructionProgress;
use App\Models\ConstructionProgressHistory;
use App\Models\ConstructionStage;
use App\Models\Division;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;

test('construction staff can update stage progress and realization with visible notes', function () {
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

    $this->actingAs($user)
        ->put(route('pembangunan.progress.update', [$property, $block, $lot]), [
            'stage_id' => $stage->id,
            'progress' => 25,
            'realization' => 12500000,
            'notes' => 'Pengecoran pondasi selesai.',
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
        'notes' => 'Pengecoran pondasi selesai.',
        'updated_by' => $user->id,
    ]);
    $this->assertDatabaseHas('construction_progress_histories', [
        'construction_progress_id' => $progress->id,
        'progress' => 25,
        'previous_progress' => null,
        'realization' => '12500000.00',
        'previous_realization' => null,
        'notes' => 'Pengecoran pondasi selesai.',
        'updated_by' => $user->id,
    ]);
    $this->get(route('pembangunan.progress.show', [$property, $block, $lot]))
        ->assertSee('Pengecoran pondasi selesai.')
        ->assertSee('12.500.000');

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
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('pembangunan.progress.store', [$property, $block, $lot]), [
            'stage_id' => $stage->id,
            'progress' => 35,
            'realization' => 7500000,
            'notes' => 'Kolom lantai satu terpasang.',
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
        'realization' => '7500000.00',
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
            'realization' => 10000000,
            'notes' => 'Struktur utama selesai.',
        ])
        ->assertRedirect(route('pembangunan.progress.show', [$property, $block, $lot]))
        ->assertSessionHas('success', 'Progress pembangunan berhasil diperbarui.');

    $this->assertDatabaseHas('construction_progress', [
        'id' => $progress->id,
        'progress' => 50,
        'realization' => '10000000.00',
        'notes' => 'Struktur utama selesai.',
    ]);
    expect(ConstructionProgressHistory::query()->where('construction_progress_id', $progress->id)->count())->toBe(2);
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

    $this->actingAs($user)
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
