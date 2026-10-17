<?php

use App\Models\Block;
use App\Models\Division;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('uploads a site plan when creating a property', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.properties.store'), [
            'name' => 'Mulia Residence',
            'site_plan' => UploadedFile::fake()->image('site-plan.png', 900, 600),
        ])
        ->assertRedirect(route('admin.properties.index'));

    $property = Property::query()->where('name', 'Mulia Residence')->firstOrFail();

    expect($property->site_plan_path)->not->toBeNull();
    Storage::disk('public')->assertExists($property->site_plan_path);
});

it('replaces a site plan and clears old lot map positions', function () {
    Storage::fake('public');
    Storage::disk('public')->put('site-plans/old.png', 'old plan');
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create([
        'name' => 'Perumahan Terpilih',
        'site_plan_path' => 'site-plans/old.png',
    ]);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'available',
        'map_coordinates' => ['x' => 10, 'y' => 15, 'width' => 5, 'height' => 8],
    ]);

    $this->actingAs($admin)
        ->put(route('admin.properties.update', $property), [
            'name' => $property->name,
            'site_plan' => UploadedFile::fake()->image('replacement.png', 900, 600),
        ])
        ->assertRedirect(route('admin.properties.index'));

    $property->refresh();

    expect($property->site_plan_path)->not->toBe('site-plans/old.png')
        ->and($lot->fresh()->map_coordinates)->toBeNull();
    Storage::disk('public')->assertMissing('site-plans/old.png');
    Storage::disk('public')->assertExists($property->site_plan_path);
});

it('rejects a non-image site plan upload', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.properties.store'), [
            'name' => 'Perumahan Tanpa Denah',
            'site_plan' => UploadedFile::fake()->create('site-plan.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('site_plan');

    expect(Property::query()->where('name', 'Perumahan Tanpa Denah')->exists())->toBeFalse();
});

it('saves validated map coordinates for a lot', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Terpilih']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'available',
    ]);
    $coordinates = ['x' => 10, 'y' => 15, 'width' => 5, 'height' => 8];

    $this->actingAs($admin)
        ->put(route('admin.properties.lots.map-coordinates.update', [$property, $lot]), [
            'coordinates' => $coordinates,
        ])
        ->assertRedirect();

    expect($lot->fresh()->map_coordinates)->toEqual($coordinates);
});

it('rejects map coordinates extending beyond the site plan', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan Terpilih']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'available',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.properties.lots.map-coordinates.update', [$property, $lot]), [
            'coordinates' => ['x' => 98, 'y' => 10, 'width' => 5, 'height' => 8],
        ])
        ->assertSessionHasErrors('coordinates');

    expect($lot->fresh()->map_coordinates)->toBeNull();
});

it('does not allow a lot from another property to be mapped', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Perumahan A']);
    $otherProperty = Property::create(['name' => 'Perumahan B']);
    $block = Block::create(['property_id' => $otherProperty->id, 'name' => 'B']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'available',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.properties.lots.map-coordinates.update', [$property, $lot]), [
            'coordinates' => ['x' => 10, 'y' => 15, 'width' => 5, 'height' => 8],
        ])
        ->assertNotFound();

    expect($lot->fresh()->map_coordinates)->toBeNull();
});

it('shows the selected property site plan on admin and marketing dashboards', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $marketingUser = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $property = Property::create([
        'name' => 'Perumahan Terpilih',
        'site_plan_path' => 'site-plans/selected.png',
    ]);
    $otherProperty = Property::create(['name' => 'Perumahan Lain']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $otherBlock = Block::create(['property_id' => $otherProperty->id, 'name' => 'B']);
    $selectedLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'available',
        'map_coordinates' => ['x' => 10, 'y' => 15, 'width' => 5, 'height' => 8],
    ]);
    Lot::create([
        'block_id' => $block->id,
        'lot_number' => '02',
        'house_price' => 500000000,
        'status' => 'booked',
        'map_coordinates' => ['x' => 20, 'y' => 15, 'width' => 5, 'height' => 8],
    ]);
    Lot::create([
        'block_id' => $otherBlock->id,
        'lot_number' => '03',
        'house_price' => 600000000,
        'status' => 'finish',
        'map_coordinates' => ['x' => 30, 'y' => 15, 'width' => 5, 'height' => 8],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard', ['property_id' => $property->id]))
        ->assertSee('Denah Perumahan')
        ->assertSee('A/01')
        ->assertSee('A/02')
        ->assertDontSee('B/03')
        ->assertSee('Tersedia')
        ->assertViewHas('sitePlanLots', fn ($lots): bool => $lots->count() === 2);

    $this->actingAs($marketingUser)
        ->get(route('marketing.dashboard', ['property_id' => $property->id, 'lot_id' => $selectedLot->id]))
        ->assertSee('Denah Perumahan')
        ->assertSee('A/01')
        ->assertSee('A/02')
        ->assertDontSee('B/03')
        ->assertSee(route('marketing.site-plans.edit', $property))
        ->assertViewHas('sitePlanLots', fn ($lots): bool => $lots->count() === 2);
});

