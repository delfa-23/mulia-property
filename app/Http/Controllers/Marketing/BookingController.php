<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Booking;
use App\Models\BookingHistory;
use App\Models\Customer;
use App\Models\Lot;
use App\Models\Property;
use App\Models\Sales;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $bookings = Booking::query()
            ->with(['lot.block.property', 'customer', 'sales', 'akadSchedule'])
            ->when($request->filled('property_id'), fn ($query) => $query->whereHas('lot.block', fn ($block) => $block->where('property_id', $request->integer('property_id'))))
            ->when($request->filled('lot_id'), fn ($query) => $query->where('lot_id', $request->integer('lot_id')))
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('sales_id'), fn ($query) => $query->where('sales_id', $request->integer('sales_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('booking_date')
            ->paginate(15)
            ->withQueryString();

        return view('marketing.bookings.index', [
            'bookings' => $bookings,
            'properties' => Property::query()->orderBy('name')->get(),
            'blocks' => Block::query()->when($request->filled('property_id'), fn ($query) => $query->where('property_id', $request->integer('property_id')))->orderBy('name')->get(),
            'lotOptions' => Lot::query()->with('block')->when($request->filled('property_id'), fn ($query) => $query->whereHas('block', fn ($block) => $block->where('property_id', $request->integer('property_id'))))->orderBy('block_id')->orderBy('lot_number')->get(),
            'customerOptions' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'salesUsers' => $this->salesOptions(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('marketing.bookings.create', $this->formData());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $this->validated($request);
        $this->ensureLotBelongsToProperty($validated);
        unset($validated['property_id']);

        if ($validated['status'] !== 'booking') {
            throw ValidationException::withMessages([
                'status' => 'Booking baru harus dimulai dari status booking.',
            ]);
        }

        DB::transaction(function () use ($request, $validated, $activityLogger): void {
            $lot = Lot::query()->lockForUpdate()->findOrFail($validated['lot_id']);
            $this->ensureLotAvailable($lot);

            $booking = Booking::create($validated);
            $lot->update(['status' => $this->lotStatusFor($booking->status)]);

            BookingHistory::create([
                'booking_id' => $booking->id,
                'to_status' => $booking->status,
                'changed_by' => $request->user()->id,
                'notes' => $booking->notes,
                'changed_at' => now(),
            ]);
            $activityLogger->record($request, 'booking.created', $booking, 'Booking baru dibuat.');
        });

        return redirect()->route('marketing.bookings.index')->with('success', 'Booking berhasil dibuat.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Booking $booking): View
    {
        $booking->load(['lot.block.property', 'customer', 'sales', 'histories.changedBy']);

        return view('marketing.bookings.show', compact('booking'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Booking $booking): View
    {
        return view('marketing.bookings.edit', array_merge(['booking' => $booking], $this->formData()));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Booking $booking, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $this->validated($request, $booking);
        $this->ensureLotBelongsToProperty($validated);
        unset($validated['property_id']);
        $this->ensureStatusOrder($booking, $validated['status']);

        DB::transaction(function () use ($request, $booking, $validated, $activityLogger): void {
            $lot = Lot::query()->lockForUpdate()->findOrFail($validated['lot_id']);
            $oldStatus = $booking->status;

            if ($lot->id !== $booking->lot_id || $validated['status'] !== 'cancelled') {
                $this->ensureLotAvailable($lot, $booking);
            }

            $booking->update($validated);
            $lot->update(['status' => $this->lotStatusFor($booking->status)]);

            if ($oldStatus !== $booking->status || $booking->wasChanged(['lot_id', 'customer_id', 'notes'])) {
                BookingHistory::create([
                    'booking_id' => $booking->id,
                    'from_status' => $oldStatus,
                    'to_status' => $booking->status,
                    'changed_by' => $request->user()->id,
                    'notes' => $booking->notes,
                    'changed_at' => now(),
                ]);
            }
            $activityLogger->record($request, 'booking.updated', $booking, 'Booking diperbarui.');
        });

        return redirect()->route('marketing.bookings.index')->with('success', 'Booking berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Booking $booking, ActivityLogger $activityLogger): RedirectResponse
    {
        abort_unless($booking->status === 'cancelled', 422, 'Booking harus berstatus cancelled sebelum dihapus.');

        DB::transaction(function () use ($request, $booking, $activityLogger): void {
            $lot = Lot::query()->lockForUpdate()->find($booking->lot_id);

            if ($lot) {
                $lot->update(['status' => 'available']);
            }

            $activityLogger->record($request, 'booking.deleted', $booking, 'Booking cancelled dihapus permanen.');
            $booking->delete();
        });

        return back()->with('success', 'Booking cancelled berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Booking $booking = null): array
    {
        return $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'lot_id' => ['required', 'exists:lots,id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'sales_id' => [
                'nullable',
                Rule::exists('sales', 'id')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
            ],
            'booking_date' => ['required', 'date'],
            'blocking_until' => ['nullable', 'date', 'after_or_equal:booking_date'],
            'status' => ['required', Rule::in(['booking', 'process', 'akad', 'finish', 'cancelled'])],
            'notes' => ['nullable', 'string'],
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'properties' => Property::query()->orderBy('name')->get(),
            'customers' => Customer::query()->orderBy('name')->get(),
            'lots' => Lot::query()->with('block.property')->whereNotIn('status', ['finish', 'cancelled'])->orderBy('lot_number')->get(),
            'salesUsers' => $this->salesOptions(),
        ];
    }

    private function salesOptions()
    {
        return Sales::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function ensureLotAvailable(Lot $lot, ?Booking $currentBooking = null): void
    {
        if (in_array($lot->status, ['blocked', 'finish', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'lot_id' => 'Kavling tidak tersedia untuk booking.',
            ]);
        }

        $conflictingBooking = $lot->bookings()
            ->whereIn('status', ['booking', 'process', 'akad', 'finish'])
            ->when($currentBooking, fn ($query) => $query->where('id', '!=', $currentBooking->id))
            ->exists();

        if ($conflictingBooking) {
            throw ValidationException::withMessages([
                'lot_id' => 'Kavling ini sudah dibooking dan belum dapat dipakai untuk booking baru.',
            ]);
        }
    }

    /** @param array<string, mixed> $validated */
    private function ensureLotBelongsToProperty(array $validated): void
    {
        $lotBelongsToProperty = Lot::query()
            ->whereKey($validated['lot_id'])
            ->whereHas('block', fn ($query) => $query->where('property_id', $validated['property_id']))
            ->exists();

        if (! $lotBelongsToProperty) {
            throw ValidationException::withMessages([
                'lot_id' => 'Kavling tidak termasuk dalam perumahan yang dipilih.',
            ]);
        }
    }

    private function lotStatusFor(string $status): string
    {
        return $status === 'cancelled'
            ? 'available'
            : ($status === 'booking' ? 'booked' : $status);
    }

    private function ensureStatusOrder(Booking $booking, string $status): void
    {
        if ($status === 'cancelled' || $status === $booking->status) {
            return;
        }

        $statusOrder = ['booking' => 0, 'process' => 1, 'akad' => 2, 'finish' => 3];

        if (($statusOrder[$status] ?? 0) <= ($statusOrder[$booking->status] ?? 0)) {
            return;
        }

        if (($statusOrder[$status] ?? 0) > ($statusOrder[$booking->status] ?? 0) + 1) {
            throw ValidationException::withMessages([
                'status' => 'Status booking harus mengikuti urutan: booking, process, akad, lalu finish.',
            ]);
        }

        $booking->loadMissing(['documentProcess', 'bankProcess', 'sp3', 'akadSchedule']);

        if ($status === 'process' && ($booking->documentProcess?->status !== 'complete' || $booking->documentProcess?->bi_checking_status !== 'approved')) {
            throw ValidationException::withMessages([
                'status' => 'Booking harus menyelesaikan berkas mandatory dan BI Checking approved terlebih dahulu.',
            ]);
        }

        if ($status === 'akad' && ($booking->bankProcess?->status !== 'approved' || $booking->sp3?->status !== 'issued' || ! $booking->akadSchedule)) {
            throw ValidationException::withMessages([
                'status' => 'Bank harus approved, SP3 harus issued, dan jadwal akad harus diisi terlebih dahulu.',
            ]);
        }

        if ($status === 'finish' && $booking->akadSchedule?->status !== 'completed') {
            throw ValidationException::withMessages([
                'status' => 'Akad harus berstatus completed terlebih dahulu sebelum booking dapat diselesaikan.',
            ]);
        }
    }
}
