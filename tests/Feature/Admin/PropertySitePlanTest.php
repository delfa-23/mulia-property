<?php

use App\Models\Block;
use App\Models\Division;
use App\Models\Lot;
use App\Models\Property;
use App\Models\SitePlanArea;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('uploads a site plan when creating a property', function () {
    Storage::fake('site-plans');
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.properties.store'), [
            'name' => 'Mulia Residence',
            'site_plan' => UploadedFile::fake()->image('site-plan.png', 900, 600),
        ])
        ->assertRedirect(route('admin.properties.index'));

    $property = Property::query()->where('name', 'Mulia Residence')->firstOrFail();

    expect($property->site_plan_path)->not->toBeNull();
    expect($property->site_plan_path)->not->toContain('/');
    Storage::disk('site-plans')->assertExists($property->site_plan_path);
    expect($property->sitePlanUrl())->toBe(Storage::disk('site-plans')->url($property->site_plan_path));
});

it('serves existing site plans from Laravel public storage when the new disk has no file', function () {
    Storage::fake('site-plans');
    Storage::fake('public');
    Storage::disk('public')->put('site-plans/existing.png', 'existing plan');
    $property = new Property(['site_plan_path' => 'site-plans/existing.png']);

    expect($property->sitePlanUrl())->toBe(Storage::disk('public')->url('site-plans/existing.png'));
});

it('replaces a site plan without deleting existing rectangle mappings or the old image', function () {
    Storage::fake('site-plans');
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
        ->and($lot->fresh()->map_coordinates)->toBe([
            'x' => 10.0,
            'y' => 15.0,
            'width' => 5.0,
            'height' => 8.0,
        ])
        ->and(SitePlanArea::query()->where('lot_id', $lot->id)->exists())->toBeFalse();
    Storage::disk('public')->assertExists('site-plans/old.png');
    Storage::disk('site-plans')->assertExists($property->site_plan_path);

    $this->actingAs($admin)
        ->get(route('admin.properties.edit', $property))
        ->assertOk()
        ->assertSee('data-site-plan-mapped-lot="'.$lot->id.'"', false);
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

it('saves a manually drawn rectangle and advances to the next lot', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create([
        'name' => 'Perumahan Terpilih',
        'site_plan_path' => 'site-plans/legacy.png',
    ]);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'available',
    ]);
    $nextLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '02',
        'house_price' => 500000000,
        'status' => 'available',
    ]);
    $coordinates = ['x' => 10, 'y' => 15, 'width' => 5, 'height' => 8];

    $this->actingAs($admin)
        ->put(route('admin.properties.lots.map-coordinates.update', [$property, $lot]), [
            'coordinates' => $coordinates,
        ])
        ->assertRedirect(route('admin.properties.edit', [
            'property' => $property,
            'lot_id' => $nextLot->id,
        ]).'#site-plan-editor')
        ->assertSessionHas('success', 'Posisi kavling berhasil disimpan. Lanjutkan ke kavling 02.');

    expect($lot->fresh()->map_coordinates)->toBe([
        'x' => 10.0,
        'y' => 15.0,
        'width' => 5.0,
        'height' => 8.0,
    ])->and(SitePlanArea::query()->where('lot_id', $lot->id)->exists())->toBeFalse();
});

it('saves a rectangle through the quick mapping response without redirecting', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create([
        'name' => 'Perumahan Pemetaan Cepat',
        'site_plan_path' => 'site-plans/quick-map.png',
    ]);
    $block = Block::create(['property_id' => $property->id, 'name' => 'B']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '07',
        'house_price' => 500000000,
        'status' => 'available',
    ]);

    $response = $this->actingAs($admin)
        ->putJson(route('admin.properties.lots.map-coordinates.update', [$property, $lot]), [
            'coordinates' => ['x' => 12, 'y' => 18, 'width' => 6, 'height' => 9],
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Posisi kavling berhasil disimpan.')
        ->assertJsonPath('lot.label', 'B/07')
        ->assertJsonPath('mapped_lot_count', 1)
        ->assertJsonPath('total_lot_count', 1)
        ->assertJsonPath('lot.coordinates.width', fn (int|float $width): bool => (float) $width === 6.0);
    expect($lot->fresh()->map_coordinates)->toBe([
        'x' => 12.0,
        'y' => 18.0,
        'width' => 6.0,
        'height' => 9.0,
    ]);
});