it('allows marketing staff to open the site plan manager for a property', function () {
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $marketingUser = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $property = Property::create([
        'name' => 'Perumahan Marketing',
        'site_plan_path' => 'site-plans/marketing.png',
    ]);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'available',
    ]);

    $this->actingAs($marketingUser)
        ->get(route('marketing.site-plans.edit', $property))
        ->assertSee('Kelola denah perumahan')
        ->assertSee('Simpan Gambar Denah')
        ->assertSee('Tandai Area Kavling');

    $this->put(route('marketing.site-plans.update', $property), [
        'site_plan' => UploadedFile::fake()->create('denah.pdf', 100, 'application/pdf'),
    ])->assertSessionHasErrors('site_plan');
});

it('lets marketing replace a site plan and clears old mapped positions', function () {
    Storage::fake('public');
    Storage::disk('public')->put('site-plans/old-marketing.png', 'old plan');
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $marketingUser = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $property = Property::create([
        'name' => 'Perumahan Marketing',
        'site_plan_path' => 'site-plans/old-marketing.png',
    ]);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'available',
        'map_coordinates' => ['x' => 10, 'y' => 15, 'width' => 5, 'height' => 8],
    ]);

    $this->actingAs($marketingUser)
        ->put(route('marketing.site-plans.update', $property), [
            'site_plan' => UploadedFile::fake()->image('replacement.png', 900, 600),
        ])
        ->assertRedirect(route('marketing.site-plans.edit', $property));

    $property->refresh();

    expect($property->site_plan_path)->not->toBe('site-plans/old-marketing.png')
        ->and($lot->fresh()->map_coordinates)->toBeNull();
    Storage::disk('public')->assertMissing('site-plans/old-marketing.png');
    Storage::disk('public')->assertExists($property->site_plan_path);
});

it('lets marketing save a lot position but prevents mapping a lot from another property', function () {
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $marketingUser = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Marketing']);
    $otherProperty = Property::create(['name' => 'Perumahan Lain']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $otherBlock = Block::create(['property_id' => $otherProperty->id, 'name' => 'B']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'available',
    ]);
    $otherLot = Lot::create([
        'block_id' => $otherBlock->id,
        'lot_number' => '02',
        'house_price' => 500000000,
        'status' => 'available',
    ]);
    $coordinates = ['x' => 10, 'y' => 15, 'width' => 5, 'height' => 8];

    $this->actingAs($marketingUser)
        ->put(route('marketing.site-plans.coordinates.update', [$property, $lot]), [
            'coordinates' => $coordinates,
        ])
        ->assertRedirect();

    expect($lot->fresh()->map_coordinates)->toEqual($coordinates);

    $this->put(route('marketing.site-plans.coordinates.update', [$property, $otherLot]), [
        'coordinates' => $coordinates,
    ])->assertNotFound();

    expect($otherLot->fresh()->map_coordinates)->toBeNull();
});

it('denies site plan management to users outside marketing', function () {
    $division = Division::create([
        'name' => 'Finance',
        'slug' => 'finance',
        'is_active' => true,
    ]);
    $financeUser = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Tertutup']);

    $this->actingAs($financeUser)
        ->get(route('marketing.site-plans.edit', $property))
        ->assertForbidden();
});
