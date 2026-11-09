<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Services\BookingPaymentSummaryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentReportController extends Controller
{
    public function index(Request $request, BookingPaymentSummaryService $summaryService): View
    {
        $this->authorizeFinance($request);

        $bookings = Booking::query()
            ->with(['customer', 'lot.block.property', 'payments'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->trim()->toString();
                $query->whereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('lot', fn ($lot) => $lot->where('lot_number', 'like', "%{$search}%"));
            })
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        $rows = $bookings->getCollection()->map(function (Booking $booking) use ($summaryService): array {
            $summary = $summaryService->summarize($booking);

            return [
                'booking' => $booking,
                'summary' => $summary,
                'remaining' => collect($summary['stages'])->sum('remaining'),
            ];
        });
        $bookings->setCollection($rows);

        return view('keuangan.payment-reports.index', [
            'bookings' => $bookings,
            'approvedToday' => BookingPayment::query()
                ->where('approval_status', 'approved')
                ->whereDate('paid_at', today())
                ->sum('amount'),
        ]);
    }

    public function show(Request $request, Booking $booking, BookingPaymentSummaryService $summaryService): View
    {
        $this->authorizeFinance($request);
        $booking->load(['customer', 'lot.block.property', 'payments.recordedBy', 'payments.reviewedBy']);

        return view('keuangan.payment-reports.show', [
            'booking' => $booking,
            'summary' => $summaryService->summarize($booking),
        ]);
    }

    private function authorizeFinance(Request $request): void
    {
        abort_unless(
            $request->user()?->isAdmin() || $request->user()?->isAssignedToDivision('finance'),
            403,
        );
    }
}
