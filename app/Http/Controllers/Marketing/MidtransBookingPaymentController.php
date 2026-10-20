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
}
