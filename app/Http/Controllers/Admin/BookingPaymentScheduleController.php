<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingPaymentSchedule;
use App\Models\BookingPaymentSetting;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingPaymentScheduleController extends Controller
{
    public function index(Property $property): View
    {
        $schedules = $property->bookingPaymentSchedules()
            ->whereNull('booking_id')
            ->with(['payments', 'paymentOrders'])
            ->get();
        $schedules->each(function (BookingPaymentSchedule $schedule): void {
            $transactionBookingIds = $schedule->payments->pluck('booking_id')
                ->merge($schedule->paymentOrders->pluck('booking_id'))
                ->unique()
                ->values();

            $schedule->setAttribute(
                'transaction_booking_id',
                $transactionBookingIds->count() === 1 ? (int) $transactionBookingIds->first() : null,
            );
            $schedule->setAttribute('has_multiple_transaction_bookings', $transactionBookingIds->count() > 1);
        });

        $allBookings = Booking::query()
            ->whereHas('lot.block', fn ($blocks) => $blocks->where('property_id', $property->id))
            ->with(['customer', 'lot.block'])
            ->orderByDesc('booking_date')
            ->get();

        return view('admin.properties.payment-schedules', [
            'property' => $property,
            'schedules' => $schedules,
            'allBookings' => $allBookings,
            'bookings' => $allBookings
                ->whereIn('status', ['booking', 'process', 'akad'])
                ->values(),
        ]);
    }

    public function store(Request $request, Property $property): RedirectResponse
    {
        $validated = $this->validated($request, includeBookingId: true);
        $bookingId = $validated['booking_id'];
        unset($validated['booking_id']);
        $booking = $this->bookingForProperty($property, (int) $bookingId);

        if (! in_array($booking->status, ['booking', 'process', 'akad'], true)) {
            throw ValidationException::withMessages([
                'booking_id' => 'Jadwal hanya dapat ditambahkan ke booking yang masih aktif.',
            ]);
        }

        $this->ensureScheduleTotalFitsProperty($property, (float) $validated['amount'], $booking);
        $schedules = $property->bookingPaymentSchedules()->where('booking_id', $booking->id);

        $property->bookingPaymentSchedules()->create([
            ...$validated,
            'booking_id' => $booking->id,
            'sort_order' => (int) $schedules->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Jadwal pembayaran berhasil ditambahkan.');
    }

    public function assignToBooking(
        Request $request,
        Property $property,
        BookingPaymentSchedule $bookingPaymentSchedule,
    ): RedirectResponse {
        $schedule = $property->bookingPaymentSchedules()
            ->whereNull('booking_id')
            ->whereKey($bookingPaymentSchedule->id)
            ->firstOrFail();
        $validated = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
        ]);
        $booking = $this->bookingForProperty($property, (int) $validated['booking_id']);
        $transactionBookingIds = $schedule->payments()->pluck('booking_id')
            ->merge($schedule->paymentOrders()->pluck('booking_id'))
            ->map(fn ($bookingId): int => (int) $bookingId)
            ->unique();

        if ($transactionBookingIds->contains(fn (int $bookingId): bool => $bookingId !== $booking->id)) {
            throw ValidationException::withMessages([
                'booking_id' => 'Jadwal ini memiliki transaksi untuk booking lain dan tidak dapat dipindahkan ke booking yang dipilih.',
            ]);
        }

        $nextSortOrder = (int) $property->bookingPaymentSchedules()
            ->where('booking_id', $booking->id)
            ->max('sort_order') + 1;
        $schedule->update([
            'booking_id' => $booking->id,
            'sort_order' => $nextSortOrder,
        ]);

        return back()->with('success', 'Jadwal pembayaran sekarang khusus untuk booking yang dipilih.');
    }

    public function updateForBooking(
        Request $request,
        Booking $booking,
        BookingPaymentSchedule $bookingPaymentSchedule,
    ): RedirectResponse {
        $booking->loadMissing('lot');
        $schedule = $booking->bookingPaymentSchedules()
            ->whereKey($bookingPaymentSchedule->id)
            ->firstOrFail();
        $this->ensureScheduleIsNotFullyPaid($schedule, $booking);
        $validated = $this->validated($request);

        $hasPaymentActivity = $schedule->payments()->exists() || $schedule->paymentOrders()->exists();
        if (
            $hasPaymentActivity &&
            ((string) $schedule->title !== $validated['title'] ||
                (float) $schedule->amount !== (float) $validated['amount'])
        ) {
            throw ValidationException::withMessages([
                'title' => 'Judul dan nominal tidak dapat diubah karena jadwal ini sudah memiliki transaksi.',
            ]);
        }

        if ($schedule->is_active) {
            $this->ensureScheduleTotalFitsProperty(
                $schedule->property,
                (float) $validated['amount'],
                $booking,
                $schedule,
            );
        }

        $schedule->update($validated);

        return back()->with('success', 'Jadwal pembayaran booking berhasil diperbarui.');
    }

    public function destroyForBooking(
        Booking $booking,
        BookingPaymentSchedule $bookingPaymentSchedule,
    ): RedirectResponse {
        $schedule = $booking->bookingPaymentSchedules()
            ->whereKey($bookingPaymentSchedule->id)
            ->firstOrFail();
        $this->ensureScheduleIsNotFullyPaid($schedule, $booking);

        if ($schedule->payments()->exists() || $schedule->paymentOrders()->exists()) {
            $schedule->update(['is_active' => false]);

            return back()->with('success', 'Jadwal dinonaktifkan. Riwayat dan tagihan booking yang sudah terkait tetap tersimpan.');
        }

        $schedule->delete();

        return back()->with('success', 'Jadwal pembayaran booking berhasil dihapus.');
    }

    /** @return array{title: string, amount: string, booking_id?: int} */
    private function validated(Request $request, bool $includeBookingId = false): array
    {
        if ($request->filled('amount')) {
            $request->merge([
                'amount' => preg_replace('/[^\d]/', '', (string) $request->input('amount')),
            ]);
        }

        return $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'integer', 'min:1', 'max:9999999999999'],
            ...($includeBookingId ? ['booking_id' => ['required', 'integer', 'exists:bookings,id']] : []),
        ]);
    }

    private function bookingForProperty(Property $property, int $bookingId): Booking
    {
        return Booking::query()
            ->whereKey($bookingId)
            ->whereHas('lot.block', fn ($blocks) => $blocks->where('property_id', $property->id))
            ->with('lot')
            ->firstOrFail();
    }

    private function ensureScheduleIsNotFullyPaid(
        BookingPaymentSchedule $schedule,
        Booking $booking,
    ): void {
        $paidInFull = BookingPayment::query()
            ->where('booking_payment_schedule_id', $schedule->id)
            ->where('booking_id', $booking->id)
            ->where('approval_status', 'approved')
            ->selectRaw('SUM(amount) as paid_amount')
            ->groupBy('booking_id')
            ->get()
            ->contains(fn ($payment): bool => (float) $payment->paid_amount >= (float) $schedule->amount - 0.009);

        if (! $paidInFull) {
            $paidInFull = $schedule->paymentOrders()
                ->where('booking_id', $booking->id)
                ->where('status', 'paid')
                ->exists();
        }

        if ($paidInFull) {
            throw ValidationException::withMessages([
                'schedule' => 'Jadwal pembayaran tidak dapat diubah atau dihapus karena sudah lunas.',
            ]);
        }
    }

    private function ensureScheduleTotalFitsProperty(
        Property $property,
        float $amount,
        Booking $booking,
        ?BookingPaymentSchedule $schedule = null,
    ): void {
        $activeSchedules = $property->bookingPaymentSchedules()
            ->where('booking_id', $booking->id)
            ->where(function ($query) use ($booking): void {
                $query->where('is_active', true)
                    ->orWhereHas('payments', fn ($payments) => $payments->where('booking_id', $booking->id))
                    ->orWhereHas('paymentOrders', fn ($orders) => $orders->where('booking_id', $booking->id));
            });

        if ($schedule !== null) {
            $activeSchedules->where('id', '!=', $schedule->id);
        }

        $scheduledTotal = (float) $activeSchedules->sum('amount') + $amount;
        $categoryAmounts = BookingPaymentSetting::query()
            ->get()
            ->mapWithKeys(fn (BookingPaymentSetting $setting): array => [
                $setting->payment_category => (float) $setting->booking_fee_amount + (float) $setting->dp_amount,
            ]);
        $scheduledTotal += (float) ($categoryAmounts[$booking->payment_category ?? 'cash'] ?? 0);
        $housePrice = (float) ($booking->house_price_snapshot ?? $booking->lot->house_price);

        if ($scheduledTotal > $housePrice + 0.009) {
            throw ValidationException::withMessages([
                'amount' => 'Total nominal jadwal tidak boleh melebihi harga booking ini.',
            ]);
        }
    }
}
