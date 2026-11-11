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
        private readonly BookingPaymentSummaryService $paymentSummaryService,
    ) {}

    public function createCheckout(Booking $booking, string $stageKey, ?int $amount, User $user): BookingPaymentOrder
    {
        $serverKey = config('services.midtrans.server_key');

        if (! is_string($serverKey) || $serverKey === '') {
            throw new MidtransGatewayException('Midtrans belum dikonfigurasi. Minta tim teknis mengatur MIDTRANS_SERVER_KEY.');
        }

        $order = DB::transaction(function () use ($booking, $stageKey, $amount, $user): BookingPaymentOrder {
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

            $summary = $this->paymentSummaryService->summarize($lockedBooking);
            $paymentStage = collect($summary['stages'])
                ->first(fn (array $paymentStage): bool => $paymentStage['key'] === $stageKey);

            if ($paymentStage === null || $paymentStage['remaining'] <= 0.009) {
                throw ValidationException::withMessages([
                    'stage' => 'Tahap pembayaran tidak ditemukan atau sudah lunas.',
                ]);
            }
            if (! $paymentStage['configured']) {
                throw ValidationException::withMessages([
                    'stage' => 'Admin belum mengatur jadwal pembayaran untuk perumahan ini.',
                ]);
            }

            $amountToPay = $paymentStage['stage'] === 'akad_fee'
                ? $amount
                : (int) floor($paymentStage['remaining']);

            if ($amountToPay === null || $amountToPay < 1) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal pembayaran tahap ini tidak valid.',
                ]);
            }

            if ($amountToPay > $paymentStage['remaining'] + 0.009) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal melebihi sisa tagihan tahap ini.',
                ]);
            }
            if (
                $paymentStage['stage'] !== 'akad_fee' &&
                $amountToPay > $summary['house_price'] - $summary['booking_paid'] + 0.009
            ) {
                throw ValidationException::withMessages([
                    'stage' => 'Nominal jadwal melebihi sisa harga rumah. Minta admin menyesuaikan jadwal pembayaran perumahan.',
                ]);
            }

            $existingOrder = BookingPaymentOrder::query()
                ->where('booking_id', $lockedBooking->id)
                ->where('stage', $paymentStage['stage'])
                ->when(
                    $paymentStage['schedule_id'] === null,
                    fn ($query) => $query->whereNull('booking_payment_schedule_id'),
                    fn ($query) => $query->where('booking_payment_schedule_id', $paymentStage['schedule_id']),
                )
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if ($existingOrder) {
                if ((float) $existingOrder->amount === (float) $amountToPay && $existingOrder->redirect_url) {
                    return $existingOrder;
                }

                if ($paymentStage['stage'] === 'akad_fee') {
                    $existingOrder->update([
                        'status' => 'cancelled',
                        'snap_token' => null,
                        'redirect_url' => null,
                    ]);
                } else {
                    throw ValidationException::withMessages([
                        'amount' => 'Masih ada transaksi Midtrans yang menunggu pembayaran untuk tahap ini.',
                    ]);
                }

                $existingOrder = null;
            }

            return BookingPaymentOrder::query()->create([
                'booking_id' => $lockedBooking->id,
                'booking_payment_schedule_id' => $paymentStage['schedule_id'],
                'stage' => $paymentStage['stage'],
                'amount' => $amountToPay,
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
        $order->loadMissing(['booking.customer', 'paymentSchedule']);
        $stageLabel = $order->paymentSchedule?->title ?? match ($order->stage) {
            'dp', 'booking' => 'Pembayaran Booking',
            'booking_balance' => 'Sisa Pembayaran Booking',
            'akad_fee' => 'Biaya Akad',
            default => 'Pembayaran Booking',
        };
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
                        'booking_payment_schedule_id' => $lockedOrder->booking_payment_schedule_id,
                        'stage' => $lockedOrder->stage,
                        'amount' => $lockedOrder->amount,
                        'paid_at' => now(),
                        'payment_method' => Str::limit('Midtrans - '.($notification['payment_type'] ?? 'online'), 100, ''),
                        'reference' => $lockedOrder->order_id,
                        'notes' => 'Pembayaran Midtrans terkonfirmasi.',
                        'recorded_by' => $lockedOrder->created_by,
                        'payment_channel' => 'midtrans',
                        'approval_status' => 'approved',
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

    private function amountInCents(string $amount): ?int
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/D', $amount)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
