<?php

use App\Models\Alert;
use App\Models\Division;
use App\Models\User;
use App\Notifications\AlertActivity;

test('alerts sent to a division are visible to the sender, recipient division, and admin only', function () {
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

    $this->actingAs($marketingUser)
        ->get(route('alerts.index'))
        ->assertSee('Dokumen siap diperiksa');

    $this->actingAs($pemberkasanUser)
        ->get(route('alerts.index'))
        ->assertSee('Dokumen siap diperiksa')
        ->assertSee('data-alert-count', false)
        ->assertDontSee('data-alert-notifications', false)
        ->assertDontSee('fixed right-4 top-4', false);

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
        ->assertSee('Pembayaran perlu ditindaklanjuti')
        ->assertSee('data-alert-count', false);

    $this->assertDatabaseHas('notifications', [
        'notifiable_type' => User::class,
        'notifiable_id' => $marketingUser->id,
        'type' => AlertActivity::class,
    ]);
    $this->assertDatabaseHas('notifications', [
        'notifiable_type' => User::class,
        'notifiable_id' => $admin->id,
        'type' => AlertActivity::class,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.alerts.index'))
        ->assertSee('Pembayaran perlu ditindaklanjuti')
        ->assertSee('data-alert-count', false);
});

test('only an admin or recipient division can resolve an alert and the sender is notified', function () {
    $marketingDivision = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $financeDivision = Division::create([
        'name' => 'Finance',
        'slug' => 'finance',
        'is_active' => true,
    ]);
    $pemberkasanDivision = Division::create([
        'name' => 'Pemberkasan',
        'slug' => 'pemberkasan',
        'is_active' => true,
    ]);
    $sender = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $marketingDivision->id,
    ]);
    $recipient = User::factory()->create([
        'role' => 'staff_finance',
        'division_id' => $financeDivision->id,
    ]);
    $unrelatedUser = User::factory()->create([
        'role' => 'staff_pemberkasan',
        'division_id' => $pemberkasanDivision->id,
    ]);
    $alert = Alert::create([
        'created_by' => $sender->id,
        'type' => 'Informasi',
        'title' => 'Konfirmasi data kavling',
        'message' => 'Data perlu dicek.',
        'severity' => 'medium',
    ]);
    $alert->recipientDivisions()->sync([$financeDivision->id]);

    $this->actingAs($unrelatedUser)
        ->post(route('alerts.resolve', $alert))
        ->assertForbidden();
    $this->assertDatabaseHas('alerts', ['id' => $alert->id, 'is_resolved' => false]);

    $this->actingAs($recipient)
        ->post(route('alerts.resolve', $alert))
        ->assertRedirect()
        ->assertSessionHas('success', 'Alert berhasil diselesaikan.');

    $this->assertDatabaseHas('alerts', [
        'id' => $alert->id,
        'is_resolved' => true,
        'resolved_by' => $recipient->id,
    ]);
    expect($sender->notifications()->where('type', AlertActivity::class)->firstOrFail()->data['event'])
        ->toBe('resolved');

    $this->actingAs($sender)
        ->postJson(route('notifications.alerts.pending-count'))
        ->assertOk()
        ->assertJsonPath('count', 1);

    expect($sender->unreadNotifications()->where('type', AlertActivity::class)->exists())->toBeTrue();
    expect($recipient->unreadNotifications()->where('type', AlertActivity::class)->exists())->toBeFalse();

    $this->actingAs($recipient)
        ->postJson(route('notifications.alerts.pending-count'))
        ->assertOk()
        ->assertJsonPath('count', 0);

    $this->actingAs($sender)
        ->get(route('alerts.index'))
        ->assertSee('Konfirmasi data kavling');

    expect($sender->unreadNotifications()->where('type', AlertActivity::class)->exists())->toBeFalse();

    $this->actingAs($sender)
        ->postJson(route('notifications.alerts.pending-count'))
        ->assertOk()
        ->assertJsonPath('count', 0);
});

test('inbox alert badge counts active alerts for the signed-in user and keeps active items until completion', function () {
    $marketingDivision = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $financeDivision = Division::create([
        'name' => 'Finance',
        'slug' => 'finance',
        'is_active' => true,
    ]);
    $sender = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $marketingDivision->id,
    ]);
    $recipient = User::factory()->create([
        'role' => 'staff_finance',
        'division_id' => $financeDivision->id,
    ]);
    $otherUser = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $marketingDivision->id,
    ]);

    $this->actingAs($sender)
        ->post(route('alerts.store'), [
            'type' => 'Informasi',
            'title' => 'Alert popup Finance',
            'message' => 'Ada data baru.',
            'severity' => 'medium',
            'recipient_division_ids' => [$financeDivision->id],
        ])
        ->assertRedirect(route('alerts.create'));

    $this->actingAs($recipient)
        ->postJson(route('notifications.alerts.pending-count'))
        ->assertOk()
        ->assertJsonPath('count', 1);

    $recipientNotification = $recipient->unreadNotifications()->where('type', AlertActivity::class)->firstOrFail();
    expect($recipient->unreadNotifications()->where('type', AlertActivity::class)->exists())->toBeTrue();
    expect($otherUser->notifications()->where('type', AlertActivity::class)->exists())->toBeFalse();

    $recipientNotification->markAsRead();

    $this->actingAs($recipient)
        ->postJson(route('notifications.alerts.pending-count'))
        ->assertOk()
        ->assertJsonPath('count', 1);

    expect($recipient->unreadNotifications()->where('type', AlertActivity::class)->exists())->toBeFalse();

    $this->actingAs($otherUser)
        ->postJson(route('notifications.alerts.pending-count'))
        ->assertOk()
        ->assertJsonPath('count', 0);

    $this->actingAs($recipient)
        ->post(route('alerts.resolve', Alert::query()->where('title', 'Alert popup Finance')->firstOrFail()))
        ->assertRedirect()
        ->assertSessionHas('success', 'Alert berhasil diselesaikan.');

    $this->actingAs($recipient)
        ->postJson(route('notifications.alerts.pending-count'))
        ->assertOk()
        ->assertJsonPath('count', 0);

    $this->actingAs($sender)
        ->postJson(route('notifications.alerts.pending-count'))
        ->assertOk()
        ->assertJsonPath('count', 1);
});
