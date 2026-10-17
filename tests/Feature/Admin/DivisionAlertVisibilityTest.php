<?php

use App\Models\Alert;
use App\Models\Division;
use App\Models\User;

test('alerts sent to a division are visible to that division and admin only', function () {
    $marketingDivision = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $pemberkasanDivision = Division::create([
        'name' => 'Pemberkasan',
        'slug' => 'pemberkasan',
        'is_active' => true,
    ]);
    $financeDivision = Division::create([
        'name' => 'Finance',
        'slug' => 'finance',
        'is_active' => true,
    ]);
    $marketingUser = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $marketingDivision->id,
    ]);
    $pemberkasanUser = User::factory()->create([
        'role' => 'staff_pemberkasan',
        'division_id' => $pemberkasanDivision->id,
    ]);
    $financeUser = User::factory()->create([
        'role' => 'staff_finance',
        'division_id' => $financeDivision->id,
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($marketingUser)
        ->post(route('alerts.store'), [
            'type' => 'Informasi',
            'title' => 'Dokumen siap diperiksa',
            'message' => 'Berkas pelanggan sudah tersedia.',
            'severity' => 'high',
            'recipient_division_ids' => [$pemberkasanDivision->id],
        ])
        ->assertRedirect(route('alerts.create'));

    $alert = Alert::query()->where('title', 'Dokumen siap diperiksa')->firstOrFail();
    $this->assertDatabaseHas('alert_division', [
        'alert_id' => $alert->id,
        'division_id' => $pemberkasanDivision->id,
    ]);

    $this->actingAs($pemberkasanUser)
        ->get(route('alerts.index'))
        ->assertSee('Dokumen siap diperiksa');

    $this->actingAs($financeUser)
        ->get(route('alerts.index'))
        ->assertDontSee('Dokumen siap diperiksa');

    $this->actingAs($admin)
        ->get(route('admin.alerts.index'))
        ->assertSee('Dokumen siap diperiksa');
});

test('a division cannot target its own alert at itself', function () {
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
        ->post(route('alerts.store'), [
            'type' => 'Informasi',
            'title' => 'Alert tidak valid',
            'message' => 'Alert tidak boleh kembali ke divisi pengirim.',
            'severity' => 'medium',
            'recipient_division_ids' => [$division->id],
        ])
        ->assertSessionHasErrors('recipient_division_ids.0');

    $this->assertDatabaseMissing('alerts', [
        'title' => 'Alert tidak valid',
    ]);
});

test('finance can send an alert to another division and admin', function () {
    $financeDivision = Division::create([
        'name' => 'Finance',
        'slug' => 'finance',
        'is_active' => true,
    ]);
    $marketingDivision = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $financeUser = User::factory()->create([
        'role' => 'staff_finance',
        'division_id' => $financeDivision->id,
    ]);
    $marketingUser = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $marketingDivision->id,
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($financeUser)
        ->post(route('alerts.store'), [
            'type' => 'finance',
            'title' => 'Pembayaran perlu ditindaklanjuti',
            'message' => 'Mohon tindak lanjuti pembayaran ini.',
            'severity' => 'high',
            'recipient_division_ids' => [$marketingDivision->id],
        ])
        ->assertRedirect(route('alerts.create'));

    $this->actingAs($marketingUser)
        ->get(route('alerts.index'))
        ->assertSee('Pembayaran perlu ditindaklanjuti');

    $this->actingAs($admin)
        ->get(route('admin.alerts.index'))
        ->assertSee('Pembayaran perlu ditindaklanjuti');
});
