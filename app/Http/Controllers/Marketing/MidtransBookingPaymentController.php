<?php

namespace App\Http\Controllers\Marketing;

use App\Exceptions\MidtransGatewayException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\MarketingLeadAccess;
use App\Services\MidtransPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MidtransBookingPaymentController extends Controller
{
    public function store(
        Request $request,
        Booking $booking,
        MidtransPaymentService $midtrans,
        MarketingLeadAccess $leadAccess,
    ): RedirectResponse {
        $leadAccess->authorizeBooking($request->user(), $booking);

        if ($request->input('stage') === 'akad_fee' && $request->filled('amount')) {
            $request->merge([
                'amount' => $this->normalizeCurrencyInput((string) $request->input('amount')),
            ]);
        }
        $validated = $request->validate([
            'stage' => ['required', 'string', 'max:100'],
            'amount' => ['exclude_unless:stage,akad_fee', 'required', 'integer', 'min:1', 'max:9999999999999'],
        ]);

        try {
            $order = $midtrans->createCheckout(
                $booking,
                $validated['stage'],
                isset($validated['amount']) ? (int) $validated['amount'] : null,
                $request->user(),
            );
        } catch (MidtransGatewayException $exception) {
            return to_route('marketing.bookings.show', $booking)
                ->with('error', $exception->getMessage());
        }

        abort_unless(is_string($order->redirect_url), 502, 'Midtrans checkout URL is unavailable.');

        return redirect()->away($order->redirect_url);
    }

    private function normalizeCurrencyInput(string $value): string
    {
        $digits = preg_replace('/[^\d]/', '', $value);

        return $digits === null || $digits === '' ? '' : ltrim($digits, '0');
    }

    public function refresh(
        Request $request,
        Booking $booking,
        MidtransPaymentService $midtrans,
        MarketingLeadAccess $leadAccess,
    ): RedirectResponse {
        $leadAccess->authorizeBooking($request->user(), $booking);

        return $this->refreshPaymentStatus($booking, $midtrans);
    }

    public function returnFromCheckout(
        Request $request,
        Booking $booking,
        MidtransPaymentService $midtrans,
        MarketingLeadAccess $leadAccess,
    ): RedirectResponse {
        $leadAccess->authorizeBooking($request->user(), $booking);

        return $this->refreshPaymentStatus($booking, $midtrans);
    }

    private function refreshPaymentStatus(Booking $booking, MidtransPaymentService $midtrans): RedirectResponse
    {
        try {
            $status = $midtrans->refreshPendingPaymentOrder($booking);
        } catch (MidtransGatewayException $exception) {
            return to_route('marketing.bookings.show', $booking)
                ->with('error', $exception->getMessage());
        }

        if ($status === 'paid') {
            return to_route('marketing.bookings.show', $booking)
                ->with('success', 'Pembayaran Midtrans sudah terkonfirmasi dan tercatat di riwayat pembayaran.');
        }

        $message = $status === 'none'
            ? 'Tidak ada transaksi Midtrans yang sedang menunggu konfirmasi.'
            : 'Status Midtrans sudah diperbarui: '.str_replace('_', ' ', $status).'.';

        return to_route('marketing.bookings.show', $booking)->with('info', $message);
    }
}
