<?php

use App\Models\Block;
use App\Models\ConstructionProgress;
use App\Models\ConstructionProgressHistory;
use App\Models\ConstructionStage;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;

test('construction stage order must be unique when creating a stage', function () {
    $user = User::factory()->create(['role' => 'admin']);
    ConstructionStage::create([
        'name' => 'Tahap Lama',
        'order' => 1,
        'min_progress' => 0,
        'max_progress' => 100,
    ]);

    $response = $this->actingAs($user)->post(route('admin.construction-stages.store'), [
        'name' => 'Tahap Baru',
        'order' => 1,
        'min_progress' => 0,
        'max_progress' => 100,
    ]);

    $response->assertSessionHasErrors('order');
    expect(ConstructionStage::query()->where('name', 'Tahap Baru')->exists())->toBeFalse();
});

test('construction stage can keep its own order when updating', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $stage = ConstructionStage::create([
        'name' => 'Tahap Lama',
        'order' => 1,
        'min_progress' => 0,
        'max_progress' => 100,
    ]);

    $response = $this->actingAs($user)->put(
        route('admin.construction-stages.update', $stage),
        [
            'name' => $stage->name,
            'order' => 1,
            'min_progress' => $stage->min_progress,
            'max_progress' => $stage->max_progress,
        ]
    );

    $response->assertRedirect(route('admin.construction-stages.index'));
    $response->assertSessionDoesntHaveErrors();
});

test('construction stage rejects an overlapping progress range', function () {
    $user = User::factory()->create(['role' => 'admin']);
    ConstructionStage::create([
        'name' => 'Tahap Pertama',
        'order' => 1,
        'min_progress' => 1,
        'max_progress' => 10,
    ]);

    $response = $this->actingAs($user)->post(route('admin.construction-stages.store'), [
        'name' => 'Tahap Bertabrakan',
        'order' => 2,
        'min_progress' => 8,
        'max_progress' => 20,
    ]);

    $response->assertSessionHasErrors('min_progress');
    expect(ConstructionStage::query()->where('name', 'Tahap Bertabrakan')->exists())->toBeFalse();
});

test('construction stage accepts an adjacent progress range', function () {
    $user = User::factory()->create(['role' => 'admin']);
    ConstructionStage::create([
        'name' => 'Tahap Pertama',
        'order' => 1,
        'min_progress' => 1,
        'max_progress' => 10,
    ]);

    $response = $this->actingAs($user)->post(route('admin.construction-stages.store'), [
        'name' => 'Tahap Berikutnya',
        'order' => 2,
        'min_progress' => 11,
        'max_progress' => 20,
    ]);

    $response->assertRedirect(route('admin.construction-stages.index'));
    $response->assertSessionDoesntHaveErrors();
    $this->assertDatabaseHas('construction_stages', ['name' => 'Tahap Berikutnya']);
});

test('construction stage can be deleted while preserving linked lot progress and history', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Griya Asri']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'process',
    ]);
    $stage = ConstructionStage::create([
        'name' => 'Pondasi',
        'order' => 1,
        'min_progress' => 0,
        'max_progress' => 100,
    ]);
    $progress = ConstructionProgress::create([
        'lot_id' => $lot->id,
        'stage_id' => $stage->id,
        'progress' => 45,
        'updated_by' => $user->id,
        'notes' => 'Pekerjaan berjalan',
    ]);
    $history = ConstructionProgressHistory::create([
        'construction_progress_id' => $progress->id,
        'progress' => 45,
        'previous_progress' => 30,
        'updated_by' => $user->id,
        'notes' => 'Pekerjaan berjalan',
    ]);

    $response = $this->actingAs($user)->delete(route('admin.construction-stages.destroy', $stage));

    $response->assertRedirect(route('admin.construction-stages.index'));
    $response->assertSessionHas('success', 'Tahapan pembangunan berhasil dihapus.');
    $this->assertDatabaseMissing('construction_stages', ['id' => $stage->id]);
    $this->assertDatabaseHas('construction_progress', [
        'id' => $progress->id,
        'lot_id' => $lot->id,
        'stage_id' => null,
        'progress' => 45,
    ]);
    $this->assertModelExists($history);
});
