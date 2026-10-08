<?php

use App\Models\Block;
use App\Models\ConstructionProgress;
use App\Models\ConstructionStage;
use App\Models\Division;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;

test('construction progress pages and dashboard summarize the highest stage progress per lot', function () {
    $division = Division::create([
        'name' => 'Pembangunan',
        'slug' => 'pembangunan',
        'is_active' => true,
    ]);
    $constructionUser = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
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
        'updated_by' => $constructionUser->id,
    ]);
    ConstructionProgress::create([
        'lot_id' => $firstLot->id,
        'stage_id' => $stages[1]->id,
        'progress' => 45,
        'updated_by' => $constructionUser->id,
    ]);
    ConstructionProgress::create([
        'lot_id' => $secondLot->id,
        'stage_id' => $stages[2]->id,
        'progress' => 8,
        'updated_by' => $constructionUser->id,
    ]);
    $orphanedProgress = ConstructionProgress::create([
        'lot_id' => $secondLot->id,
        'stage_id' => null,
        'progress' => 12,
        'updated_by' => $constructionUser->id,
    ]);
    ConstructionProgress::create([
        'lot_id' => $otherLot->id,
        'stage_id' => $stages[3]->id,
        'progress' => 99,
        'updated_by' => $constructionUser->id,
    ]);

    $listResponse = $this->actingAs($constructionUser)->get(route('pembangunan.progress.index', [
        'property_id' => $property->id,
    ]));
    $detailResponse = $this->get(route('pembangunan.progress.show', [
        'property' => $property,
        'block' => $block,
        'lot' => $firstLot,
    ]));
    $secondDetailResponse = $this->get(route('pembangunan.progress.show', [
        'property' => $property,
        'block' => $block,
        'lot' => $secondLot,
    ]));
    $dashboardResponse = $this->get(route('pembangunan.dashboard', [
        'property_id' => $property->id,
    ]));

    $listResponse->assertSee('45.00%')
        ->assertSee('8.00%')
        ->assertDontSee('99.00%');
    $detailResponse->assertSee('45.00%');
    $secondDetailResponse->assertSee('8.00%')
        ->assertDontSee('12.00%');
    $dashboardResponse->assertViewHas('stats', fn (array $stats): bool => $stats['physical_progress'] === 26.5)
        ->assertViewHas('latestProgressUpdates', fn ($updates): bool => ! $updates->contains('id', $orphanedProgress->id)
            && $updates->every(fn (ConstructionProgress $progress): bool => $progress->stage_id !== null));
});
