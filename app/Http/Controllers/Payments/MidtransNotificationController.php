<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Services\MidtransPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MidtransNotificationController extends Controller
{
    public function __invoke(Request $request, MidtransPaymentService $midtrans): Response
    {
        $notification = $request->validate([
            'order_id' => ['required', 'string', 'max:50'],
            'status_code' => ['required', 'string', 'max:3'],
            'gross_amount' => ['required', 'string', 'max:20'],
            'signature_key' => ['required', 'string', 'size:128'],
            'transaction_status' => ['required', 'string', 'max:50'],
            'fraud_status' => ['nullable', 'string', 'max:50'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'payment_type' => ['nullable', 'string', 'max:100'],
        ]);

        $midtrans->handleNotification($notification);

        return response()->noContent();
    }
}
