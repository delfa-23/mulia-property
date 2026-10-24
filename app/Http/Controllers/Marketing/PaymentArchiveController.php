<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\ActivityLogger;
use App\Services\BookingPaymentSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentArchiveController extends Controller
{
    public function store(
        Request $request,
        Booking $booking,
        ActivityLogger $activityLogger,
        BookingPaymentSummaryService $paymentSummaryService,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $booking, $activityLogger, $paymentSummaryService): void {
            $lockedBooking = Booking::query()
                ->with(['lot', 'payments'])
                ->lockForUpdate()
                ->findOrFail($booking->id);

            abort_if($lockedBooking->payment_archived_at !== null, 422, 'Pembayaran booking ini sudah diarsipkan.');
            abort_if(
                $lockedBooking->paymentOrders()->where('status', 'pending')->exists(),
                422,
                'Pembayaran belum dapat diarsipkan selama ada transaksi Midtrans yang menunggu konfirmasi.',
            );

            $summary = $paymentSummaryService->summarize($lockedBooking);
            $isFullyPaid = collect($summary['stages'])->every(
                fn (array $stage): bool => $stage['remaining'] <= 0.009,
            );

            abort_unless($isFullyPaid, 422, 'Booking hanya dapat diarsipkan setelah semua tagihan lunas.');

            $lockedBooking->payment_archived_at = now();
            $lockedBooking->save();
            $activityLogger->record($request, 'booking.payment_archived', $lockedBooking, 'Pembayaran booking diarsipkan.');
        });

        return to_route('marketing.payments.index', ['archive' => 'active'])
            ->with('success', 'Pembayaran lunas berhasil dimasukkan ke arsip.');
    }

    public function destroy(
        Request $request,
        Booking $booking,
        ActivityLogger $activityLogger,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $booking, $activityLogger): void {
            $lockedBooking = Booking::query()
                ->lockForUpdate()
                ->findOrFail($booking->id);

            abort_if($lockedBooking->payment_archived_at === null, 422, 'Pembayaran booking ini belum diarsipkan.');

            $lockedBooking->payment_archived_at = null;
            $lockedBooking->save();
            $activityLogger->record($request, 'booking.payment_archive_restored', $lockedBooking, 'Pembayaran booking dikembalikan dari arsip.');
        });

        return to_route('marketing.payments.index', ['archive' => 'archived'])
            ->with('success', 'Pembayaran dikembalikan ke daftar pembayaran aktif.');
    }
}
