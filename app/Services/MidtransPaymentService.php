<?php

namespace App\Services;

use App\Exceptions\MidtransGatewayException;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingPaymentOrder;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MidtransPaymentService
{
    public function __construct(
        private readonly BookingPaymentFinanceSync $financeSync,
    ) {}

    public function createCheckout(Booking $booking, string $stage, int $amount, User $user): BookingPaymentOrder
    {
        $serverKey = config('services.midtrans.server_key');

        if (! is_string($serverKey) || $serverKey === '') {
            throw new MidtransGatewayException('Midtrans belum dikonfigurasi. Minta tim teknis mengatur MIDTRANS_SERVER_KEY.');
        }

        $order = DB::transaction(function () use ($booking, $stage, $amount, $user): BookingPaymentOrder {
            $lockedBooking = Booking::query()
                ->with(['customer', 'lot.block'])
                ->lockForUpdate()
                ->findOrFail($booking->id);

            if (in_array($lockedBooking->status, ['cancelled', 'finish'], true)) {
                throw ValidationException::withMessages([
                    'stage' => 'Pembayaran tidak dapat dibuat untuk booking yang selesai atau dibatalkan.',
                ]);
            }

            if ($lockedBooking->status === 'booking' && $lockedBooking->blocking_until?->isPast()) {
                throw ValidationException::withMessages([
                    'stage' => 'Batas booking sudah terlewati. Perbarui status booking sebelum membuat pembayaran.',
                ]);
            }

            $summary = $this->paymentStageSummary($lockedBooking);
            $nextStage = collect($summary)->first(fn (array $paymentStage): bool => $paymentStage['remaining'] > 0.009);

            if ($nextStage === null || $stage !== $nextStage['key']) {
                throw ValidationException::withMessages([
                    'stage' => 'Pembayaran harus mengikuti urutan DP, lalu biaya akad.',
                ]);
            }

            if ($amount > $nextStage['remaining']) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal melebihi sisa tagihan tahap ini.',
                ]);
            }

            $existingOrder = BookingPaymentOrder::query()
                ->where('booking_id', $lockedBooking->id)
                ->where('stage', $stage)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if ($existingOrder) {
                if ((float) $existingOrder->amount === (float) $amount && $existingOrder->redirect_url) {
                    return $existingOrder;
                }

                throw ValidationException::withMessages([
                    'amount' => 'Masih ada transaksi Midtrans yang menunggu pembayaran untuk tahap ini.',
                ]);
            }

            return BookingPaymentOrder::query()->create([
                'booking_id' => $lockedBooking->id,
                'stage' => $stage,
                'amount' => $amount,
                'order_id' => 'BK'.$lockedBooking->id.'-'.Str::ulid(),
                'status' => 'pending',
                'created_by' => $user->id,
            ]);
        });

        if ($order->redirect_url) {
            return $order;
        }

        $isProduction = (bool) config('services.midtrans.is_production');
        $baseUrl = $isProduction
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';
        $stageLabel = $order->stage === 'dp' ? 'Pembayaran DP' : 'Biaya Akad';
        $customer = $order->booking->customer;
        $payload = [
            'transaction_details' => [
                'order_id' => $order->order_id,
                'gross_amount' => (int) $order->amount,
            ],
            'item_details' => [[
                'id' => 'booking-'.$order->booking_id.'-'.$order->stage,
                'price' => (int) $order->amount,
                'quantity' => 1,
                'name' => $stageLabel.' booking #'.$order->booking_id,
            ]],
            'customer_details' => array_filter([
                'first_name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ], fn (?string $value): bool => $value !== null && $value !== ''),
            'callbacks' => [
                'finish' => route('marketing.bookings.payments.midtrans.return', $order->booking_id),
            ],
            'credit_card' => ['secure' => true],
        ];

        try {
            $response = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->withBasicAuth($serverKey, '')
                ->post($baseUrl.'/snap/v1/transactions', $payload);
        } catch (ConnectionException $exception) {
            Log::error('Midtrans checkout request outcome is unknown.', [
                'order_id' => $order->order_id,
                'exception' => $exception->getMessage(),
            ]);

            throw new MidtransGatewayException(
                'Status transaksi belum dapat dipastikan. Jangan membuat pembayaran baru; minta tim Finance mengecek order '.$order->order_id.'.',
                previous: $exception,
            );
        }

        if ($response->clientError()) {
            $order->update(['status' => 'failed']);
            Log::warning('Midtrans rejected a booking payment checkout request.', [
                'order_id' => $order->order_id,
                'status' => $response->status(),
            ]);

            throw new MidtransGatewayException('Midtrans menolak pembuatan pembayaran. Periksa data transaksi dan coba lagi.');
        }

        if ($response->serverError()) {
            Log::error('Midtrans checkout returned a server error; transaction outcome is unknown.', [
                'order_id' => $order->order_id,
                'status' => $response->status(),
            ]);

            throw new MidtransGatewayException(
                'Status transaksi belum dapat dipastikan. Jangan membuat pembayaran baru; minta tim Finance mengecek order '.$order->order_id.'.',
            );
        }

        $token = $response->json('token');
        $redirectUrl = $response->json('redirect_url');
        $expectedHost = $isProduction ? 'app.midtrans.com' : 'app.sandbox.midtrans.com';
        $parsedUrl = is_string($redirectUrl) ? parse_url($redirectUrl) : false;

        if (
            ! $response->successful() ||
            ! is_string($token) ||
            $token === '' ||
            ! is_array($parsedUrl) ||
            ($parsedUrl['scheme'] ?? null) !== 'https' ||
            ($parsedUrl['host'] ?? null) !== $expectedHost
        ) {
            Log::error('Midtrans returned an invalid booking checkout response.', [
                'order_id' => $order->order_id,
                'status' => $response->status(),
            ]);

            throw new MidtransGatewayException(
                'Status transaksi belum dapat dipastikan. Jangan membuat pembayaran baru; minta tim Finance mengecek order '.$order->order_id.'.',
            );
        }

        $order->update([
            'snap_token' => $token,
            'redirect_url' => $redirectUrl,
        ]);

        return $order->refresh();
    }

    /** @param array<string, string|null> $notification */
    public function handleNotification(array $notification): void
    {
        $serverKey = config('services.midtrans.server_key');

        if (! is_string($serverKey) || $serverKey === '') {
            abort(503, 'Midtrans notification cannot be verified because its server key is not configured.');
        }

        $expectedSignature = hash(
            'sha512',
            $notification['order_id'].$notification['status_code'].$notification['gross_amount'].$serverKey,
        );

        abort_unless(hash_equals($expectedSignature, $notification['signature_key']), 403);

        $paymentOrder = BookingPaymentOrder::query()
            ->where('order_id', $notification['order_id'])
            ->firstOrFail();
        $receivedCents = $this->amountInCents($notification['gross_amount']);
        $expectedCents = $this->amountInCents((string) $paymentOrder->amount);

        if ($receivedCents === null || $receivedCents !== $expectedCents) {
            Log::warning('Midtrans notification amount did not match the booking payment order.', [
                'order_id' => $paymentOrder->order_id,
            ]);

            abort(422, 'Midtrans notification amount does not match the payment order.');
        }

        $this->applyTransactionStatus($notification, $paymentOrder);
    }

    public function refreshPendingPaymentOrder(Booking $booking): string
    {
        $paymentOrder = BookingPaymentOrder::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (! $paymentOrder) {
            return 'none';
        }

        $serverKey = config('services.midtrans.server_key');

        if (! is_string($serverKey) || $serverKey === '') {
            throw new MidtransGatewayException('Midtrans belum dikonfigurasi. Minta tim teknis mengatur MIDTRANS_SERVER_KEY.');
        }

        $isProduction = (bool) config('services.midtrans.is_production');
        $baseUrl = $isProduction
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';

        try {
            $response = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->withBasicAuth($serverKey, '')
                ->get($baseUrl.'/v2/'.rawurlencode($paymentOrder->order_id).'/status');
        } catch (ConnectionException $exception) {
            Log::warning('Midtrans payment status could not be checked.', [
                'order_id' => $paymentOrder->order_id,
                'exception' => $exception->getMessage(),
            ]);

            throw new MidtransGatewayException(
                'Status pembayaran belum dapat diperiksa. Pastikan koneksi ke Midtrans tersedia, lalu coba lagi.',
                previous: $exception,
            );
        }

        $notification = $response->json();

        if (
            ! $response->successful()
            || ! is_array($notification)
            || ($notification['order_id'] ?? null) !== $paymentOrder->order_id
            || ($notification['status_code'] ?? null) !== '200'
            || ! is_string($notification['gross_amount'] ?? null)
            || ! is_string($notification['transaction_status'] ?? null)
        ) {
            Log::warning('Midtrans returned an invalid payment status response.', [
                'order_id' => $paymentOrder->order_id,
                'status' => $response->status(),
            ]);

            throw new MidtransGatewayException(
                'Midtrans belum mengembalikan status transaksi yang valid. Coba perbarui status beberapa saat lagi.',
            );
        }

        $receivedCents = $this->amountInCents($notification['gross_amount']);
        $expectedCents = $this->amountInCents((string) $paymentOrder->amount);

        if ($receivedCents === null || $receivedCents !== $expectedCents) {
            Log::warning('Midtrans status amount did not match the booking payment order.', [
                'order_id' => $paymentOrder->order_id,
            ]);

            throw new MidtransGatewayException(
                'Nominal transaksi dari Midtrans tidak sesuai dengan order booking. Status tidak diubah; hubungi tim Finance.',
            );
        }

        $this->applyTransactionStatus([
            'order_id' => $paymentOrder->order_id,
            'status_code' => $notification['status_code'],
            'gross_amount' => $notification['gross_amount'],
            'transaction_status' => $notification['transaction_status'],
            'fraud_status' => is_string($notification['fraud_status'] ?? null) ? $notification['fraud_status'] : null,
            'transaction_id' => is_string($notification['transaction_id'] ?? null) ? $notification['transaction_id'] : null,
            'payment_type' => is_string($notification['payment_type'] ?? null) ? $notification['payment_type'] : null,
        ], $paymentOrder);

        return $paymentOrder->fresh()->status;
    }

    /** @param array<string, string|null> $notification */
    private function applyTransactionStatus(array $notification, BookingPaymentOrder $paymentOrder): void
    {
        DB::transaction(function () use ($notification, $paymentOrder): void {
            $booking = Booking::query()->lockForUpdate()->findOrFail($paymentOrder->booking_id);
            $lockedOrder = BookingPaymentOrder::query()->lockForUpdate()->findOrFail($paymentOrder->id);

            if ($lockedOrder->status === 'paid') {
                return;
            }

            $transactionStatus = strtolower($notification['transaction_status']);
            $isSuccessful = $notification['status_code'] === '200'
                && ($transactionStatus === 'settlement'
                    || ($transactionStatus === 'capture'
                        && strtolower((string) ($notification['fraud_status'] ?? '')) === 'accept'));

            if ($isSuccessful) {
                $payment = BookingPayment::query()->firstOrCreate(
                    ['booking_payment_order_id' => $lockedOrder->id],
                    [
                        'booking_id' => $booking->id,
                        'stage' => $lockedOrder->stage,
                        'amount' => $lockedOrder->amount,
                        'paid_at' => now(),
                        'payment_method' => Str::limit('Midtrans - '.($notification['payment_type'] ?? 'online'), 100, ''),
                        'reference' => $lockedOrder->order_id,
                        'notes' => 'Pembayaran Midtrans terkonfirmasi.',
                        'recorded_by' => $lockedOrder->created_by,
                    ],
                );
                $this->financeSync->sync($payment);
                $lockedOrder->update([
                    'status' => 'paid',
                    'transaction_id' => $notification['transaction_id'] ?? null,
                    'payment_type' => $notification['payment_type'] ?? null,
                ]);

                return;
            }

            if ($transactionStatus === 'pending') {
                if (! in_array($lockedOrder->status, ['failed', 'expired', 'cancelled'], true)) {
                    $lockedOrder->update(['status' => 'pending']);
                }

                return;
            }

            $localStatus = match ($transactionStatus) {
                'expire' => 'expired',
                'cancel' => 'cancelled',
                'deny', 'failure' => 'failed',
                default => $lockedOrder->status,
            };

            $lockedOrder->update([
                'status' => $localStatus,
                'transaction_id' => $notification['transaction_id'] ?? $lockedOrder->transaction_id,
                'payment_type' => $notification['payment_type'] ?? $lockedOrder->payment_type,
            ]);
        });
    }

    /** @return list<array{key: string, remaining: float}> */
    private function paymentStageSummary(Booking $booking): array
    {
        $payments = $booking->payments()
            ->reorder()
            ->selectRaw('stage, SUM(amount) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');
        $paidDp = (float) $payments->get('dp', 0) + (float) $payments->get('bank_settlement', 0);
        $dpTotal = (float) ($booking->house_price_snapshot ?? $booking->lot->house_price);
        $paidAkad = (float) $payments->get('akad_fee', 0);

        return [
            ['key' => 'dp', 'remaining' => max(0, round($dpTotal - $paidDp, 2))],
            ['key' => 'akad_fee', 'remaining' => max(0, round((float) $booking->akad_fee - $paidAkad, 2))],
        ];
    }

    private function amountInCents(string $amount): ?int
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/D', $amount)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
