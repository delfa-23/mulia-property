<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPaymentSchedule;
use App\Models\BookingPaymentSetting;

class BookingPaymentSummaryService
{
    /**
     * @return array{
     *     stages: list<array{key: string, stage: string, schedule_id: int|null, label: string, due: float, paid: float, remaining: float, paid_percentage: float, configured: bool}>,
     *     house_price: float,
     *     dp_total: float,
     *     dp_paid: float,
     *     dp_paid_percentage: float,
     *     booking_paid: float,
     *     booking_paid_percentage: float,
     *     total_paid: float,
     *     total_paid_price: float,
     *     paid_percentage: float
     * }
     */
    public function summarize(Booking $booking): array
    {
        $payments = $booking->relationLoaded('payments')
            ? $booking->payments
            : $booking->payments()->get();
        $approvedPayments = $payments->where('approval_status', 'approved');

        $housePrice = (float) ($booking->house_price_snapshot ?? $booking->lot->house_price);
        $stages = [];
        $legacyPayments = $approvedPayments
            ->whereIn('stage', ['dp', 'bank_settlement']);
        $legacyPaymentOrdersExist = $booking->relationLoaded('paymentOrders')
            ? $booking->paymentOrders->contains('stage', 'dp')
            : $booking->paymentOrders()->where('stage', 'dp')->exists();
        $hasLegacyPayments = $legacyPayments->isNotEmpty() || $legacyPaymentOrdersExist;
        $categorySetting = BookingPaymentSetting::query()
            ->where('payment_category', $booking->payment_category ?? 'cash')
            ->first();
        $bookingFeeAmount = (float) ($categorySetting?->booking_fee_amount ?? 0);
        $dpAmount = (float) ($categorySetting?->dp_amount ?? 0);
        $hasCategoryPlan = $bookingFeeAmount > 0.009 || $dpAmount > 0.009;
        $property = $booking->lot->block->property;
        $usedScheduleIds = $payments->pluck('booking_payment_schedule_id')
            ->merge(
                $booking->relationLoaded('paymentOrders')
                    ? $booking->paymentOrders->pluck('booking_payment_schedule_id')
                    : $booking->paymentOrders()->pluck('booking_payment_schedule_id'),
            )
            ->filter()
            ->unique();
        $schedules = $property->relationLoaded('bookingPaymentSchedules')
            ? $property->bookingPaymentSchedules
                ->filter(fn (BookingPaymentSchedule $schedule): bool => (int) $schedule->booking_id === $booking->id
                    && ($schedule->is_active || $usedScheduleIds->contains($schedule->id)))
                ->values()
            : BookingPaymentSchedule::query()
                ->where('property_id', $property->id)
                ->where('booking_id', $booking->id)
                ->where(function ($query) use ($booking): void {
                    $query->where('is_active', true)
                        ->orWhereHas('payments', fn ($payments) => $payments->where('booking_id', $booking->id))
                        ->orWhereHas('paymentOrders', fn ($orders) => $orders->where('booking_id', $booking->id));
                })
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

        if ($hasCategoryPlan) {
            if ($bookingFeeAmount > 0.009) {
                $stages[] = $this->makeStage(
                    'booking_fee',
                    'booking_fee',
                    null,
                    'Booking Fee',
                    $bookingFeeAmount,
                    (float) $approvedPayments->where('stage', 'booking_fee')->sum('amount'),
                    true,
                );
            }

            if ($dpAmount > 0.009) {
                $stages[] = $this->makeStage(
                    'dp',
                    'dp',
                    null,
                    'Down Payment (DP)',
                    $dpAmount,
                    (float) $approvedPayments->where('stage', 'dp')->sum('amount'),
                    true,
                );
            }
        }

        if (! $hasCategoryPlan && ($schedules->isEmpty() || $hasLegacyPayments)) {
            $bookingPaid = (float) $legacyPayments->sum('amount')
                + (float) $approvedPayments->where('stage', 'booking')->whereNull('booking_payment_schedule_id')->sum('amount')
                + (float) $approvedPayments->where('stage', 'booking_balance')->sum('amount');
            $stages[] = $this->makeStage(
                'dp',
                'dp',
                null,
                'Pembayaran Booking',
                $housePrice,
                $bookingPaid,
                $hasLegacyPayments,
            );
        } else {
            foreach ($schedules as $schedule) {
                $paid = (float) $approvedPayments
                    ->where('booking_payment_schedule_id', $schedule->id)
                    ->sum('amount');
                $stages[] = $this->makeStage(
                    'booking_schedule_'.$schedule->id,
                    'booking',
                    $schedule->id,
                    $schedule->title,
                    (float) $schedule->amount,
                    $paid,
                    true,
                );
            }
        }

        $akadPaid = (float) $approvedPayments->where('stage', 'akad_fee')->sum('amount');
        $stages[] = $this->makeStage(
            'akad_fee',
            'akad_fee',
            null,
            'Biaya Akad',
            (float) $booking->akad_fee,
            $akadPaid,
            true,
        );

        $totalPaid = (float) $approvedPayments->sum('amount');
        $bookingPaid = (float) $approvedPayments
            ->whereIn('stage', ['booking_fee', 'dp', 'bank_settlement', 'booking', 'booking_balance'])
            ->sum('amount');

        return [
            'stages' => $stages,
            'house_price' => $housePrice,
            'dp_total' => $housePrice,
            'dp_paid' => $bookingPaid,
            'dp_paid_percentage' => $housePrice > 0 ? min(100, $bookingPaid / $housePrice * 100) : 0,
            'booking_paid' => $bookingPaid,
            'booking_paid_percentage' => $housePrice > 0 ? min(100, $bookingPaid / $housePrice * 100) : 0,
            'total_paid' => $totalPaid,
            'total_paid_price' => $bookingPaid,
            'paid_percentage' => $housePrice > 0 ? min(100, $bookingPaid / $housePrice * 100) : 0,
        ];
    }

    /**
     * @return array{key: string, stage: string, schedule_id: int|null, label: string, due: float, paid: float, remaining: float, paid_percentage: float, configured: bool}
     */
    private function makeStage(
        string $key,
        string $stage,
        ?int $scheduleId,
        string $label,
        float $due,
        float $paid,
        bool $configured,
    ): array {
        return [
            'key' => $key,
            'stage' => $stage,
            'schedule_id' => $scheduleId,
            'label' => $label,
            'due' => $due,
            'paid' => $paid,
            'remaining' => max(0, round($due - $paid, 2)),
            'paid_percentage' => $due > 0 ? min(100, $paid / $due * 100) : 0,
            'configured' => $configured,
        ];
    }
}
