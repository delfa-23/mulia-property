<?php

namespace App\Http\Controllers\Pemberkasan;

use App\Http\Controllers\Controller;
use App\Models\AkadSchedule;
use App\Models\BankProcess;
use App\Models\Booking;
use App\Models\DocumentProcess;
use App\Models\Lot;
use App\Models\PemberkasanDocument;
use App\Models\Property;
use App\Models\Sp3;
use App\Models\User;
use App\Services\PemberkasanDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(protected PemberkasanDocumentService $pemberkasanDocumentService) {}

    public function index(Request $request): View
    {
        $bookings = Booking::query()
            ->with(['customer', 'lot.block.property', 'documentProcess', 'bankProcess', 'sp3', 'akadSchedule'])
            ->where('status', '!=', 'cancelled')
            ->when($request->filled('property_id'), function ($query) use ($request): void {
                $query->whereHas('lot.block', fn ($block) => $block->where('property_id', $request->integer('property_id')));
            })
            ->when($request->filled('lot_id'), function ($query) use ($request): void {
                $query->where('lot_id', $request->integer('lot_id'));
            })
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', $request->string('status'));
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $query->whereHas('customer', fn ($customer) => $customer->where('name', 'like', $search));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $properties = Property::query()->orderBy('name')->get();
        $lotOptions = Lot::query()
            ->with('block.property')
            ->when($request->filled('property_id'), fn ($query) => $query->whereHas('block', fn ($block) => $block->where('property_id', $request->integer('property_id'))))
            ->orderBy('block_id')
            ->orderBy('lot_number')
            ->get();

        return view('pemberkasan.bookings.index', compact('bookings', 'properties', 'lotOptions'));
    }

    public function show(Booking $booking): View
    {
        $booking->load(['customer', 'sales', 'lot.block.property', 'documentProcess.pic', 'bankProcess.pic', 'sp3.createdBy', 'akadSchedule.createdBy', 'pemberkasanDocuments']);

        return view('pemberkasan.bookings.show', [
            'booking' => $booking,
            'picUsers' => User::query()->whereIn('role', ['tl_pemberkasan', 'staff_pemberkasan'])->orderBy('name')->get(),
        ]);
    }

    public function uploadDocument(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'string', 'max:100'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        try {
            $document = $this->pemberkasanDocumentService->uploadForBooking(
                $booking,
                $request->file('document'),
                $validated['document_type'],
                $request->user(),
            );

            return back()->with(
                'success',
                $document->status === 'uploaded'
                    ? 'Dokumen '.$document->document_type.' berhasil diupload ke Google Drive.'
                    : 'Dokumen tersimpan di sistem dan sedang menunggu upload ke Google Drive.',
            );
        } catch (\Throwable $exception) {
            return back()->withErrors(['document' => $exception->getMessage()])->withInput();
        }
    }

    public function retryUpload(Request $request, PemberkasanDocument $pemberkasanDocument): RedirectResponse
    {
        try {
            $document = $this->pemberkasanDocumentService->retryUpload($pemberkasanDocument, $request->user());

            return back()->with(
                'success',
                $document->status === 'uploaded'
                    ? 'Upload ulang dokumen berhasil.'
                    : 'Upload ulang dokumen telah ditambahkan ke antrean Google Drive.',
            );
        } catch (\Throwable $exception) {
            return back()->withErrors(['document' => $exception->getMessage()]);
        }
    }

    public function syncCustomerDocuments(Booking $booking): RedirectResponse
    {
        $documents = $this->pemberkasanDocumentService->syncCustomerDocuments(
            $booking->load(['customer', 'lot.block.property']),
            ['KTP', 'KK', 'NPWP', 'Dokumen Pemesanan'],
        );

        return back()->with(
            'success',
            $documents === []
                ? 'Tidak ada dokumen lama yang perlu disinkronkan ke Google Drive.'
                : count($documents).' dokumen lama ditambahkan ke antrean Google Drive.',
        );
    }

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_birth_place' => ['nullable', 'string', 'max:255'],
            'customer_birth_date' => ['nullable', 'date'],
            'customer_nik' => ['nullable', 'digits:16', 'unique:customers,nik,'.$booking->customer_id],
            'customer_marital_status' => ['nullable', Rule::in(['Belum Menikah', 'Menikah'])],
            'customer_occupation' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_address' => ['nullable', 'string'],
            'customer_ktp_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'customer_kk_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'customer_npwp_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'customer_booking_form_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'document_total_required' => ['nullable', 'integer', 'min:0'],
            'document_total_completed' => ['nullable', 'integer', 'min:0', 'lte:document_total_required'],
            'document_status' => ['nullable', Rule::in(['incomplete', 'complete', 'revision'])],
            'bi_checking_status' => ['nullable', Rule::in(['not_checked', 'processing', 'approved', 'rejected', 'revision'])],
            'document_pic_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['tl_pemberkasan', 'staff_pemberkasan']))],
            'document_notes' => ['nullable', 'string'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_status' => ['nullable', Rule::in(['not_submitted', 'submitted', 'processing', 'approved', 'rejected', 'revision'])],
            'bank_pic_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['tl_pemberkasan', 'staff_pemberkasan']))],
            'bank_notes' => ['nullable', 'string'],
            'sp3_number' => ['nullable', 'string', 'max:255'],
            'sp3_status' => ['nullable', Rule::in(['pending', 'issued', 'cancelled'])],
            'sp3_issued_at' => ['nullable', 'date'],
            'sp3_notes' => ['nullable', 'string'],
            'akad_scheduled_at' => ['nullable', 'date'],
            'akad_status' => ['nullable', Rule::in(['scheduled', 'completed', 'rescheduled', 'cancelled'])],
            'akad_location' => ['nullable', 'string', 'max:255'],
            'akad_notes' => ['nullable', 'string'],
        ]);

        $this->ensureProcessOrder($booking, $validated);

        $customerDocumentTypes = [];

        DB::transaction(function () use ($request, $booking, $validated, &$customerDocumentTypes): void {
            foreach ([
                'customer_ktp_file' => 'KTP',
                'customer_kk_file' => 'KK',
                'customer_npwp_file' => 'NPWP',
                'customer_booking_form_file' => 'Dokumen Pemesanan',
            ] as $requestField => $documentType) {
                if (! $request->hasFile($requestField)) {
                    continue;
                }

                $existingDocument = PemberkasanDocument::query()
                    ->where('booking_id', $booking->id)
                    ->where('document_type', $documentType)
                    ->lockForUpdate()
                    ->first();

                if ($existingDocument && in_array($existingDocument->status, ['pending', 'uploading'], true)) {
                    throw ValidationException::withMessages([
                        $requestField => 'Dokumen ini masih menunggu atau sedang diproses oleh Google Drive.',
                    ]);
                }
            }

            $customer = $booking->customer;
            $customer->update([
                'name' => $validated['customer_name'] ?? $customer->name,
                'birth_place' => $validated['customer_birth_place'] ?? null,
                'birth_date' => $validated['customer_birth_date'] ?? null,
                'nik' => $validated['customer_nik'] ?? null,
                'marital_status' => $validated['customer_marital_status'] ?? null,
                'occupation' => $validated['customer_occupation'] ?? null,
                'phone' => $validated['customer_phone'] ?? null,
                'email' => $validated['customer_email'] ?? null,
                'address' => $validated['customer_address'] ?? null,
            ]);

            foreach ([
                'customer_ktp_file' => ['ktp_file', 'KTP'],
                'customer_kk_file' => ['kk_file', 'KK'],
                'customer_npwp_file' => ['npwp_file', 'NPWP'],
                'customer_booking_form_file' => ['booking_form_file', 'Dokumen Pemesanan'],
            ] as $requestField => $customerField) {
                if ($request->hasFile($requestField)) {
                    [$field, $documentType] = $customerField;

                    if ($customer->{$field}) {
                        Storage::disk('public')->delete($customer->{$field});
                    }

                    $customer->update([
                        $field => $request->file($requestField)->store('customers/documents', 'public'),
                    ]);
                    $customerDocumentTypes[] = $documentType;
                }
            }

            if ($customerDocumentTypes !== []) {
                $this->pemberkasanDocumentService->syncCustomerDocuments(
                    $booking->fresh(['customer', 'lot.block.property']),
                    $customerDocumentTypes,
                    replaceExisting: true,
                );
            }

            $document = DocumentProcess::updateOrCreate(
                ['booking_id' => $booking->id],
                [
                    'total_required' => $validated['document_total_required'] ?? $booking->documentProcess?->total_required ?? 0,
                    'total_completed' => $validated['document_total_completed'] ?? $booking->documentProcess?->total_completed ?? 0,
                    'status' => $validated['document_status'] ?? $booking->documentProcess?->status ?? 'incomplete',
                    'bi_checking_status' => $validated['bi_checking_status'] ?? $booking->documentProcess?->bi_checking_status ?? 'not_checked',
                    'pic_id' => $validated['document_pic_id'] ?? null,
                    'started_at' => ($validated['document_total_completed'] ?? $booking->documentProcess?->total_completed ?? 0) > 0 ? ($booking->documentProcess?->started_at ?? now()) : null,
                    'completed_at' => ($validated['document_status'] ?? $booking->documentProcess?->status ?? 'incomplete') === 'complete' ? now() : null,
                    'notes' => $validated['document_notes'] ?? null,
                ]
            );

            if (($validated['bank_name'] ?? null) !== null && ($validated['bank_name'] ?? '') !== '' || ($validated['bank_status'] ?? 'not_submitted') !== 'not_submitted' || ($validated['bank_pic_id'] ?? null) !== null || ($validated['bank_notes'] ?? null) !== null) {
                BankProcess::updateOrCreate(
                    ['booking_id' => $booking->id],
                    [
                        'bank_name' => $validated['bank_name'] ?? $booking->bankProcess?->bank_name,
                        'status' => $validated['bank_status'] ?? $booking->bankProcess?->status ?? 'not_submitted',
                        'pic_id' => $validated['bank_pic_id'] ?? null,
                        'submitted_at' => in_array($validated['bank_status'] ?? 'not_submitted', ['submitted', 'processing', 'approved'], true) ? ($booking->bankProcess?->submitted_at ?? now()) : null,
                        'approved_at' => ($validated['bank_status'] ?? null) === 'approved' ? now() : null,
                        'notes' => $validated['bank_notes'] ?? null,
                    ]
                );
            }

            if (($validated['sp3_number'] ?? null) !== null || ($validated['sp3_issued_at'] ?? null) !== null || ($validated['sp3_notes'] ?? null) !== null || ($validated['sp3_status'] ?? 'pending') !== 'pending') {
                Sp3::updateOrCreate(
                    ['booking_id' => $booking->id],
                    ['sp3_number' => $validated['sp3_number'] ?? null, 'status' => $validated['sp3_status'] ?? 'pending', 'issued_at' => $validated['sp3_issued_at'] ?? null, 'created_by' => $request->user()->id, 'notes' => $validated['sp3_notes'] ?? null]
                );
            }

            if (($validated['akad_scheduled_at'] ?? null) !== null || ($validated['akad_location'] ?? null) !== null || ($validated['akad_notes'] ?? null) !== null || ($validated['akad_status'] ?? 'scheduled') !== 'scheduled') {
                AkadSchedule::updateOrCreate(
                    ['booking_id' => $booking->id],
                    ['scheduled_at' => $validated['akad_scheduled_at'] ?? $booking->akadSchedule?->scheduled_at ?? now(), 'status' => $validated['akad_status'] ?? 'scheduled', 'location' => $validated['akad_location'] ?? null, 'created_by' => $request->user()->id, 'notes' => $validated['akad_notes'] ?? null]
                );
            }

            $booking->load(['documentProcess', 'bankProcess', 'sp3', 'akadSchedule']);
            $bookingStatus = $this->bookingStatusFor($booking);
            $booking->update(['status' => $bookingStatus]);
            $booking->lot()->update(['status' => $bookingStatus === 'booking' ? 'booked' : $bookingStatus]);
        });

        return redirect()->route('pemberkasan.bookings.show', $booking)->with('success', 'Data pemberkasan berhasil diperbarui.');
    }

    /** @param array<string, mixed> $validated */
    private function ensureProcessOrder(Booking $booking, array $validated): void
    {
        $booking->loadMissing(['documentProcess', 'bankProcess', 'sp3', 'akadSchedule']);

        $documentStatus = $validated['document_status'] ?? $booking->documentProcess?->status ?? 'incomplete';
        $biCheckingStatus = $validated['bi_checking_status'] ?? $booking->documentProcess?->bi_checking_status ?? 'not_checked';
        $bankStatus = $validated['bank_status'] ?? $booking->bankProcess?->status ?? 'not_submitted';
        $sp3Status = $validated['sp3_status'] ?? $booking->sp3?->status ?? 'pending';
        $hasBankData = ($validated['bank_name'] ?? null) !== null
            || $bankStatus !== 'not_submitted'
            || ($validated['bank_pic_id'] ?? null) !== null
            || ($validated['bank_notes'] ?? null) !== null;
        $hasSp3Data = ($validated['sp3_number'] ?? null) !== null
            || ($validated['sp3_issued_at'] ?? null) !== null
            || ($validated['sp3_notes'] ?? null) !== null
            || $sp3Status !== 'pending';
        $hasAkadData = ($validated['akad_scheduled_at'] ?? null) !== null
            || ($validated['akad_location'] ?? null) !== null
            || ($validated['akad_notes'] ?? null) !== null
            || ($validated['akad_status'] ?? 'scheduled') !== 'scheduled';

        if ($hasBankData && ($documentStatus !== 'complete' || $biCheckingStatus !== 'approved')) {
            throw ValidationException::withMessages([
                'bank_name' => 'Berkas mandatory harus complete dan BI Checking harus approved sebelum proses bank.',
            ]);
        }

        if ($hasSp3Data && ($bankStatus !== 'approved')) {
            throw ValidationException::withMessages([
                'sp3_number' => 'Bank harus approved sebelum proses SP3 dapat dilanjutkan.',
            ]);
        }

        if ($hasAkadData && ($sp3Status !== 'issued')) {
            throw ValidationException::withMessages([
                'akad_scheduled_at' => 'SP3 harus issued sebelum jadwal akad dapat diisi.',
            ]);
        }
    }

    private function bookingStatusFor(Booking $booking): string
    {
        if ($booking->akadSchedule?->status === 'completed') {
            return 'finish';
        }

        if ($booking->akadSchedule) {
            return 'akad';
        }

        if ($booking->documentProcess?->status === 'complete' && $booking->documentProcess?->bi_checking_status === 'approved') {
            return 'process';
        }

        return 'booking';
    }
}
