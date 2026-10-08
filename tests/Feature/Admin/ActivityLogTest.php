<?php

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Carbon;

test('admin can filter activity logs by date range and action', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $firstLog = ActivityLog::create([
        'user_id' => $user->id,
        'action' => 'booking.created',
        'description' => 'Booking dalam rentang filter.',
    ]);
    $firstLog->forceFill(['created_at' => Carbon::parse('2026-09-05 10:00')])->saveQuietly();

    $secondLog = ActivityLog::create([
        'user_id' => $user->id,
        'action' => 'booking.updated',
        'description' => 'Booking dengan action berbeda.',
    ]);
    $secondLog->forceFill(['created_at' => Carbon::parse('2026-09-05 11:00')])->saveQuietly();

    $thirdLog = ActivityLog::create([
        'user_id' => $user->id,
        'action' => 'booking.created',
        'description' => 'Booking di luar rentang filter.',
    ]);
    $thirdLog->forceFill(['created_at' => Carbon::parse('2026-09-20 12:00')])->saveQuietly();

    $response = $this->actingAs($user)->get(route('admin.activity-logs.index', [
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-10',
        'action' => 'booking.created',
    ]));

    $response->assertOk()
        ->assertSee('Booking dalam rentang filter.')
        ->assertDontSee('Booking dengan action berbeda.')
        ->assertDontSee('Booking di luar rentang filter.');
});
