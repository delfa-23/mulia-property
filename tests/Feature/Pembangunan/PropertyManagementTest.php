<?php

use App\Models\Block;
use App\Models\ConstructionProgress;
use App\Models\ConstructionStage;
use App\Models\Division;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createPropertyManagementUser(string $role = 'admin'): User
{
    return User::factory()->create([
        'role' => $role,
    ]);
}

test('admin can manage properties blocks and lots', function () {
    $user = createPropertyManagementUser();

    $this->actingAs($user)
        ->get(route('admin.properties.index'))
        ->assertOk()
        ->assertSee('Perumahan, Block & Kavling');
    $this->get(route('admin.properties.create'))
        ->assertOk();

    $this->post(route('admin.properties.store'), [
        'name' => 'Perumahan Baru',
        'address' => 'Jl. Contoh',
        'description' => 'Deskripsi',
    ])->assertRedirect(route('admin.properties.index'));

    $property = Property::query()->where('name', 'Perumahan Baru')->firstOrFail();
    $this->assertModelExists($property);

    $this->get(route('admin.properties.edit', $property))
        ->assertSee('value="Perumahan Baru"', false)
        ->assertSee('value="Jl. Contoh"', false)
        ->assertSee('>Deskripsi</textarea>', false);
    $this->put(route('admin.properties.update', $property), [
        'name' => 'Perumahan Diperbarui',
        'address' => 'Jl. Baru',
        'description' => 'Deskripsi baru',
    ])->assertRedirect(route('admin.properties.index'));
    $this->assertDatabaseHas('properties', [
        'id' => $property->id,
        'name' => 'Perumahan Diperbarui',
        'address' => 'Jl. Baru',
    ]);

    $this->get(route('admin.properties.blocks.create', $property))
        ->assertOk();
    $this->post(route('admin.properties.blocks.store', $property), [
        'name' => 'A',
        'description' => 'Block pertama',
    ])->assertRedirect(route('admin.properties.blocks.index', $property));

    $block = Block::query()->where('property_id', $property->id)->firstOrFail();

    $this->get(route('admin.properties.blocks.edit', [$property, $block]))
        ->assertSee('value="A"', false)
        ->assertSee('>Block pertama</textarea>', false);
    $this->put(route('admin.properties.blocks.update', [$property, $block]), [
        'name' => 'B',
        'description' => 'Block diperbarui',
    ])->assertRedirect(route('admin.properties.blocks.index', $property));
    $this->assertDatabaseHas('blocks', [
        'id' => $block->id,
        'name' => 'B',
    ]);

    $this->get(route('admin.properties.blocks.lots.create', [$property, $block]))
        ->assertSee('B-', false)
        ->assertSee('name="lot_number"', false)
        ->assertSee('placeholder="01"', false);
    $this->post(route('admin.properties.blocks.lots.store', [$property, $block]), [
        'lot_number' => '01',
        'house_price' => 350000000,
        'status' => 'available',
        'notes' => 'Kavling pertama',
    ])->assertRedirect(route('admin.properties.blocks.lots.index', [$property, $block]));

    $lot = Lot::query()->where('block_id', $block->id)->firstOrFail();
    $this->assertDatabaseHas('lots', [
        'id' => $lot->id,
        'lot_number' => 'B-01',
    ]);

    $this->get(route('admin.properties.blocks.lots.edit', [$property, $block, $lot]))
        ->assertSee('B-', false)
        ->assertSee('value="01"', false)
        ->assertSee('value="350000000.00"', false)
        ->assertSee('value="available" selected', false)
        ->assertSee('>Kavling pertama</textarea>', false);
    $this->put(route('admin.properties.blocks.lots.update', [$property, $block, $lot]), [
        'lot_number' => '02',
        'house_price' => 400000000,
        'status' => 'available',
        'notes' => 'Kavling diperbarui',
    ])->assertRedirect(route('admin.properties.blocks.lots.index', [$property, $block]));
    $this->assertDatabaseHas('lots', [
        'id' => $lot->id,
        'lot_number' => 'B-02',
        'house_price' => 400000000,
    ]);

    $this->delete(route('admin.properties.blocks.lots.destroy', [$property, $block, $lot]))
        ->assertRedirect(route('admin.properties.blocks.lots.index', [$property, $block]));
    $this->delete(route('admin.properties.blocks.destroy', [$property, $block]))
        ->assertRedirect(route('admin.properties.blocks.index', $property));
    $this->delete(route('admin.properties.destroy', $property))
        ->assertRedirect(route('admin.properties.index'));

    $this->assertDatabaseMissing('properties', ['id' => $property->id]);
    $this->assertDatabaseMissing('blocks', ['id' => $block->id]);
    $this->assertDatabaseMissing('lots', ['id' => $lot->id]);
});

