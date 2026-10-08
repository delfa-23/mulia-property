<?php

use App\Models\Block;
use App\Models\ConstructionProgress;
use App\Models\ConstructionStage;
use App\Models\Division;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;

function createPropertyManagementUser(string $role = 'staff_pembangunan'): User
{
    $division = Division::firstOrCreate(
        ['slug' => 'pembangunan'],
        ['name' => 'Pembangunan', 'is_active' => true]
    );

    return User::factory()->create([
        'role' => $role,
        'division_id' => $division->id,
    ]);
}

test('pembangunan staff can manage properties blocks and lots', function () {
    $user = createPropertyManagementUser();

    $this->actingAs($user)
        ->get(route('pembangunan.properties.index'))
        ->assertOk()
        ->assertSee('Perumahan, Block & Kavling');
    $this->get(route('pembangunan.properties.create'))
        ->assertOk();

    $this->post(route('pembangunan.properties.store'), [
        'name' => 'Perumahan Baru',
        'address' => 'Jl. Contoh',
        'description' => 'Deskripsi',
    ])->assertRedirect(route('pembangunan.properties.index'));

    $property = Property::query()->where('name', 'Perumahan Baru')->firstOrFail();
    $this->assertModelExists($property);

    $this->get(route('pembangunan.properties.edit', $property))
        ->assertSee('value="Perumahan Baru"', false)
        ->assertSee('value="Jl. Contoh"', false)
        ->assertSee('>Deskripsi</textarea>', false);
    $this->put(route('pembangunan.properties.update', $property), [
        'name' => 'Perumahan Diperbarui',
        'address' => 'Jl. Baru',
        'description' => 'Deskripsi baru',
    ])->assertRedirect(route('pembangunan.properties.index'));
    $this->assertDatabaseHas('properties', [
        'id' => $property->id,
        'name' => 'Perumahan Diperbarui',
        'address' => 'Jl. Baru',
    ]);

    $this->get(route('pembangunan.properties.blocks.create', $property))
        ->assertOk();
    $this->post(route('pembangunan.properties.blocks.store', $property), [
        'name' => 'A',
        'description' => 'Block pertama',
    ])->assertRedirect(route('pembangunan.properties.blocks.index', $property));

    $block = Block::query()->where('property_id', $property->id)->firstOrFail();

    $this->get(route('pembangunan.properties.blocks.edit', [$property, $block]))
        ->assertSee('value="A"', false)
        ->assertSee('>Block pertama</textarea>', false);
    $this->put(route('pembangunan.properties.blocks.update', [$property, $block]), [
        'name' => 'B',
        'description' => 'Block diperbarui',
    ])->assertRedirect(route('pembangunan.properties.blocks.index', $property));
    $this->assertDatabaseHas('blocks', [
        'id' => $block->id,
        'name' => 'B',
    ]);

    $this->get(route('pembangunan.properties.blocks.lots.create', [$property, $block]))
        ->assertSee('B-', false)
        ->assertSee('name="lot_number"', false)
        ->assertSee('placeholder="01"', false);
    $this->post(route('pembangunan.properties.blocks.lots.store', [$property, $block]), [
        'lot_number' => '01',
        'house_price' => 350000000,
        'status' => 'available',
        'notes' => 'Kavling pertama',
    ])->assertRedirect(route('pembangunan.properties.blocks.lots.index', [$property, $block]));

    $lot = Lot::query()->where('block_id', $block->id)->firstOrFail();
    $this->assertDatabaseHas('lots', [
        'id' => $lot->id,
        'lot_number' => 'B-01',
    ]);

    $this->get(route('pembangunan.properties.blocks.lots.edit', [$property, $block, $lot]))
        ->assertSee('B-', false)
        ->assertSee('value="01"', false)
        ->assertSee('value="350000000.00"', false)
        ->assertSee('value="available" selected', false)
        ->assertSee('>Kavling pertama</textarea>', false);
    $this->put(route('pembangunan.properties.blocks.lots.update', [$property, $block, $lot]), [
        'lot_number' => '02',
        'house_price' => 400000000,
        'status' => 'available',
        'notes' => 'Kavling diperbarui',
    ])->assertRedirect(route('pembangunan.properties.blocks.lots.index', [$property, $block]));
    $this->assertDatabaseHas('lots', [
        'id' => $lot->id,
        'lot_number' => 'B-02',
        'house_price' => 400000000,
    ]);

    $this->delete(route('pembangunan.properties.blocks.lots.destroy', [$property, $block, $lot]))
        ->assertRedirect(route('pembangunan.properties.blocks.lots.index', [$property, $block]));
    $this->delete(route('pembangunan.properties.blocks.destroy', [$property, $block]))
        ->assertRedirect(route('pembangunan.properties.blocks.index', $property));
    $this->delete(route('pembangunan.properties.destroy', $property))
        ->assertRedirect(route('pembangunan.properties.index'));

    $this->assertDatabaseMissing('properties', ['id' => $property->id]);
    $this->assertDatabaseMissing('blocks', ['id' => $block->id]);
    $this->assertDatabaseMissing('lots', ['id' => $lot->id]);
});

