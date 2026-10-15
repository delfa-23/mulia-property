<?php

namespace App\Http\Controllers\Pemberkasan;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PemberkasanDocument;
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

        $bookings = Booking::query()
            ->with(['customer:id,name', 'lot.block.property', 'documentProcess'])
            ->where('status', '!=', 'cancelled')
            ->latest('booking_date')
            ->get();
        $documents = PemberkasanDocument::query()
            ->where('document_category', 'other')
            ->with(['booking.customer:id,name', 'booking.lot.block.property', 'uploadedBy:id,name'])
            ->latest('created_at')
            ->paginate(20);

        return view('pemberkasan.documents.other', [
            'bookings' => $bookings,
            'documents' => $documents,
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