it('returns a validation error for an out-of-bounds quick rectangle', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create([
        'name' => 'Perumahan Batas Pemetaan',
        'site_plan_path' => 'site-plans/bounds.png',
    ]);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'available',
    ]);

    $this->actingAs($admin)
        ->putJson(route('admin.properties.lots.map-coordinates.update', [$property, $lot]), [
            'coordinates' => ['x' => 98, 'y' => 10, 'width' => 5, 'height' => 8],
        ])
        ->assertInvalid(['coordinates']);

    expect($lot->fresh()->map_coordinates)->toBeNull();
});

it('shows the rectangle drawing editor for a property with a site plan', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create([
        'name' => 'Perumahan Kotak',
        'site_plan_path' => 'site-plans/rectangles.png',
    ]);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'available',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.properties.edit', $property))
        ->assertOk()
        ->assertSee('data-site-plan-editor', false)
        ->assertSee('data-site-plan-canvas', false)
        ->assertSee('data-site-plan-lot-picker', false)
        ->assertSee('Simpan Area Kavling')
        ->assertDontSee('data-site-plan-polygon-form', false);
});

it('lets an admin save a polygon linked to the selected property lot', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create([
        'name' => 'Perumahan Poligon',
        'site_plan_path' => 'site-plans/polygon.png',
    ]);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'available',
        'map_coordinates' => ['x' => 1, 'y' => 1, 'width' => 10, 'height' => 8],
    ]);
    $polygon = [
        ['x' => 12, 'y' => 18],
        ['x' => 19, 'y' => 18],
        ['x' => 19, 'y' => 24],
        ['x' => 12, 'y' => 24],
    ];

    $this->actingAs($admin)
        ->post(route('admin.properties.site-plan-areas.store', $property), [
            'lot_id' => $lot->id,
            'label' => 'Blok A/01',
            'polygon' => $polygon,
        ])
        ->assertRedirect();

    $area = SitePlanArea::query()->where('property_id', $property->id)->firstOrFail();

    expect($area->polygon)->toEqual([
        ['x' => 12.0, 'y' => 18.0],
        ['x' => 19.0, 'y' => 18.0],
        ['x' => 19.0, 'y' => 24.0],
        ['x' => 12.0, 'y' => 24.0],
    ])
        ->and($area->lot_id)->toBe($lot->id)
        ->and($area->site_plan_path)->toBe('site-plans/polygon.png');
    expect($lot->fresh()->map_coordinates)->toBeNull();

    $this->get(route('site-plans.index', ['property_id' => $property->id]))
        ->assertOk()
        ->assertSee('data-area-id="'.$area->id.'"', false)
        ->assertSee('data-lot-id="'.$lot->id.'"', false)
        ->assertSee('data-lot-label="A/01"', false)
        ->assertSee('data-lot-mapped="true"', false);

    $this->put(route('admin.properties.site-plan-areas.update', [$property, $area]), [
        'lot_id' => $lot->id,
        'label' => 'Blok A/01 revisi',
        'polygon' => [
            ['x' => 11, 'y' => 17],
            ['x' => 20, 'y' => 17],
            ['x' => 20, 'y' => 25],
            ['x' => 11, 'y' => 25],
        ],
    ])->assertRedirect();

    expect($area->fresh()->label)->toBe('Blok A/01 revisi')
        ->and($area->fresh()->polygon[0])->toEqual(['x' => 11.0, 'y' => 17.0]);
});

