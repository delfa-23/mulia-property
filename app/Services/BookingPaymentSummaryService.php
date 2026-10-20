<?php

namespace App\Services;

use App\Models\Booking;

class BookingPaymentSummaryService
{
    /**
     * @return array{
     *     stages: list<array{key: string, label: string, due: float, paid: float, remaining: float, paid_percentage: float}>,
     *     house_price: float,
     *     dp_total: float,
     *     dp_paid: float,
     *     dp_paid_percentage: float,
     *     total_paid: float,
     *     total_paid_price: float,
     *     paid_percentage: float
     * }
     */
    public function summarize(Booking $booking): array
    {
        $booking->loadMissing(['lot', 'payments']);

        $housePrice = (float) ($booking->house_price_snapshot ?? $booking->lot->house_price);
        $targets = [
            'dp' => ['label' => 'Pembayaran DP', 'due' => $housePrice],
            'akad_fee' => ['label' => 'Biaya Akad', 'due' => (float) $booking->akad_fee],
        ];
        $paidAmounts = $booking->payments
            ->groupBy('stage')
            ->map(fn ($payments) => (float) $payments->sum('amount'));
        $stages = [];

        foreach ($targets as $key => $target) {
            $paid = $key === 'dp'
                ? (float) $paidAmounts->get('dp', 0) + (float) $paidAmounts->get('bank_settlement', 0)
                : $paidAmounts->get($key, 0.0);
            $stages[] = [
                'key' => $key,
                'label' => $target['label'],
                'due' => $target['due'],
                'paid' => $paid,
                'remaining' => max(0, round($target['due'] - $paid, 2)),
                'paid_percentage' => $target['due'] > 0 ? min(100, $paid / $target['due'] * 100) : 100,
            ];
        }

        $totalPaid = (float) $booking->payments->sum('amount');
        $dpPaid = (float) $paidAmounts->get('dp', 0) + (float) $paidAmounts->get('bank_settlement', 0);

        return [
            'stages' => $stages,
            'house_price' => $housePrice,
            'dp_total' => $housePrice,
            'dp_paid' => $dpPaid,
            'dp_paid_percentage' => $housePrice > 0 ? min(100, $dpPaid / $housePrice * 100) : 0,
            'total_paid' => $totalPaid,
            'total_paid_price' => $dpPaid,
            'paid_percentage' => $housePrice > 0 ? min(100, $dpPaid / $housePrice * 100) : 0,
        ];
    }
}
