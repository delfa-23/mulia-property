<?php

use App\Models\Budget;
use App\Models\CommonFacility;
use App\Models\CommonFacilityHistory;
use App\Models\Division;
use App\Models\Property;
use App\Models\User;

test('construction staff can update facility progress and see its history', function () {
    $division = Division::firstOrCreate(
        ['slug' => 'pembangunan'],
        ['name' => 'Pembangunan', 'is_active' => true]
    );
    $user = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Fasilitas']);
    $facility = CommonFacility::create([
        'property_id' => $property->id,
        'name' => 'Taman Utama',
        'budget' => 10000000,
        'realization' => 2000000,
        'created_by' => $user->id,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'facility_id' => $facility->id,
        'purpose' => 'pembangunan',
        'name' => 'Pengerjaan Taman',
        'category' => 'Lanskap',
        'amount' => 20000000,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->put(route('pembangunan.progress.facilities.update', $facility), [
            'realization' => 12500000,
            'notes' => 'Pemasangan paving selesai.',
        ])
        ->assertRedirect(route('pembangunan.progress.facilities.show', $facility))
        ->assertSessionHas('success', 'Progress fasilitas umum berhasil diperbarui.');

    $this->assertDatabaseHas('common_facilities', [
        'id' => $facility->id,
        'realization' => '12500000.00',
        'status' => 'in_progress',
    ]);
    $this->assertDatabaseHas('common_facility_histories', [
        'common_facility_id' => $facility->id,
        'previous_realization' => '2000000.00',
        'realization' => '12500000.00',
        'updated_by' => $user->id,
        'notes' => 'Pemasangan paving selesai.',
    ]);
    $this->get(route('pembangunan.progress.facilities.show', $facility))
        ->assertSee('Pengerjaan Taman')
        ->assertSee('Pemasangan paving selesai.')
        ->assertSee('62.50%');

    expect(CommonFacilityHistory::query()->where('common_facility_id', $facility->id)->count())->toBe(1);
});

test('construction staff cannot report facility realization above its RAB', function () {
    $division = Division::firstOrCreate(
        ['slug' => 'pembangunan'],
        ['name' => 'Pembangunan', 'is_active' => true]
    );
    $user = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Batas Fasilitas']);
    $facility = CommonFacility::create([
        'property_id' => $property->id,
        'name' => 'Pos Keamanan',
        'budget' => 10000000,
        'created_by' => $user->id,
    ]);
    Budget::create([
        'property_id' => $property->id,
        'facility_id' => $facility->id,
        'purpose' => 'pembangunan',
        'name' => 'RAB Pos Keamanan',
        'category' => 'Struktur',
        'amount' => 5000000,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->from(route('pembangunan.progress.facilities.show', $facility))
        ->put(route('pembangunan.progress.facilities.update', $facility), [
            'realization' => 6000000,
            'notes' => 'Melebihi anggaran.',
        ])
        ->assertRedirect(route('pembangunan.progress.facilities.show', $facility))
        ->assertSessionHasErrors([
            'realization' => 'Realisasi tidak boleh melebihi anggaran RAB fasilitas.',
        ]);

    $this->assertDatabaseHas('common_facilities', [
        'id' => $facility->id,
        'realization' => '0.00',
        'status' => 'planned',
    ]);
    $this->assertDatabaseMissing('common_facility_histories', [
        'common_facility_id' => $facility->id,
    ]);
});

test('marketing staff cannot access facility progress', function () {
    $division = Division::firstOrCreate(
        ['slug' => 'marketing'],
        ['name' => 'Marketing', 'is_active' => true]
    );
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);

    $this->actingAs($user)
        ->get(route('pembangunan.progress.facilities.index'))
        ->assertForbidden();
});

test('construction staff can create and delete facility progress without deleting its history', function () {
    $division = Division::firstOrCreate(
        ['slug' => 'pembangunan'],
        ['name' => 'Pembangunan', 'is_active' => true]
    );
    $user = User::factory()->create([
        'role' => 'staff_pembangunan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan CRUD Fasum']);
    $facility = CommonFacility::create([
        'property_id' => $property->id,
        'name' => 'Gerbang',
        'budget' => 12000000,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('pembangunan.progress.facilities.store', $facility), [
            'realization' => 4000000,
            'notes' => 'Pondasi gerbang selesai.',
        ])
        ->assertRedirect(route('pembangunan.progress.facilities.show', $facility))
        ->assertSessionHas('success', 'Progress fasilitas umum berhasil disimpan.');

    $this->assertDatabaseHas('common_facilities', [
        'id' => $facility->id,
        'realization' => '4000000.00',
        'status' => 'in_progress',
    ]);

    $this->put(route('pembangunan.progress.facilities.update', $facility), [
        'realization' => 5000000,
        'notes' => 'Pemasangan gerbang berjalan.',
    ])
        ->assertRedirect(route('pembangunan.progress.facilities.show', $facility))
        ->assertSessionHas('success', 'Progress fasilitas umum berhasil disimpan.');

    $this->delete(route('pembangunan.progress.facilities.destroy', $facility))
        ->assertRedirect(route('pembangunan.progress.facilities.show', $facility))
        ->assertSessionHas('success', 'Progress fasilitas umum dihapus. Riwayat perubahan tetap tersimpan.');

    $this->assertDatabaseHas('common_facilities', [
        'id' => $facility->id,
        'realization' => '0.00',
        'status' => 'planned',
    ]);
    $this->assertDatabaseHas('common_facility_histories', [
        'common_facility_id' => $facility->id,
        'previous_realization' => '5000000.00',
        'realization' => '0.00',
        'notes' => 'Progress saat ini dihapus; catatan riwayat sebelumnya tetap dipertahankan.',
    ]);
    $this->assertDatabaseHas('activity_logs', [
        'action' => 'facility.progress_deleted',
        'subject_type' => CommonFacility::class,
        'subject_id' => $facility->id,
    ]);
    expect(CommonFacilityHistory::query()->where('common_facility_id', $facility->id)->count())->toBe(3);
});