test('pembangunan staff saves house prices entered with Indonesian separators', function (string $housePrice) {
    $user = createPropertyManagementUser();
    $property = Property::create(['name' => 'Perumahan Harga']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => 'A-01',
        'house_price' => 1,
        'status' => 'available',
    ]);

    $this->actingAs($user)
        ->put(route('pembangunan.properties.blocks.lots.update', [$property, $block, $lot]), [
            'lot_number' => 'A-01',
            'house_price' => $housePrice,
            'status' => 'available',
        ])
        ->assertRedirect(route('pembangunan.properties.blocks.lots.index', [$property, $block]));

    $this->assertDatabaseHas('lots', [
        'id' => $lot->id,
        'house_price' => '4000000000.00',
    ]);
})->with([
    'grouped amount' => '4.000.000.000',
    'currency-prefixed grouped amount' => 'Rp 4.000.000.000',
]);

test('pembangunan staff can add construction stages scoped to each property', function () {
    $user = createPropertyManagementUser();
    $property = Property::create(['name' => 'Perumahan Tahap Satu']);
    $otherProperty = Property::create(['name' => 'Perumahan Tahap Dua']);

    $this->actingAs($user)
        ->post(route('pembangunan.properties.construction-stages.store', $property), [
            'name' => 'Pondasi Tahap Satu',
            'order' => 1,
            'min_progress' => 1,
            'max_progress' => 20,
            'description' => 'Pekerjaan pondasi',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->post(route('pembangunan.properties.construction-stages.store', $otherProperty), [
        'name' => 'Pondasi Tahap Dua',
        'order' => 1,
        'min_progress' => 1,
        'max_progress' => 20,
    ])->assertRedirect()->assertSessionHas('success');

    $stage = $property->constructionStages()->firstOrFail();

    $this->assertDatabaseHas('construction_stages', [
        'id' => $stage->id,
        'property_id' => $property->id,
        'name' => 'Pondasi Tahap Satu',
        'min_progress' => 1,
        'max_progress' => 20,
    ]);
});

test('pembangunan staff cannot add overlapping progress ranges for one property', function () {
    $user = createPropertyManagementUser();
    $property = Property::create(['name' => 'Perumahan Range']);
    $property->constructionStages()->create([
        'name' => 'Pekerjaan Awal',
        'order' => 1,
        'min_progress' => 1,
        'max_progress' => 20,
    ]);

    $this->actingAs($user)
        ->from(route('pembangunan.properties.index'))
        ->post(route('pembangunan.properties.construction-stages.store', $property), [
            'name' => 'Pekerjaan Berikutnya',
            'order' => 2,
            'min_progress' => 15,
            'max_progress' => 30,
        ])
        ->assertRedirect(route('pembangunan.properties.index'))
        ->assertSessionHasErrors('min_progress');

    $this->assertDatabaseMissing('construction_stages', [
        'property_id' => $property->id,
        'name' => 'Pekerjaan Berikutnya',
    ]);
});

test('deleting a property construction stage removes its lot progress', function () {
    $user = createPropertyManagementUser();
    $property = Property::create(['name' => 'Perumahan Hapus Tahap']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => 'A-01',
        'house_price' => 350000000,
        'status' => 'available',
    ]);
    $stage = $property->constructionStages()->create([
        'name' => 'Pekerjaan Awal',
        'order' => 1,
        'min_progress' => 1,
        'max_progress' => 20,
    ]);
    $progress = ConstructionProgress::create([
        'lot_id' => $lot->id,
        'stage_id' => $stage->id,
        'progress' => 10,
        'updated_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->delete(route('pembangunan.properties.construction-stages.destroy', [$property, $stage]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('construction_stages', ['id' => $stage->id]);
    $this->assertDatabaseMissing('construction_progress', ['id' => $progress->id]);
});

test('users outside pembangunan cannot access property management', function () {
    $division = Division::firstOrCreate(
        ['slug' => 'marketing'],
        ['name' => 'Marketing', 'is_active' => true]
    );
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);

    $this->actingAs($user)
        ->get(route('pembangunan.properties.index'))
        ->assertForbidden();
});

test('pembangunan team leads can access property management', function () {
    $user = createPropertyManagementUser('tl_pembangunan');

    $this->actingAs($user)
        ->get(route('pembangunan.properties.index'))
        ->assertOk();
});

test('pembangunan sidebar opens the construction stages management page', function () {
    $user = createPropertyManagementUser();
    $property = Property::create(['name' => 'Perumahan Tahapan']);
    $property->constructionStages()->create([
        'name' => 'Pekerjaan Pondasi',
        'order' => 1,
        'min_progress' => 1,
        'max_progress' => 20,
    ]);

    $this->actingAs($user)
        ->get(route('pembangunan.construction-stages.index'))
        ->assertOk()
        ->assertSee('Tahapan Pembangunan')
        ->assertSee('Perumahan Tahapan')
        ->assertSee('Pekerjaan Pondasi')
        ->assertSee(route('pembangunan.construction-stages.index'));
});

test('nested property routes reject blocks and lots from other properties', function () {
    $user = createPropertyManagementUser();
    $property = Property::create(['name' => 'Perumahan Satu']);
    $otherProperty = Property::create(['name' => 'Perumahan Dua']);
    $block = Block::create([
        'property_id' => $otherProperty->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => 'A-01',
        'house_price' => 350000000,
        'status' => 'available',
    ]);

    $this->actingAs($user)
        ->get(route('pembangunan.properties.blocks.lots.index', [$property, $block]))
        ->assertNotFound();
    $this->get(route('pembangunan.properties.blocks.lots.edit', [$otherProperty, $block, $lot]))
        ->assertOk();
    $this->get(route('pembangunan.properties.blocks.lots.edit', [$property, $block, $lot]))
        ->assertNotFound();
});

test('deleting a property hierarchy with progress data is refused', function () {
    $user = createPropertyManagementUser();
    $property = Property::create(['name' => 'Perumahan Aktif']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => 'A-01',
        'house_price' => 350000000,
        'status' => 'available',
    ]);
    $stage = ConstructionStage::create([
        'name' => 'Persiapan',
        'order' => 1,
        'min_progress' => 1,
        'max_progress' => 10,
    ]);
    $progress = ConstructionProgress::create([
        'lot_id' => $lot->id,
        'stage_id' => $stage->id,
        'progress' => 5,
        'updated_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->delete(route('pembangunan.properties.destroy', $property))
        ->assertRedirect(route('pembangunan.properties.index'))
        ->assertSessionHas('error');
    $this->delete(route('pembangunan.properties.blocks.destroy', [$property, $block]))
        ->assertRedirect(route('pembangunan.properties.blocks.index', $property))
        ->assertSessionHas('error');
    $this->delete(route('pembangunan.properties.blocks.lots.destroy', [$property, $block, $lot]))
        ->assertRedirect(route('pembangunan.properties.blocks.lots.index', [$property, $block]))
        ->assertSessionHas('error');

    $this->assertModelExists($property);
    $this->assertModelExists($block);
    $this->assertModelExists($lot);
    $this->assertModelExists($progress);
});

test('admin property management routes remain available', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.properties.index'))
        ->assertOk()
        ->assertSee(route('admin.properties.create'));
});
