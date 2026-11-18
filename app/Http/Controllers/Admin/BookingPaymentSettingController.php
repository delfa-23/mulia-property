<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPaymentSchedule;
use App\Models\BookingPaymentSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingPaymentSettingController extends Controller
{
    public function index(): View
    {
        return view('admin.payment-settings.index', [
            'settings' => BookingPaymentSetting::query()
                ->orderBy('payment_category')
                ->get()
                ->keyBy('payment_category'),
        ]);
    }

    public function update(Request $request, string $paymentCategory): RedirectResponse
    {
        abort_unless(in_array($paymentCategory, BookingPaymentSetting::CATEGORIES, true), 404);

        foreach (['booking_fee_amount', 'dp_amount'] as $amountField) {
            if ($request->filled($amountField)) {
                $request->merge([
                    $amountField => preg_replace('/[^\d]/', '', (string) $request->input($amountField)),
                ]);
            }
        }

        $validated = $request->validate([
            'booking_fee_amount' => ['required', 'integer', 'min:0', 'max:9999999999999'],
            'dp_amount' => ['required', 'integer', 'min:0', 'max:9999999999999'],
            'booking_fee_deadline_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'dp_deadline_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        $this->ensureAmountsFitBookingSchedules(
            $paymentCategory,
            (float) $validated['booking_fee_amount'] + (float) $validated['dp_amount'],
        );

        DB::transaction(function () use ($paymentCategory, $validated): void {
            BookingPaymentSetting::query()
                ->where('payment_category', $paymentCategory)
                ->update($validated);

            $now = now();
            Booking::query()
                ->where('payment_category', $paymentCategory)
                ->whereIn('status', ['booking', 'process', 'akad'])
                ->update([
                    'blocking_until' => $now->copy()->addHours((int) $validated['booking_fee_deadline_hours']),
                    'dp_deadline_at' => $now->copy()->addDays((int) $validated['dp_deadline_days']),
                    'dp_deadline_alerted_at' => null,
                ]);
        });

        return to_route('admin.payment-settings.index')
            ->with('success', 'Konfigurasi pembayaran '.strtoupper($paymentCategory).' berhasil disimpan dan diterapkan ke booking terkait.');
    }

    private function ensureAmountsFitBookingSchedules(string $paymentCategory, float $configuredAmount): void
    {
        $lowestLotPrice = DB::table('lots')
            ->join('blocks', 'blocks.id', '=', 'lots.block_id')
            ->whereNotIn('lots.status', ['finish', 'cancelled'])
            ->min('lots.house_price');

        if ($lowestLotPrice !== null && $configuredAmount > (float) $lowestLotPrice + 0.009) {
            throw ValidationException::withMessages([
                'dp_amount' => 'Total Booking Fee dan DP tidak boleh melebihi harga kavling terendah.',
            ]);
        }

        $bookingsQuery = Booking::query()
            ->whereIn('status', ['booking', 'process', 'akad'])
            ->where(function ($query) use ($paymentCategory): void {
                $query->where('payment_category', $paymentCategory);

                if ($paymentCategory === 'cash') {
                    $query->orWhereNull('payment_category');
                }
            });
        $bookings = $bookingsQuery->with('lot')->get();

        if ($bookings->isEmpty()) {
            return;
        }

        $scheduleTotals = BookingPaymentSchedule::query()
            ->whereIn('booking_id', $bookings->modelKeys())
            ->where(function ($query): void {
                $query->where('is_active', true)
                    ->orWhereHas('payments')
                    ->orWhereHas('paymentOrders');
            })
            ->select('booking_id')
            ->selectRaw('SUM(amount) as scheduled_total')
            ->groupBy('booking_id')
            ->pluck('scheduled_total', 'booking_id');

        foreach ($bookings as $booking) {
            $scheduledTotal = (float) ($scheduleTotals[$booking->id] ?? 0);
            $housePrice = (float) ($booking->house_price_snapshot ?? $booking->lot->house_price);

            if ($configuredAmount + $scheduledTotal > $housePrice + 0.009) {
                throw ValidationException::withMessages([
                    'dp_amount' => 'Total Booking Fee, DP, dan jadwal pembayaran tidak boleh melebihi harga booking terkait.',
                ]);
            }
        }
    }
}
