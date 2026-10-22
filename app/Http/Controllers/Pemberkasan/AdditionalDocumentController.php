<?php

namespace App\Http\Controllers\Pemberkasan;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PemberkasanDocument;
use App\Models\Property;
use App\Services\PemberkasanDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdditionalDocumentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeDocumentAccess($request);

        $validated = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'document_type' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'uploading', 'uploaded', 'failed', 'archived'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $bookings = Booking::query()
            ->with(['customer:id,name', 'lot.block.property', 'documentProcess'])
            ->where('status', '!=', 'cancelled')
            ->latest('booking_date')
            ->get();
        $documents = PemberkasanDocument::query()
            ->where('document_category', 'other')
            ->with(['booking.customer:id,name', 'booking.lot.block.property', 'uploadedBy:id,name'])
            ->when(isset($validated['property_id']), fn ($query) => $query->whereHas(
                'booking.lot.block',
                fn ($block) => $block->where('property_id', $validated['property_id']),
            ))
            ->when(isset($validated['document_type']), fn ($query) => $query->where('document_type', $validated['document_type']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(! empty($validated['search']), function ($query) use ($validated): void {
                $search = '%'.$validated['search'].'%';
                $query->where(function ($query) use ($search): void {
                    $query->where('original_name', 'like', $search)
                        ->orWhere('document_type', 'like', $search)
                        ->orWhereHas('booking.customer', fn ($customer) => $customer->where('name', 'like', $search))
                        ->orWhereHas('booking.lot', fn ($lot) => $lot
                            ->where('lot_number', 'like', $search)
                            ->orWhereHas('block', fn ($block) => $block
                                ->where('name', 'like', $search)
                                ->orWhereHas('property', fn ($property) => $property->where('name', 'like', $search))));
                });
            })
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('pemberkasan.documents.other', [
            'bookings' => $bookings,
            'documents' => $documents,
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'documentTypes' => PemberkasanDocument::query()
                ->where('document_category', 'other')
                ->distinct()
                ->orderBy('document_type')
                ->pluck('document_type'),
            'filters' => $validated,
        ]);
    }

    public function store(Request $request, PemberkasanDocumentService $documents): RedirectResponse
    {
        $this->authorizeDocumentAccess($request);

        $validated = $request->validate([
            'booking_id' => [
                'required',
                'integer',
                Rule::exists('bookings', 'id')->where('status', '!=', 'cancelled'),
            ],
            'document_type' => ['required', 'string', 'max:100'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);
        $booking = Booking::query()
            ->with(['customer', 'lot.block.property', 'documentProcess'])
            ->findOrFail($validated['booking_id']);

        try {
            $documents->uploadForBooking(
                $booking,
                $request->file('document'),
                $validated['document_type'],
                $request->user(),
                'other',
            );
        } catch (\Throwable $exception) {
            return back()->withErrors(['document' => $exception->getMessage()])->withInput();
        }

        return to_route('pemberkasan.other-documents.index')
            ->with('success', 'Berkas lainnya berhasil diunggah langsung ke Google Drive.');
    }

    private function authorizeDocumentAccess(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user?->isAdmin() ||
                $user?->isAssignedToDivision('pemberkasan'),
            403,
        );
    }
}