it('stores an unlinked polygon label for a public facility', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create([
        'name' => 'Perumahan Fasilitas',
        'site_plan_path' => 'site-plans/facility.png',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.properties.site-plan-areas.store', $property), [
            'label' => 'Musholla',
            'polygon' => [
                ['x' => 4, 'y' => 5],
                ['x' => 9, 'y' => 5],
                ['x' => 9, 'y' => 10],
                ['x' => 4, 'y' => 10],
            ],
        ])
        ->assertRedirect();

    $area = SitePlanArea::query()->where('property_id', $property->id)->firstOrFail();

    expect($area->lot_id)->toBeNull()
        ->and($area->label)->toBe('Musholla');

    $this->get(route('site-plans.index', ['property_id' => $property->id]))
        ->assertOk()
        ->assertSee('data-lot-label="Musholla"', false)
        ->assertSee('data-lot-mapped="false"', false)
        ->assertSee('Area ini belum dihubungkan dengan record kavling.');
});

it('rejects linking an area to a lot from a different property', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create([
        'name' => 'Perumahan Satu',
        'site_plan_path' => 'site-plans/one.png',
    ]);
    $otherProperty = Property::create(['name' => 'Perumahan Dua', 'site_plan_path' => 'site-plans/two.png']);
    $otherBlock = Block::create(['property_id' => $otherProperty->id, 'name' => 'B']);
    $otherLot = Lot::create([
        'block_id' => $otherBlock->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'available',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.properties.site-plan-areas.store', $property), [
            'lot_id' => $otherLot->id,
            'polygon' => [
                ['x' => 4, 'y' => 5],
                ['x' => 9, 'y' => 5],
                ['x' => 9, 'y' => 10],
            ],
        ])
        ->assertSessionHasErrors('lot_id');

    expect(SitePlanArea::query()->where('property_id', $property->id)->exists())->toBeFalse();
});

it('prevents marketing from changing a polygon mapping', function () {
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
        'name' => 'Perumahan Read Only',
        'site_plan_path' => 'site-plans/read-only.png',
    ]);

    $this->actingAs($marketingUser)
        ->post(route('admin.properties.site-plan-areas.store', $property), [
            'label' => 'Area ilegal',
            'polygon' => [
                ['x' => 4, 'y' => 5],
                ['x' => 9, 'y' => 5],
                ['x' => 9, 'y' => 10],
            ],
        ])
        ->assertForbidden();

    expect(SitePlanArea::query()->where('property_id', $property->id)->exists())->toBeFalse();
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
        ->assertSee(route('site-plans.index'))
        ->assertViewHas('sitePlanLots', fn ($lots): bool => $lots->count() === 2);
});

