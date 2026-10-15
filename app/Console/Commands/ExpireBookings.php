<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Alert;
use App\Models\Booking;
use App\Models\BookingHistory;
use App\Models\Lot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireBookings extends Command
{
    protected $signature = 'bookings:expire';

    protected $description = 'Cancel bookings whose blocking deadline has passed';

    public function handle(): int
    {
        $expiredCount = 0;

        Booking::query()
            ->where('status', 'booking')
            ->whereNotNull('blocking_until')
            ->where('blocking_until', '<=', now())
            ->orderBy('id')
            ->select('id')
            ->chunkById(100, function ($bookings) use (&$expiredCount): void {
                foreach ($bookings as $candidate) {
                    $expired = DB::transaction(function () use ($candidate): bool {
                        $booking = Booking::query()
                            ->lockForUpdate()
                            ->find($candidate->id);

                        if (
                            ! $booking ||
                            $booking->status !== 'booking' ||
                            ! ($booking->blocking_until?->lessThanOrEqualTo(now()) ?? false)
                        ) {
                            return false;
                        }

                        $lot = Lot::query()->lockForUpdate()->find($booking->lot_id);
                        $booking->update(['status' => 'cancelled']);
                        $lot?->update(['status' => 'available']);

                        BookingHistory::create([
                            'booking_id' => $booking->id,
                            'from_status' => 'booking',
                            'to_status' => 'cancelled',
                            'notes' => 'Booking dibatalkan otomatis karena batas blocking terlewati.',
                            'changed_at' => now(),
                        ]);

                        Alert::create([
                            'lot_id' => $booking->lot_id,
                            'type' => 'booking_expired',
                            'title' => 'Booking otomatis dibatalkan',
                            'message' => 'Booking untuk '.$booking->customer()->value('name').' dibatalkan otomatis karena batas booking terlewati.',
                            'severity' => 'high',
                        ]);

                        ActivityLog::create([
                            'action' => 'booking.auto_cancelled',
                            'subject_type' => Booking::class,
                            'subject_id' => $booking->id,
                            'description' => 'Booking otomatis dibatalkan setelah batas blocking terlewati.',
                        ]);

                        return true;
                    });

                    $expiredCount += (int) $expired;
                }
            });

        $this->info("{$expiredCount} booking expired.");

        return self::SUCCESS;
    }
}