test('admin can upload, add, and remove lot photos', function () {
    Storage::fake('site-plans');
    $user = createPropertyManagementUser();
    $property = Property::create(['name' => 'Perumahan Foto Kavling']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);

    $this->actingAs($user)
        ->get(route('admin.properties.blocks.lots.create', [$property, $block]))
        ->assertSee('name="images[]"', false)
        ->assertSee('+ Tambah foto')
        ->assertSee('multipart/form-data');

    $this->post(route('admin.properties.blocks.lots.store', [$property, $block]), [
        'lot_number' => '01',
        'house_price' => 350000000,
        'status' => 'available',
        'images' => [UploadedFile::fake()->create('lot.pdf', 100, 'application/pdf')],
    ])->assertSessionHasErrors('images.0');

    $this->assertDatabaseMissing('lots', ['block_id' => $block->id]);

    $this->post(route('admin.properties.blocks.lots.store', [$property, $block]), [
        'lot_number' => '01',
        'house_price' => 350000000,
        'status' => 'available',
        'images' => [
            UploadedFile::fake()->image('lot.jpg'),
            UploadedFile::fake()->image('lot-side.jpg'),
        ],
    ])->assertRedirect(route('admin.properties.blocks.lots.index', [$property, $block]));

    $lot = Lot::query()->where('block_id', $block->id)->firstOrFail();
    $firstImagePath = $lot->image_path;
    $secondImagePath = $lot->image_paths[0];

    expect($firstImagePath)->toStartWith('lots/')
        ->and($lot->imagePaths())->toHaveCount(2);
    Storage::disk('site-plans')->assertExists($firstImagePath);
    Storage::disk('site-plans')->assertExists($secondImagePath);

    $this->put(route('admin.properties.blocks.lots.update', [$property, $block, $lot]), [
        'lot_number' => '01',
        'house_price' => 350000000,
        'status' => 'available',
        'remove_images' => ['lots/not-owned.jpg'],
    ])->assertSessionHasErrors('remove_images.0');

    Storage::disk('site-plans')->assertExists($firstImagePath);
    Storage::disk('site-plans')->assertExists($secondImagePath);

    $this->put(route('admin.properties.blocks.lots.update', [$property, $block, $lot]), [
        'lot_number' => '01',
        'house_price' => 350000000,
        'status' => 'available',
        'images' => [null, UploadedFile::fake()->image('additional.jpg')],
        'remove_images' => [$firstImagePath],
    ])->assertRedirect(route('admin.properties.blocks.lots.index', [$property, $block]));

    $lot->refresh();

    expect($lot->imagePaths())->toHaveCount(2)
        ->and($lot->image_path)->toBe($secondImagePath);
    Storage::disk('site-plans')->assertMissing($firstImagePath);
    Storage::disk('site-plans')->assertExists($secondImagePath);
    Storage::disk('site-plans')->assertExists($lot->image_paths[0]);
});

