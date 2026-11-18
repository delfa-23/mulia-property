<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Alert;
use App\Models\Booking;
use App\Models\BookingPaymentSetting;
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
                            ! $this->bookingFeeHasExpired($booking)
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

        $dpAlertCount = 0;
        Booking::query()
            ->whereNotNull('payment_category')
            ->whereIn('status', ['booking', 'process', 'akad'])
            ->whereNotNull('dp_deadline_at')
            ->where('dp_deadline_at', '<=', now())
            ->whereNull('dp_deadline_alerted_at')
            ->orderBy('id')
            ->select('id')
            ->chunkById(100, function ($bookings) use (&$dpAlertCount, $alertNotifications): void {
                foreach ($bookings as $candidate) {
                    $created = DB::transaction(function () use ($candidate, $alertNotifications): bool {
                        $booking = Booking::query()
                            ->lockForUpdate()
                            ->find($candidate->id);

                        if (
                            ! $booking ||
                            ! in_array($booking->status, ['booking', 'process', 'akad'], true) ||
                            ! ($booking->dp_deadline_at?->lessThanOrEqualTo(now()) ?? false) ||
                            $booking->dp_deadline_alerted_at !== null
                        ) {
                            return false;
                        }

                        $setting = BookingPaymentSetting::query()
                            ->where('payment_category', $booking->payment_category)
                            ->first();

                        if ($setting === null || (float) $setting->dp_amount <= 0.009) {
                            return false;
                        }

                        $paidAmount = (float) $booking->payments()
                            ->where('stage', 'dp')
                            ->where('approval_status', 'approved')
                            ->sum('amount');
                        $hasPendingPayment = $booking->payments()
                            ->where('stage', 'dp')
                            ->where('approval_status', 'pending')
                            ->exists() || $booking->paymentOrders()
                            ->where('stage', 'dp')
                            ->whereIn('status', ['pending', 'paid'])
                            ->exists();

                        if ($paidAmount >= (float) $setting->dp_amount - 0.009 || $hasPendingPayment) {
                            return false;
                        }

                        $alert = Alert::create([
                            'lot_id' => $booking->lot_id,
                            'type' => 'dp_deadline',
                            'title' => 'Deadline DP terlewati',
                            'message' => 'Pembayaran DP booking '.$booking->customer()->value('name').' belum lunas setelah deadline '.strtoupper($booking->payment_category).'. Kavling tetap dibooking.',
                            'severity' => 'medium',
                        ]);
                        $alertNotifications->notifyReceived($alert);
                        $booking->update(['dp_deadline_alerted_at' => now()]);

                        return true;
                    });

                    $dpAlertCount += (int) $created;
                }
            });

        $this->info("{$expiredCount} booking expired.");
        $this->info("{$dpAlertCount} DP deadline alerts created.");

        return self::SUCCESS;
    }

    private function bookingFeeHasExpired(Booking $booking): bool
    {
        if ($booking->payment_category === null) {
            return ! $this->hasBlockingPayment($booking)
                && ! $booking->paymentOrders()->whereIn('status', ['pending', 'paid'])->exists();
        }

        $setting = BookingPaymentSetting::query()
            ->where('payment_category', $booking->payment_category)
            ->first();

        if ($setting === null || (float) $setting->booking_fee_amount <= 0.009) {
            return false;
        }

        $paidAmount = (float) $booking->payments()
            ->where('stage', 'booking_fee')
            ->where('approval_status', 'approved')
            ->sum('amount');
        $hasPendingPayment = $booking->payments()
            ->where('stage', 'booking_fee')
            ->where('approval_status', 'pending')
            ->exists() || $booking->paymentOrders()
            ->where('stage', 'booking_fee')
            ->whereIn('status', ['pending', 'paid'])
            ->exists();

        return $paidAmount < (float) $setting->booking_fee_amount - 0.009 && ! $hasPendingPayment;
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
