<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingPaymentSchedule;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingPaymentScheduleController extends Controller
{
    public function index(Property $property): View
    {
        return view('admin.properties.payment-schedules', [
            'property' => $property,
            'schedules' => $property->bookingPaymentSchedules()->get(),
        ]);
    }

    public function store(Request $request, Property $property): RedirectResponse
    {
        $validated = $this->validated($request);
        $this->ensureScheduleTotalFitsProperty($property, (float) $validated['amount']);

        $property->bookingPaymentSchedules()->create([
            ...$validated,
            'sort_order' => (int) $property->bookingPaymentSchedules()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Jadwal pembayaran berhasil ditambahkan.');
    }

    public function update(
        Request $request,
        Property $property,
        BookingPaymentSchedule $bookingPaymentSchedule,
    ): RedirectResponse {
        $schedule = $property->bookingPaymentSchedules()
            ->whereKey($bookingPaymentSchedule->id)
            ->firstOrFail();
        $validated = $this->validated($request, true);

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

        if ($validated['is_active']) {
            $this->ensureScheduleTotalFitsProperty(
                $property,
                (float) $validated['amount'],
                $schedule,
            );
        }

        $schedule->update($validated);

        return back()->with('success', 'Jadwal pembayaran berhasil diperbarui.');
    }

    public function destroy(
        Property $property,
        BookingPaymentSchedule $bookingPaymentSchedule,
    ): RedirectResponse {
        $schedule = $property->bookingPaymentSchedules()
            ->whereKey($bookingPaymentSchedule->id)
            ->firstOrFail();

        if ($schedule->payments()->exists() || $schedule->paymentOrders()->exists()) {
            $schedule->update(['is_active' => false]);

            return back()->with('success', 'Jadwal dinonaktifkan. Riwayat dan tagihan booking yang sudah terkait tetap tersimpan.');
        }

        $schedule->delete();

        return back()->with('success', 'Jadwal pembayaran berhasil dihapus.');
    }

    /**
     * @return array{title: string, amount: string, is_active?: bool}
     */
    private function validated(Request $request, bool $includeActiveState = false): array
    {
        if ($request->filled('amount')) {
            $request->merge([
                'amount' => preg_replace('/[^\d]/', '', (string) $request->input('amount')),
            ]);
        }

        return $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'integer', 'min:1', 'max:9999999999999'],
            ...($includeActiveState ? ['is_active' => ['required', 'boolean']] : []),
        ]);
    }

    private function ensureScheduleTotalFitsProperty(
        Property $property,
        float $amount,
        ?BookingPaymentSchedule $schedule = null,
    ): void {
        $activeSchedules = $property->bookingPaymentSchedules()->where('is_active', true);

        if ($schedule !== null) {
            $activeSchedules->where('id', '!=', $schedule->id);
        }

        $scheduledTotal = (float) $activeSchedules->sum('amount') + $amount;
        $lowestLotPrice = $property->lots()
            ->whereNotIn('status', ['finish', 'cancelled'])
            ->min('house_price');

        if ($lowestLotPrice !== null && $scheduledTotal > (float) $lowestLotPrice + 0.009) {
            throw ValidationException::withMessages([
                'amount' => 'Total nominal jadwal tidak boleh melebihi harga kavling terendah di perumahan ini.',
            ]);
        }
    }
}