test('admin saves house prices entered with Indonesian separators', function (string $housePrice) {
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
        ->put(route('admin.properties.blocks.lots.update', [$property, $block, $lot]), [
            'lot_number' => 'A-01',
            'house_price' => $housePrice,
            'status' => 'available',
        ])
        ->assertRedirect(route('admin.properties.blocks.lots.index', [$property, $block]));

    $this->assertDatabaseHas('lots', [
        'id' => $lot->id,
        'house_price' => '4000000000.00',
    ]);
})->with([
    'grouped amount' => '4.000.000.000',
    'currency-prefixed grouped amount' => 'Rp 4.000.000.000',
]);

test('admin can add construction stages using the existing percentage as bobot per property', function () {
    $user = createPropertyManagementUser();
    $property = Property::create(['name' => 'Perumahan Tahap Satu']);
    $otherProperty = Property::create(['name' => 'Perumahan Tahap Dua']);

    $this->actingAs($user)
        ->post(route('admin.property-construction-stages.store', $property), [
            'name' => 'Pondasi Tahap Satu',
            'order' => 1,
            'max_progress' => 4,
            'description' => 'Pekerjaan pondasi',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');
    $this->post(route('admin.property-construction-stages.store', $otherProperty), [
        'name' => 'Pondasi Tahap Dua',
        'order' => 1,
        'max_progress' => 4,
    ])->assertRedirect()->assertSessionHas('success');

    $stage = $property->constructionStages()->firstOrFail();

    $this->assertDatabaseHas('construction_stages', [
        'id' => $stage->id,
        'property_id' => $property->id,
        'name' => 'Pondasi Tahap Satu',
        'max_progress' => '4.00',
    ]);
});

test('admin can use the existing percentage as bobot for separate property stages', function () {
    $user = createPropertyManagementUser();
    $property = Property::create(['name' => 'Perumahan Range']);
    $property->constructionStages()->create([
        'name' => 'Pekerjaan Awal',
        'order' => 1,
        'max_progress' => 25,
    ]);

    $this->actingAs($user)
        ->post(route('admin.property-construction-stages.store', $property), [
            'name' => 'Pekerjaan Berikutnya',
            'order' => 2,
            'max_progress' => 25,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('construction_stages', [
        'property_id' => $property->id,
        'name' => 'Pekerjaan Berikutnya',
        'max_progress' => '25.00',
    ]);
});

test('admin can edit a property construction stage', function () {
    $user = createPropertyManagementUser();
    $property = Property::create(['name' => 'Perumahan Edit Tahapan']);
    $stage = $property->constructionStages()->create([
        'name' => 'Pekerjaan Awal',
        'order' => 1,
        'max_progress' => 4,
        'description' => 'Sebelum perubahan',
    ]);

    $this->actingAs($user)
        ->get(route('admin.property-construction-stages.index'))
        ->assertSee('Edit')
        ->assertSee(route('admin.property-construction-stages.update', [$property, $stage]), false);

    $this->put(route('admin.property-construction-stages.update', [$property, $stage]), [
        'name' => 'Pekerjaan Struktur',
        'order' => 2,
        'max_progress' => 6.25,
        'description' => 'Tahap struktur',
    ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Tahapan pembangunan berhasil diperbarui di '.$property->name.'.');

    $this->assertDatabaseHas('construction_stages', [
        'id' => $stage->id,
        'property_id' => $property->id,
        'name' => 'Pekerjaan Struktur',
        'order' => 2,
        'max_progress' => '6.25',
        'description' => 'Tahap struktur',
    ]);
});

test('construction staff can fill progress but cannot manage construction stages', function () {
    $division = Division::firstOrCreate(
        ['slug' => 'pembangunan'],
        ['name' => 'Pembangunan', 'is_active' => true]
    );
    $user = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Tim Pembangunan']);
    $stage = $property->constructionStages()->create([
        'name' => 'Pekerjaan Pondasi',
        'order' => 1,
        'max_progress' => 30,
    ]);

    $this->actingAs($user)
        ->get(route('pembangunan.progress.index'))
        ->assertOk();
    $this->get(route('admin.property-construction-stages.index'))->assertForbidden();
    $this->post(route('admin.property-construction-stages.store', $property), [
        'name' => 'Pondasi Baru',
        'order' => 2,
        'max_progress' => 10,
    ])->assertForbidden();
    $this->put(route('admin.property-construction-stages.update', [$property, $stage]), [
        'name' => 'Pondasi Selesai',
        'order' => 1,
        'max_progress' => 30,
    ])->assertForbidden();
    $this->delete(route('admin.property-construction-stages.destroy', [$property, $stage]))
        ->assertForbidden();

    $this->assertDatabaseHas('construction_stages', [
        'id' => $stage->id,
        'property_id' => $property->id,
        'name' => 'Pekerjaan Pondasi',
    ]);
});

test('admin cannot edit a property construction stage owned by another property', function () {
    $user = createPropertyManagementUser();
    $property = Property::create(['name' => 'Perumahan Pertama']);
    $otherProperty = Property::create(['name' => 'Perumahan Kedua']);
    $stage = $otherProperty->constructionStages()->create([
        'name' => 'Tahap Milik Kedua',
        'order' => 1,
        'min_progress' => 1,
        'max_progress' => 20,
    ]);

    $this->actingAs($user)
        ->put(route('admin.property-construction-stages.update', [$property, $stage]), [
            'name' => 'Tahap Diubah',
            'order' => 1,
            'min_progress' => 1,
            'max_progress' => 20,
        ])
        ->assertNotFound();

    $this->assertDatabaseHas('construction_stages', [
        'id' => $stage->id,
        'name' => 'Tahap Milik Kedua',
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
        ->delete(route('admin.property-construction-stages.destroy', [$property, $stage]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('construction_stages', ['id' => $stage->id]);
    $this->assertDatabaseMissing('construction_progress', ['id' => $progress->id]);
});

test('marketing users cannot access admin property management', function () {
    $division = Division::firstOrCreate(
        ['slug' => 'marketing'],
        ['name' => 'Marketing', 'is_active' => true]
    );
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);

    $this->actingAs($user)
        ->get(route('admin.properties.index'))
        ->assertForbidden();
});

test('pembangunan team leads cannot access admin property management', function () {
    $user = createPropertyManagementUser('tl_pembangunan');

    $this->actingAs($user)
        ->get(route('admin.properties.index'))
        ->assertForbidden();
});

test('admin sidebar opens property construction stage management', function () {
    $user = createPropertyManagementUser();
    $property = Property::create(['name' => 'Perumahan Tahapan']);
    $property->constructionStages()->create([
        'name' => 'Pekerjaan Pondasi',
        'order' => 1,
        'min_progress' => 1,
        'max_progress' => 20,
    ]);

    $this->actingAs($user)
        ->get(route('admin.property-construction-stages.index'))
        ->assertOk()
        ->assertSee('Tahapan Pembangunan')
        ->assertSee('Perumahan Tahapan')
        ->assertSee('Pekerjaan Pondasi')
        ->assertSee(route('admin.property-construction-stages.index'));
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
        ->get(route('admin.properties.blocks.lots.index', [$property, $block]))
        ->assertNotFound();
    $this->get(route('admin.properties.blocks.lots.edit', [$otherProperty, $block, $lot]))
        ->assertOk();
    $this->get(route('admin.properties.blocks.lots.edit', [$property, $block, $lot]))
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
        ->delete(route('admin.properties.destroy', $property))
        ->assertRedirect(route('admin.properties.index'))
        ->assertSessionHas('error');
    $this->delete(route('admin.properties.blocks.destroy', [$property, $block]))
        ->assertRedirect(route('admin.properties.blocks.index', $property))
        ->assertSessionHas('error');
    $this->delete(route('admin.properties.blocks.lots.destroy', [$property, $block, $lot]))
        ->assertRedirect(route('admin.properties.blocks.lots.index', [$property, $block]))
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
