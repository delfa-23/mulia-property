<?php

namespace App\Http\Controllers\Marketing;

use App\Exceptions\MidtransGatewayException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\MidtransPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MidtransBookingPaymentController extends Controller
{
    public function store(Request $request, Booking $booking, MidtransPaymentService $midtrans): RedirectResponse
    {
        $validated = $request->validate([
            'stage' => ['required', Rule::in(['dp', 'akad_fee'])],
            'amount' => ['required', 'integer', 'min:1', 'max:9999999999999'],
        ]);

        try {
            $order = $midtrans->createCheckout(
                $booking,
                $validated['stage'],
                (int) $validated['amount'],
                $request->user(),
            );
        } catch (MidtransGatewayException $exception) {
            return to_route('marketing.bookings.show', $booking)
                ->with('error', $exception->getMessage());
        }

        abort_unless(is_string($order->redirect_url), 502, 'Midtrans checkout URL is unavailable.');

        return redirect()->away($order->redirect_url);
    }

    public function refresh(Booking $booking, MidtransPaymentService $midtrans): RedirectResponse
    {
        return $this->refreshPaymentStatus($booking, $midtrans);
    }

    public function returnFromCheckout(Booking $booking, MidtransPaymentService $midtrans): RedirectResponse
    {
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
