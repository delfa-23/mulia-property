<?php

namespace App\Services;

use App\Models\BookingPayment;
use App\Models\FinanceCashFlow;
use Illuminate\Support\Str;

class BookingPaymentFinanceSync
{
    public function sync(BookingPayment $payment): FinanceCashFlow
    {
        if ($payment->approval_status !== 'approved') {
            throw new \InvalidArgumentException('Pembayaran yang belum disetujui tidak dapat masuk laporan finance.');
        }

        $payment->loadMissing(['booking.lot.block', 'paymentSchedule']);
        $booking = $payment->booking;
        $stageLabel = $payment->paymentSchedule?->title ?? match ($payment->stage) {
            'dp', 'bank_settlement', 'booking' => 'Pembayaran Booking',
            'booking_balance' => 'Sisa Pembayaran Booking',
            'akad_fee' => 'Biaya Akad',
            default => Str::headline($payment->stage),
        };

        return FinanceCashFlow::query()->firstOrCreate(
            ['booking_payment_id' => $payment->id],
            [
                'property_id' => $booking->lot->block->property_id,
                'title' => Str::limit("Pembayaran booking #{$booking->id} - {$stageLabel}", 150, ''),
                'direction' => 'income',
                'status' => 'confirmed',
                'due_date' => $payment->paid_at,
                'amount' => $payment->amount,
                'created_by' => $payment->recorded_by,
            ],
        );
    }
}
