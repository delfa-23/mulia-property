<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::command('queue:work database --stop-when-empty --tries=4 --timeout=60')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->description('Process queued uploads');

Schedule::command('bookings:expire')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->description('Delete unpaid bookings past their blocking deadline and release their lots');
