<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Property;
use App\Services\BookingPaymentSummaryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request, BookingPaymentSummaryService $paymentSummaryService): View
    {
        $filters = $request->validate([
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'status' => ['nullable', Rule::in(['booking', 'process', 'akad', 'finish', 'cancelled'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $bookings = Booking::query()
            ->with([
                'lot.block.property',
                'customer',
                'payments.paymentOrder',
                'payments.recordedBy',
                'paymentOrders',
            ])
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
                ];
            });

        return view('marketing.payments.index', [
            'bookings' => $bookings,
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'midtransConfigured' => is_string(config('services.midtrans.server_key'))
                && config('services.midtrans.server_key') !== '',
        ]);
    }
}
