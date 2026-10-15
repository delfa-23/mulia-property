<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Booking;
use App\Models\BookingHistory;
use App\Models\Customer;
use App\Models\Lot;
use App\Models\MarketingLead;
use App\Models\Property;
use App\Models\Sales;
use App\Services\ActivityLogger;
use App\Services\MarketingLeadAccess;
use App\Services\PemberkasanDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $bookings = Booking::query()
            ->with([
                'lot.block.property',
                'customer',
                'sales',
                'akadSchedule',
                'payments',
                'histories' => fn ($query) => $query
                    ->where('to_status', 'cancelled')
                    ->where('notes', 'Booking dibatalkan otomatis karena batas blocking terlewati.'),
            ])
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

    public function create(Request $request, MarketingLeadAccess $leadAccess): View
    {
        return view('marketing.bookings.create', $this->formData($request, $leadAccess));
    }

    public function store(
        Request $request,
        ActivityLogger $activityLogger,
        MarketingLeadAccess $leadAccess,
    ): RedirectResponse {
        $validated = $this->validated($request);
        if (! empty($validated['lead_id'])) {
            $lead = MarketingLead::query()->findOrFail($validated['lead_id']);
            $leadAccess->authorizeLeadOwner($request->user(), $lead);
            if ($lead->isArchived()) {
                throw ValidationException::withMessages(['lead_id' => 'Prospek arsip tidak dapat dipilih untuk booking baru.']);
            }
        }
        $this->ensureLotBelongsToProperty($validated);
        unset($validated['property_id']);
        $dpPaidAmount = (float) ($validated['dp_paid_amount'] ?? 0);
        $akadPaidAmount = (float) ($validated['akad_paid_amount'] ?? 0);
        unset($validated['dp_paid_amount']);
        unset($validated['akad_paid_amount']);

        if ($validated['status'] !== 'booking') {
            throw ValidationException::withMessages([
                'status' => 'Booking baru harus dimulai dari status booking.',
            ]);
        }

        DB::transaction(function () use ($request, $validated, $dpPaidAmount, $akadPaidAmount, $activityLogger): void {
            $this->resolveCustomer($validated);
            $lot = Lot::query()->lockForUpdate()->findOrFail($validated['lot_id']);
            $this->ensureLotAvailable($lot);

            $booking = Booking::create([
                ...$validated,
                'house_price_snapshot' => $lot->house_price,
                'dp_percentage' => Booking::DP_PERCENTAGE,
            ]);

            if ($dpPaidAmount > (float) $booking->house_price_snapshot + 0.009) {
                throw ValidationException::withMessages([
                    'dp_paid_amount' => 'Nominal DP yang sudah dibayar tidak boleh melebihi harga rumah.',
                ]);
            }

            if ($akadPaidAmount > (float) $booking->akad_fee + 0.009) {
                throw ValidationException::withMessages([
                    'akad_paid_amount' => 'Biaya akad yang sudah dibayar tidak boleh melebihi total biaya akad.',
                ]);
            }

            if ($akadPaidAmount > 0 && $dpPaidAmount + 0.009 < (float) $booking->house_price_snapshot) {
                throw ValidationException::withMessages([
                    'akad_paid_amount' => 'Pembayaran biaya akad dapat dicatat setelah DP harga rumah lunas.',
                ]);
            }

            if ($dpPaidAmount > 0) {
                $this->recordBookingPayment($booking, 'dp', $dpPaidAmount, $request, $activityLogger, 'Pembayaran DP awal dicatat dari form booking.');
            }

            if ($akadPaidAmount > 0) {
                $this->recordBookingPayment($booking, 'akad_fee', $akadPaidAmount, $request, $activityLogger, 'Pembayaran biaya akad awal dicatat dari form booking.');
            }

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

    public function show(Booking $booking): View
    {
        $booking->load([
            'lot.block.property',
            'customer',
            'marketingLead',
            'sales',
            'histories.changedBy',
            'payments.recordedBy',
            'documentProcess',
            'pemberkasanDocuments.uploadedBy',
        ]);
        $paymentSummary = $this->paymentSummary($booking);

        return view('marketing.bookings.show', compact('booking', 'paymentSummary'));
    }

    public function editDocuments(Booking $booking): View
    {
        $booking->load([
            'lot.block.property',
            'customer',
            'pemberkasanDocuments.uploadedBy',
        ]);

        return view('marketing.bookings.edit-documents', [
            'booking' => $booking,
            'documentGroups' => [
                'Berkas BI Checking' => PemberkasanDocumentService::BI_CHECKING_DOCUMENT_TYPES,
                'Berkas Customer' => PemberkasanDocumentService::ADDITIONAL_MARKETING_DOCUMENT_TYPES,
                'Berkas Properti' => PemberkasanDocumentService::PROPERTY_DOCUMENT_TYPES,
            ],
        ]);
    }

    public function updateDocuments(
        Request $request,
        Booking $booking,
        PemberkasanDocumentService $documentService,
    ): RedirectResponse {
        $validated = $request->validate([
            'document_uploads' => ['required', 'array', 'min:1'],
            'document_uploads.*' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        $allowedDocumentTypes = array_merge(
            PemberkasanDocumentService::BI_CHECKING_DOCUMENT_TYPES,
            PemberkasanDocumentService::ADDITIONAL_MARKETING_DOCUMENT_TYPES,
            PemberkasanDocumentService::PROPERTY_DOCUMENT_TYPES,
        );
        $documentUploads = $validated['document_uploads'];

        foreach (array_keys($documentUploads) as $documentType) {
            if (! in_array($documentType, $allowedDocumentTypes, true)) {
                throw ValidationException::withMessages([
                    'document_uploads' => 'Terdapat jenis berkas yang tidak valid.',
                ]);
            }
        }

        $uploadedCount = 0;
        $uploadErrors = [];

        foreach ($documentUploads as $documentType => $uploadedFile) {
            try {
                $documentService->uploadForBooking(
                    $booking,
                    $uploadedFile,
                    $documentType,
                    $request->user(),
                );
                $uploadedCount++;
            } catch (\RuntimeException $exception) {
                $uploadErrors['document_uploads.'.$documentType] = $exception->getMessage();
            }
        }

        $response = redirect()->route('marketing.bookings.documents.edit', $booking);

        if ($uploadedCount > 0) {
            $response->with('success', $uploadedCount.' berkas berhasil diunggah ke Google Drive.');
        }

        if ($uploadErrors !== []) {
            $response->withErrors($uploadErrors);
        }

        return $response;
    }

    public function edit(Request $request, Booking $booking, MarketingLeadAccess $leadAccess): View
    {
        $booking->load(['lot', 'payments']);
        $paymentSummary = $this->paymentSummary($booking);

        return view('marketing.bookings.edit', array_merge(
            ['booking' => $booking, 'paymentSummary' => $paymentSummary],
            $this->formData($request, $leadAccess, $booking),
            ['paymentTermsLocked' => $booking->payments->isNotEmpty()]
        ));
    }

    public function update(
        Request $request,
        Booking $booking,
        ActivityLogger $activityLogger,
        MarketingLeadAccess $leadAccess,
    ): RedirectResponse {
        $validated = $this->validated($request, $booking);
        if (
            ! empty($validated['lead_id']) &&
            (int) $validated['lead_id'] !== (int) $booking->lead_id
        ) {
            $lead = MarketingLead::query()->findOrFail($validated['lead_id']);
            $leadAccess->authorizeLeadOwner($request->user(), $lead);
            if ($lead->isArchived()) {
                throw ValidationException::withMessages(['lead_id' => 'Prospek arsip tidak dapat dipilih untuk booking.']);
            }
        }
        $this->ensureLotBelongsToProperty($validated);
        unset($validated['property_id']);
        $dpPaidAmount = isset($validated['dp_paid_amount'])
            ? (float) $validated['dp_paid_amount']
            : null;
        $akadPaidAmount = isset($validated['akad_paid_amount'])
            ? (float) $validated['akad_paid_amount']
            : null;
        unset($validated['dp_paid_amount']);
        unset($validated['akad_paid_amount']);
        $this->ensureStatusOrder($booking, $validated['status']);

        DB::transaction(function () use ($request, $booking, $validated, $dpPaidAmount, $akadPaidAmount, $activityLogger): void {
            $this->resolveCustomer($validated);
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $lotIds = collect([$lockedBooking->lot_id, $validated['lot_id']])->unique()->sort()->values();
            $lockedLots = Lot::query()->whereIn('id', $lotIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $targetLot = $lockedLots->get($validated['lot_id']);
            $oldLot = $lockedLots->get($lockedBooking->lot_id);
            $hasPayments = $lockedBooking->payments()->exists();

            if (
                $hasPayments &&
                ((int) $lockedBooking->customer_id !== (int) $validated['customer_id'] ||
                    $lockedBooking->lot_id !== $targetLot->id ||
                    (float) $lockedBooking->akad_fee !== (float) $validated['akad_fee'])
            ) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Data customer, kavling, harga, dan biaya akad tidak dapat diubah setelah pembayaran dicatat.',
                ]);
            }

            if ($targetLot->id !== $lockedBooking->lot_id || $validated['status'] !== 'cancelled') {
                $this->ensureLotAvailable($targetLot, $lockedBooking);
            }

            $validated['house_price_snapshot'] = $hasPayments
                ? $lockedBooking->house_price_snapshot
                : $targetLot->house_price;
            $validated['dp_percentage'] = Booking::DP_PERCENTAGE;
            $housePrice = (float) $validated['house_price_snapshot'];
            $existingDpPaid = (float) $lockedBooking->payments()->whereIn('stage', ['dp', 'bank_settlement'])->sum('amount');
            $existingAkadPaid = (float) $lockedBooking->payments()->where('stage', 'akad_fee')->sum('amount');
            $requestedDpPaid = $dpPaidAmount === null
                ? $existingDpPaid
                : round($dpPaidAmount, 2);
            $requestedAkadPaid = $akadPaidAmount === null
                ? $existingAkadPaid
                : round($akadPaidAmount, 2);
            $totalDp = round($housePrice * Booking::DP_PERCENTAGE / 100, 2);

            if ($requestedDpPaid + 0.009 < $existingDpPaid) {
                throw ValidationException::withMessages([
                    'dp_paid_amount' => 'Nominal DP yang sudah dibayar tidak dapat lebih kecil dari pembayaran yang tercatat.',
                ]);
            }

            if ($requestedAkadPaid + 0.009 < $existingAkadPaid) {
                throw ValidationException::withMessages([
                    'akad_paid_amount' => 'Nominal biaya akad yang sudah dibayar tidak dapat lebih kecil dari pembayaran yang tercatat.',
                ]);
            }

            if ($requestedDpPaid > $totalDp + 0.009) {
                throw ValidationException::withMessages([
                    'dp_paid_amount' => 'Nominal DP yang sudah dibayar tidak boleh melebihi harga rumah.',
                ]);
            }

            if ($requestedAkadPaid > (float) $validated['akad_fee'] + 0.009) {
                throw ValidationException::withMessages([
                    'akad_paid_amount' => 'Biaya akad yang sudah dibayar tidak boleh melebihi total biaya akad.',
                ]);
            }

            if (
                $requestedAkadPaid > $existingAkadPaid + 0.009 &&
                $requestedDpPaid + 0.009 < $totalDp
            ) {
                throw ValidationException::withMessages([
                    'akad_paid_amount' => 'Pembayaran biaya akad dapat dicatat setelah DP harga rumah lunas.',
                ]);
            }

            $lockedBooking->update($validated);

            $additionalDpPaid = round($requestedDpPaid - $existingDpPaid, 2);

            if ($additionalDpPaid > 0.009) {
                $this->recordBookingPayment($lockedBooking, 'dp', $additionalDpPaid, $request, $activityLogger, 'Tambahan pembayaran DP dicatat dari form booking.');
            }

            $additionalAkadPaid = round($requestedAkadPaid - $existingAkadPaid, 2);

            if ($additionalAkadPaid > 0.009) {
                $this->recordBookingPayment($lockedBooking, 'akad_fee', $additionalAkadPaid, $request, $activityLogger, 'Tambahan pembayaran biaya akad dicatat dari form booking.');
            }

            if ($oldLot && $oldLot->id !== $targetLot->id) {
                $oldLot->update(['status' => 'available']);
            }
            $targetLot->update(['status' => $this->lotStatusFor($lockedBooking->status)]);

            if ($lockedBooking->wasChanged('status') || $lockedBooking->wasChanged(['lot_id', 'customer_id', 'notes'])) {
                BookingHistory::create([
                    'booking_id' => $lockedBooking->id,
                    'from_status' => $booking->status,
                    'to_status' => $lockedBooking->status,
                    'changed_by' => $request->user()->id,
                    'notes' => $lockedBooking->notes,
                    'changed_at' => now(),
                ]);
            }
            $activityLogger->record($request, 'booking.updated', $lockedBooking, 'Booking diperbarui.');
        });

        return redirect()->route('marketing.bookings.index')->with('success', 'Booking berhasil diperbarui.');
    }

    public function storePayment(Request $request, Booking $booking, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validate([
            'stage' => ['required', Rule::in(['dp', 'akad_fee'])],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $booking, $validated, $activityLogger): void {
            $lockedBooking = Booking::query()->with('lot')->lockForUpdate()->findOrFail($booking->id);

            if (in_array($lockedBooking->status, ['cancelled', 'finish'], true)) {
                throw ValidationException::withMessages([
                    'stage' => 'Pembayaran tidak dapat dicatat untuk booking yang sudah selesai atau dibatalkan.',
                ]);
            }

            $summary = $this->paymentSummary($lockedBooking);
            $nextStage = collect($summary['stages'])->first(fn (array $stage): bool => $stage['remaining'] > 0.009);

            if ($nextStage === null || $validated['stage'] !== $nextStage['key']) {
                throw ValidationException::withMessages([
                    'stage' => 'Pembayaran harus mengikuti urutan DP 100% harga rumah, lalu biaya akad.',
                ]);
            }

            if ((float) $validated['amount'] > $nextStage['remaining'] + 0.009) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal melebihi sisa tagihan tahap ini.',
                ]);
            }

            $payment = $lockedBooking->payments()->create([
                ...$validated,
                'recorded_by' => $request->user()->id,
            ]);
            $activityLogger->record($request, 'booking.payment_recorded', $payment, 'Pembayaran booking dicatat.');
        });

        return redirect()->route('marketing.bookings.show', $booking)->with('success', 'Pembayaran berhasil dicatat.');
    }

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
        $validated = $request->validate([
            'property_id' => ['required', 'exists:properties,id'],
            'lot_id' => ['required', 'exists:lots,id'],
            'customer_mode' => ['required', Rule::in(['existing', 'new'])],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'new_customer.name' => [Rule::requiredIf($request->input('customer_mode') === 'new'), 'nullable', 'string', 'max:255'],
            'new_customer.nik' => ['nullable', 'digits:16', Rule::unique('customers', 'nik')],
            'new_customer.phone' => ['nullable', 'string', 'max:50'],
            'new_customer.email' => ['nullable', 'email', 'max:255'],
            'new_customer.address' => ['nullable', 'string'],
            'new_customer.birth_place' => ['nullable', 'string', 'max:255'],
            'new_customer.birth_date' => ['nullable', 'date'],
            'new_customer.marital_status' => ['nullable', Rule::in(['Belum Menikah', 'Menikah'])],
            'new_customer.occupation' => ['nullable', 'string', 'max:255'],
            'sales_id' => [
                'nullable',
                Rule::exists('sales', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'lead_id' => ['nullable', 'integer', 'exists:marketing_leads,id'],
            'applicant_work_location_url' => ['nullable', 'url', 'max:2048'],
            'spouse_work_location_url' => ['nullable', 'url', 'max:2048'],
            'booking_date' => ['required', 'date'],
            'blocking_until' => [
                Rule::requiredIf($request->input('status') === 'booking'),
                'nullable',
                'date',
                'after_or_equal:booking_date',
                Rule::when($request->input('status') === 'booking', ['after:now']),
            ],
            'dp_paid_amount' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0'],
            'akad_paid_amount' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0'],
            'akad_fee' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'status' => ['required', Rule::in(['booking', 'process', 'akad', 'finish', 'cancelled'])],
            'notes' => ['nullable', 'string'],
        ], [
            'dp_paid_amount.numeric' => 'Nominal DP harus berupa angka Rupiah yang valid.',
            'akad_paid_amount.numeric' => 'Nominal biaya akad harus berupa angka Rupiah yang valid.',
        ]);

        if ($validated['customer_mode'] === 'existing' && empty($validated['customer_id'])) {
            throw ValidationException::withMessages([
                'customer_id' => 'Pilih customer yang sudah terdaftar.',
            ]);
        }

        return $validated;
    }

    private function recordBookingPayment(
        Booking $booking,
        string $stage,
        float $amount,
        Request $request,
        ActivityLogger $activityLogger,
        string $notes
    ): void {
        $payment = $booking->payments()->create([
            'stage' => $stage,
            'amount' => $amount,
            'paid_at' => now(),
            'recorded_by' => $request->user()->id,
            'notes' => $notes,
        ]);

        $activityLogger->record($request, 'booking.payment_recorded', $payment, 'Pembayaran booking dicatat.');
    }

    /** @param array<string, mixed> $validated */
    private function resolveCustomer(array &$validated): void
    {
        if ($validated['customer_mode'] === 'new') {
            $customer = Customer::create($validated['new_customer']);
            $validated['customer_id'] = $customer->id;
        }

        unset($validated['customer_mode'], $validated['new_customer']);
    }

    /** @return array<string, mixed> */
    private function formData(
        Request $request,
        MarketingLeadAccess $leadAccess,
        ?Booking $booking = null,
    ): array {
        $leadQuery = $leadAccess->scopeLeads(MarketingLead::query(), $request->user())
            ->where(function ($query) use ($booking): void {
                $query->whereNull('archived_at');
                if ($booking?->lead_id) {
                    $query->orWhereKey($booking->lead_id);
                }
            });
        $leads = $leadQuery
            ->orderByDesc('date_received')
            ->orderBy('name')
            ->get(['id', 'name', 'date_received']);
        $requestedLeadId = old('lead_id', $request->query('lead_id', $booking?->lead_id));
        $selectedLeadId = $leads->firstWhere('id', (int) $requestedLeadId)?->id;

        return [
            'properties' => Property::query()->orderBy('name')->get(),
            'customers' => Customer::query()->orderBy('name')->get(),
            'lots' => Lot::query()->with('block.property')->whereNotIn('status', ['finish', 'cancelled'])->orderBy('lot_number')->get(),
            'salesUsers' => $this->salesOptions(),
            'leads' => $leads,
            'selectedLeadId' => $selectedLeadId,
        ];
    }

    private function salesOptions()
    {
        return Sales::query()->where('is_active', true)->orderBy('name')->get();
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

    /** @return array{stages: list<array{key: string, label: string, due: float, paid: float, remaining: float, paid_percentage: float}>, house_price: float, dp_total: float, dp_paid: float, dp_paid_percentage: float, total_paid: float, total_paid_price: float, paid_percentage: float} */
    private function paymentSummary(Booking $booking): array
    {
        $housePrice = (float) ($booking->house_price_snapshot ?? $booking->lot->house_price);
        $downPaymentTotal = $housePrice;
        $targets = [
            'dp' => ['label' => 'Pembayaran DP', 'due' => $downPaymentTotal],
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
        $totalPaidPrice = $dpPaid;

        return [
            'stages' => $stages,
            'house_price' => $housePrice,
            'dp_total' => $downPaymentTotal,
            'dp_paid' => $dpPaid,
            'dp_paid_percentage' => $housePrice > 0 ? min(100, $dpPaid / $housePrice * 100) : 0,
            'total_paid' => $totalPaid,
            'total_paid_price' => $totalPaidPrice,
            'paid_percentage' => $housePrice > 0 ? min(100, $totalPaidPrice / $housePrice * 100) : 0,
        ];
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

        if ($status === 'akad') {
            $requiredPayments = collect($this->paymentSummary($booking)['stages'])
                ->whereIn('key', ['dp', 'akad_fee']);

            if ($requiredPayments->contains(fn (array $stage): bool => $stage['remaining'] > 0.009)) {
                throw ValidationException::withMessages([
                    'status' => 'Total DP dan biaya akad harus lunas sebelum status booking menjadi akad.',
                ]);
            }
        }

        if ($status === 'finish' && $booking->akadSchedule?->status !== 'completed') {
            throw ValidationException::withMessages([
                'status' => 'Akad harus berstatus completed terlebih dahulu sebelum booking dapat diselesaikan.',
            ]);
        }

        if ($status === 'finish') {
            $unpaidStages = collect($this->paymentSummary($booking)['stages'])
                ->filter(fn (array $stage): bool => $stage['remaining'] > 0.009);

            if ($unpaidStages->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'status' => 'DP 100% harga rumah dan biaya akad harus lunas sebelum booking dapat diselesaikan.',
                ]);
            }
        }
    }
}