it('shows a dedicated site plan selector to admin and marketing', function () {
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
        'name' => 'Perumahan Siteplan Pilihan',
        'site_plan_path' => 'site-plans/selected.png',
    ]);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $availableLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'available',
        'image_path' => 'lots/lot-01.png',
        'map_coordinates' => ['x' => 10, 'y' => 15, 'width' => 5, 'height' => 8],
    ]);
    $bookedLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '02',
        'house_price' => 500000000,
        'status' => 'booked',
        'map_coordinates' => ['x' => 20, 'y' => 15, 'width' => 5, 'height' => 8],
    ]);
    foreach ([['03', 'process'], ['04', 'akad']] as [$lotNumber, $status]) {
        Lot::create([
            'block_id' => $block->id,
            'lot_number' => $lotNumber,
            'house_price' => 500000000,
            'status' => $status,
            'map_coordinates' => ['x' => 20, 'y' => 15, 'width' => 5, 'height' => 8],
        ]);
    }

    $this->actingAs($admin)
        ->get(route('admin.site-plans.index', ['property_id' => $property->id]))
        ->assertOk()
        ->assertSee('Perumahan Siteplan Pilihan')
        ->assertSee('data-site-plan-modal', false)
        ->assertSee('data-lot-images=', false)
        ->assertSee('fill="#ffffff"', false)
        ->assertSee('data-lot-status="Booking"', false)
        ->assertSee('fill="#22c55e"', false)
        ->assertSee('data-lot-status="SP3"', false)
        ->assertSee('fill="#facc15"', false)
        ->assertSee('data-lot-status="Akad"', false)
        ->assertSee('fill="#ef4444"', false)
        ->assertSee(route('admin.properties.blocks.lots.edit', [$property, $block, $availableLot]), false);

    $this->actingAs($marketingUser)
        ->get(route('marketing.site-plans.index', ['property_id' => $property->id]))
        ->assertOk()
        ->assertSee('Perumahan Siteplan Pilihan')
        ->assertSee('data-site-plan-modal', false)
        ->assertSee('data-lot-images=', false)
        ->assertSee('fill="#ffffff"', false)
        ->assertSee('data-lot-status="Booking"', false)
        ->assertSee('data-lot-status="SP3"', false)
        ->assertSee('data-lot-status="Akad"', false)
        ->assertSee(route('marketing.bookings.create', ['property_id' => $property->id, 'lot_id' => $availableLot->id]))
        ->assertSee('data-lot-action-label="Tambah booking"', false)
        ->assertDontSee(route('marketing.bookings.create', ['property_id' => $property->id, 'lot_id' => $bookedLot->id]));
});

it('lets other assigned divisions view the siteplan without management controls', function () {
    $division = Division::create([
        'name' => 'Finance',
        'slug' => 'finance',
        'is_active' => true,
    ]);
    $financeUser = User::factory()->create([
        'role' => 'staff_finance',
        'division_id' => $division->id,
    ]);
    $property = Property::create([
        'name' => 'Perumahan Baca Saja',
        'site_plan_path' => 'site-plans/read-only.png',
    ]);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'available',
        'map_coordinates' => ['x' => 10, 'y' => 15, 'width' => 5, 'height' => 8],
    ]);

    $this->actingAs($financeUser)
        ->get(route('site-plans.index', ['property_id' => $property->id]))
        ->assertOk()
        ->assertSee('Perumahan Baca Saja')
        ->assertDontSee('Kelola Denah')
        ->assertDontSee('data-lot-action-url="'.route('admin.properties.blocks.lots.edit', [$property, $block, $property->lots()->first()]).'"', false);

    $this->get(route('admin.site-plans.index'))
        ->assertForbidden();

    $this->get(route('marketing.site-plans.edit', $property))
        ->assertForbidden();
});

it('redirects marketing to the read-only siteplan viewer', function () {
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
        ->assertRedirect(route('site-plans.index', ['property_id' => $property->id]));

    $this->put('/marketing/properties/'.$property->id.'/site-plan', [
        'site_plan' => UploadedFile::fake()->image('denah.png'),
    ])->assertStatus(405);
});

it('does not allow marketing to replace a siteplan or clear mapped positions', function () {
    Storage::fake('site-plans');
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
        ->put('/marketing/properties/'.$property->id.'/site-plan', [
            'site_plan' => UploadedFile::fake()->image('replacement.png', 900, 600),
        ])
        ->assertStatus(405);

    $property->refresh();

    expect($property->site_plan_path)->toBe('site-plans/old-marketing.png')
        ->and($lot->fresh()->map_coordinates)->toEqual(['x' => 10, 'y' => 15, 'width' => 5, 'height' => 8]);
});

it('does not allow marketing to write mapping coordinates', function () {
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
        ->put('/marketing/properties/'.$property->id.'/lots/'.$lot->id.'/map-coordinates', [
            'coordinates' => $coordinates,
        ])
        ->assertNotFound();

    expect($lot->fresh()->map_coordinates)->toBeNull();
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
