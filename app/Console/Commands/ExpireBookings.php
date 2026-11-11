<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Alert;
use App\Models\Booking;
use App\Models\Lot;
use App\Services\AlertNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireBookings extends Command
{
    protected $signature = 'bookings:expire';

    protected $description = 'Delete unpaid bookings whose blocking deadline has passed';

    public function handle(AlertNotificationService $alertNotifications): int
    {
        $expiredCount = 0;

        Booking::query()
            ->where('status', 'booking')
            ->whereNotNull('blocking_until')
            ->where('blocking_until', '<=', now())
            ->whereDoesntHave('paymentOrders', fn ($query) => $query->whereIn('status', ['pending', 'paid']))
            ->orderBy('id')
            ->select('id')
            ->chunkById(100, function ($bookings) use (&$expiredCount, $alertNotifications): void {
                foreach ($bookings as $candidate) {
                    $expired = DB::transaction(function () use ($candidate, $alertNotifications): bool {
                        $booking = Booking::query()
                            ->lockForUpdate()
                            ->find($candidate->id);

                        if (
                            ! $booking ||
                            $booking->status !== 'booking' ||
                            ! ($booking->blocking_until?->lessThanOrEqualTo(now()) ?? false) ||
                            $this->hasBlockingPayment($booking) ||
                            $booking->paymentOrders()->whereIn('status', ['pending', 'paid'])->exists()
                        ) {
                            return false;
                        }

                        $lot = Lot::query()->lockForUpdate()->find($booking->lot_id);
                        $lot?->update(['status' => 'available']);

                        $alert = Alert::create([
                            'lot_id' => $booking->lot_id,
                            'type' => 'booking_expired',
                            'title' => 'Booking kedaluwarsa dihapus',
                            'message' => 'Booking untuk '.$booking->customer()->value('name').' dihapus otomatis karena batas booking terlewati dan belum ada pembayaran yang masuk.',
                            'severity' => 'high',
                        ]);
                        $alertNotifications->notifyReceived($alert);

                        ActivityLog::create([
                            'action' => 'booking.auto_deleted',
                            'subject_type' => Booking::class,
                            'subject_id' => $booking->id,
                            'description' => 'Booking dihapus otomatis setelah batas blocking terlewati dan belum ada pembayaran.',
                        ]);

                        $booking->delete();

                        return true;
                    });

                    $expiredCount += (int) $expired;
                }
            });

        $this->info("{$expiredCount} booking expired.");

        return self::SUCCESS;
    }

    private function hasBlockingPayment(Booking $booking): bool
    {
        return $booking->payments()
            ->where('approval_status', 'approved')
            ->where(function ($query) use ($booking): void {
                $query->where(function ($query): void {
                    $query->whereIn('stage', ['dp', 'bank_settlement'])
                        ->where('amount', '>', 0);
                });

                if ((float) $booking->akad_fee > 0) {
                    $query->orWhere(function ($query): void {
                        $query->where('stage', 'akad_fee')
                            ->where('amount', '>', 0);
                    });
                }
            })
            ->exists();
    }
}
