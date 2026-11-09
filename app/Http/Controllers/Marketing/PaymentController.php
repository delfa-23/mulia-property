<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Property;
use App\Services\BookingPaymentSummaryService;
use App\Services\MarketingLeadAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function updateUnpaidReason(
        Request $request,
        Booking $booking,
        MarketingLeadAccess $leadAccess,
    ): RedirectResponse {
        $leadAccess->authorizeBooking($request->user(), $booking);

        $validated = $request->validate([
            'unpaid_payment_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        if ($booking->unpaid_payment_reason_status === 'rejected') {
            abort(403, 'Alasan yang ditolak tidak dapat diubah.');
        }

        $canEdit = $booking->unpaid_payment_reason === null
            || $booking->unpaid_payment_reason_status === 'approved';

        abort_unless($canEdit, 403, 'Alasan pembayaran belum lunas menunggu persetujuan admin.');

        $booking->update([
            'unpaid_payment_reason' => trim($validated['unpaid_payment_reason']),
            'unpaid_payment_reason_status' => 'pending',
            'unpaid_payment_reason_reviewed_at' => null,
            'unpaid_payment_reason_reviewed_by' => null,
        ]);

        return back()->with('status', 'Alasan pembayaran belum lunas berhasil disimpan.');
    }

    public function approveUnpaidReason(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($booking->unpaid_payment_reason_status === 'pending', 422, 'Alasan belum menunggu persetujuan.');

        $booking->update([
            'unpaid_payment_reason_status' => 'approved',
            'unpaid_payment_reason_reviewed_at' => now(),
            'unpaid_payment_reason_reviewed_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Alasan pembayaran belum lunas disetujui.');
    }

    public function rejectUnpaidReason(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($booking->unpaid_payment_reason_status === 'pending', 422, 'Alasan belum menunggu persetujuan.');

        $booking->update([
            'unpaid_payment_reason_status' => 'rejected',
            'unpaid_payment_reason_reviewed_at' => now(),
            'unpaid_payment_reason_reviewed_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Alasan pembayaran belum lunas ditolak.');
    }

    public function index(
        Request $request,
        BookingPaymentSummaryService $paymentSummaryService,
        MarketingLeadAccess $leadAccess,
    ): View {
        $filters = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'status' => ['nullable', Rule::in(['booking', 'process', 'akad', 'finish', 'cancelled'])],
            'search' => ['nullable', 'string', 'max:100'],
            'archive' => ['nullable', Rule::in(['active', 'archived'])],
        ]);
        $archive = $filters['archive'] ?? 'active';

        $bookings = $leadAccess->scopeBookings(Booking::query()
            ->with([
                'lot.block.property',
                'customer',
                'payments.paymentOrder',
                'payments.recordedBy',
                'paymentOrders',
            ]), $request->user())
            ->when(
                isset($filters['property_id']),
                fn ($query) => $query->whereHas(
                    'lot.block',
                    fn ($blocks) => $blocks->where('property_id', $filters['property_id']),
                ),
            )
            ->when(
                isset($filters['status']),
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->when(
                $archive === 'archived',
                fn ($query) => $query->whereNotNull('payment_archived_at'),
                fn ($query) => $query->whereNull('payment_archived_at'),
            )
            ->when(
                isset($filters['search']),
                fn ($query) => $query->where(function ($bookings) use ($filters): void {
                    $search = $filters['search'];

                    $bookings
                        ->whereHas('customer', fn ($customers) => $customers
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('phone', 'like', '%'.$search.'%'))
                        ->orWhereHas('lot', fn ($lots) => $lots
                            ->where('lot_number', 'like', '%'.$search.'%')
                            ->orWhereHas('block', fn ($blocks) => $blocks
                                ->where('name', 'like', '%'.$search.'%')));
                }),
            )
            ->orderByDesc('booking_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Booking $booking) use ($paymentSummaryService): array {
                $summary = $paymentSummaryService->summarize($booking);
                $pendingOrder = $booking->paymentOrders->first(
                    fn ($order): bool => $order->status === 'pending',
                );

                return [
                    'booking' => $booking,
                    'summary' => $summary,
                    'nextStage' => collect($summary['stages'])
                        ->first(fn (array $stage): bool => $stage['remaining'] > 0.009),
                    'pendingOrder' => $pendingOrder,
                    'canArchive' => $booking->payment_archived_at === null
                        && $pendingOrder === null
                        && collect($summary['stages'])->every(
                            fn (array $stage): bool => $stage['remaining'] <= 0.009,
                        ),
                ];
            });

        return view('marketing.payments.index', [
            'bookings' => $bookings,
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'archive' => $archive,
            'midtransConfigured' => is_string(config('services.midtrans.server_key'))
                && config('services.midtrans.server_key') !== '',
            'pendingManualPayments' => $request->user()?->isAdmin()
                ? BookingPayment::query()
                    ->where('payment_channel', 'manual')
                    ->where('approval_status', 'pending')
                    ->with(['booking.customer', 'booking.lot.block.property', 'recordedBy'])
                    ->latest('paid_at')
                    ->get()
                : collect(),
            'pendingUnpaidReasons' => $request->user()?->isAdmin()
                ? Booking::query()
                    ->whereNotNull('unpaid_payment_reason')
                    ->where('unpaid_payment_reason_status', 'pending')
                    ->with(['customer', 'lot.block.property'])
                    ->latest('updated_at')
                    ->get()
                : collect(),
        ]);
    }
}
