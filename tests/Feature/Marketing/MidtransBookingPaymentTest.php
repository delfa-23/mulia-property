<?php

use App\Models\Block;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingPaymentOrder;
use App\Models\Customer;
use App\Models\Division;
use App\Models\FinanceCashFlow;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('creates a Midtrans Snap checkout for the next booking payment stage', function () {
    Http::preventStrayRequests();
    Http::fake([
        'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'snap-token',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token',
        ], 201),
    ]);
    config([
        'services.midtrans.server_key' => 'SB-Mid-server-test',
        'services.midtrans.is_production' => false,
    ]);
    $user = makeMidtransPaymentTestUser();
    $booking = makeMidtransPaymentTestBooking();

    $response = $this->actingAs($user)
        ->post(route('marketing.bookings.payments.midtrans.store', $booking), [
            'stage' => 'dp',
            'amount' => '2500000',
        ]);

    $response->assertRedirect('https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token');
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
        && $request['transaction_details']['gross_amount'] === 2500000
        && $request['customer_details']['first_name'] === 'Pembeli Midtrans'
        && $request['callbacks']['finish'] === route('marketing.bookings.payments.midtrans.return', $booking));
    $this->assertDatabaseHas('booking_payment_orders', [
        'booking_id' => $booking->id,
        'stage' => 'dp',
        'amount' => '2500000.00',
        'status' => 'pending',
        'snap_token' => 'snap-token',
    ]);
    $this->assertDatabaseCount('booking_payments', 0);
    $this->assertDatabaseCount('finance_cash_flows', 0);
});

it('records a verified Midtrans settlement once in booking payments and finance', function () {
    config(['services.midtrans.server_key' => 'SB-Mid-server-test']);
    $booking = makeMidtransPaymentTestBooking();
    $order = BookingPaymentOrder::query()->create([
        'booking_id' => $booking->id,
        'stage' => 'dp',
        'amount' => 2500000,
        'order_id' => 'BK'.$booking->id.'-TESTORDER',
        'status' => 'pending',
        'created_by' => makeMidtransPaymentTestUser()->id,
    ]);
    $notification = makeMidtransSettlementNotification($order->order_id, '2500000.00');

    $this->postJson(route('webhooks.midtrans.notifications'), $notification)->assertNoContent();
    $this->postJson(route('webhooks.midtrans.notifications'), $notification)->assertNoContent();

    $this->assertDatabaseHas('booking_payment_orders', [
        'id' => $order->id,
        'status' => 'paid',
        'transaction_id' => 'midtrans-transaction-1',
    ]);
    $this->assertDatabaseCount('booking_payments', 1);
    $this->assertDatabaseHas('booking_payments', [
        'booking_id' => $booking->id,
        'stage' => 'dp',
        'amount' => '2500000.00',
        'payment_method' => 'Midtrans - bank_transfer',
        'reference' => $order->order_id,
    ]);
    $this->assertDatabaseCount('finance_cash_flows', 1);
    $this->assertDatabaseHas('finance_cash_flows', [
        'property_id' => $booking->lot->block->property_id,
        'direction' => 'income',
        'status' => 'confirmed',
        'amount' => '2500000.00',
        'booking_payment_id' => BookingPayment::query()->sole()->id,
    ]);
});

it('reconciles a settled Midtrans order when staff checks its status', function () {
    config([
        'services.midtrans.server_key' => 'SB-Mid-server-test',
        'services.midtrans.is_production' => false,
    ]);
    $user = makeMidtransPaymentTestUser();
    $booking = makeMidtransPaymentTestBooking();
    $order = BookingPaymentOrder::query()->create([
        'booking_id' => $booking->id,
        'stage' => 'dp',
        'amount' => 2500000,
        'order_id' => 'BK'.$booking->id.'-STATUSCHECK',
        'status' => 'pending',
        'created_by' => $user->id,
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'api.sandbox.midtrans.com/v2/*/status' => Http::response([
            'order_id' => $order->order_id,
            'status_code' => '200',
            'gross_amount' => '2500000.00',
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
            'transaction_id' => 'midtrans-status-transaction',
        ]),
    ]);

    $this->actingAs($user)
        ->post(route('marketing.bookings.payments.midtrans.refresh', $booking))
        ->assertRedirect(route('marketing.bookings.show', $booking))
        ->assertSessionHas('success', 'Pembayaran Midtrans sudah terkonfirmasi dan tercatat di riwayat pembayaran.');

    expect($order->fresh()->status)->toBe('paid')
        ->and($order->fresh()->transaction_id)->toBe('midtrans-status-transaction');
    $this->assertDatabaseHas('booking_payments', [
        'booking_id' => $booking->id,
        'booking_payment_order_id' => $order->id,
        'amount' => '2500000.00',
        'payment_method' => 'Midtrans - bank_transfer',
    ]);
    $this->assertDatabaseHas('finance_cash_flows', [
        'direction' => 'income',
        'status' => 'confirmed',
        'amount' => '2500000.00',
    ]);
});

it('reconciles a settled Midtrans order when staff returns from checkout', function () {
    config([
        'services.midtrans.server_key' => 'SB-Mid-server-test',
        'services.midtrans.is_production' => false,
    ]);
    $user = makeMidtransPaymentTestUser();
    $booking = makeMidtransPaymentTestBooking();
    $order = BookingPaymentOrder::query()->create([
        'booking_id' => $booking->id,
        'stage' => 'dp',
        'amount' => 2500000,
        'order_id' => 'BK'.$booking->id.'-CHECKOUTRETURN',
        'status' => 'pending',
        'created_by' => $user->id,
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'api.sandbox.midtrans.com/v2/*/status' => Http::response([
            'order_id' => $order->order_id,
            'status_code' => '200',
            'gross_amount' => '2500000.00',
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
            'transaction_id' => 'midtrans-return-transaction',
        ]),
    ]);

    $this->actingAs($user)
        ->get(route('marketing.bookings.payments.midtrans.return', $booking))
        ->assertRedirect(route('marketing.bookings.show', $booking))
        ->assertSessionHas('success', 'Pembayaran Midtrans sudah terkonfirmasi dan tercatat di riwayat pembayaran.');

    expect($order->fresh()->status)->toBe('paid')
        ->and($order->fresh()->transaction_id)->toBe('midtrans-return-transaction');
    $this->assertDatabaseHas('booking_payments', [
        'booking_id' => $booking->id,
        'booking_payment_order_id' => $order->id,
        'amount' => '2500000.00',
    ]);
    $this->assertDatabaseHas('finance_cash_flows', [
        'direction' => 'income',
        'status' => 'confirmed',
        'amount' => '2500000.00',
    ]);
});

it('rejects Midtrans notifications with an invalid signature without changing payment state', function () {
    config(['services.midtrans.server_key' => 'SB-Mid-server-test']);
    $booking = makeMidtransPaymentTestBooking();
    $order = BookingPaymentOrder::query()->create([
        'booking_id' => $booking->id,
        'stage' => 'dp',
        'amount' => 2500000,
        'order_id' => 'BK'.$booking->id.'-BADSIGNATURE',
        'status' => 'pending',
    ]);
    $notification = makeMidtransSettlementNotification($order->order_id, '2500000.00');
    $notification['signature_key'] = 'invalid';

    $this->postJson(route('webhooks.midtrans.notifications'), $notification)->assertForbidden();

    expect($order->fresh()->status)->toBe('pending');
    $this->assertDatabaseCount('booking_payments', 0);
    $this->assertDatabaseCount('finance_cash_flows', 0);
});

it('syncs a manually recorded booking payment to confirmed finance income', function () {
    $user = makeMidtransPaymentTestUser();
    $booking = makeMidtransPaymentTestBooking();

    $this->actingAs($user)
        ->post(route('marketing.bookings.payments.store', $booking), [
            'stage' => 'dp',
            'amount' => '2500000',
            'paid_at' => now()->format('Y-m-d H:i:s'),
            'payment_method' => 'transfer',
        ])
        ->assertRedirect(route('marketing.bookings.show', $booking));

    $payment = BookingPayment::query()->sole();
    $cashFlow = FinanceCashFlow::query()->sole();
    expect($cashFlow->booking_payment_id)->toBe($payment->id)
        ->and($cashFlow->direction)->toBe('income')
        ->and($cashFlow->status)->toBe('confirmed');
});

it('does not allow a manual payment while a Midtrans order for the stage is pending', function () {
    $user = makeMidtransPaymentTestUser();
    $booking = makeMidtransPaymentTestBooking();
    BookingPaymentOrder::query()->create([
        'booking_id' => $booking->id,
        'stage' => 'dp',
        'amount' => 2500000,
        'order_id' => 'BK'.$booking->id.'-PENDINGMANUAL',
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->from(route('marketing.bookings.show', $booking))
        ->post(route('marketing.bookings.payments.store', $booking), [
            'stage' => 'dp',
            'amount' => '2500000',
            'paid_at' => now()->format('Y-m-d H:i:s'),
            'payment_method' => 'transfer',
        ])
        ->assertRedirect(route('marketing.bookings.show', $booking))
        ->assertSessionHasErrors('stage');

    $this->assertDatabaseCount('booking_payments', 0);
    $this->assertDatabaseCount('finance_cash_flows', 0);
});

it('does not expire a booking while a Midtrans payment is pending', function () {
    $booking = makeMidtransPaymentTestBooking();
    $booking->update(['blocking_until' => now()->subDay()]);
    BookingPaymentOrder::query()->create([
        'booking_id' => $booking->id,
        'stage' => 'dp',
        'amount' => 2500000,
        'order_id' => 'BK'.$booking->id.'-PENDINGEXPIRY',
        'status' => 'pending',
    ]);

    Artisan::call('bookings:expire');

    expect($booking->fresh()->status)->toBe('booking');
});

function makeMidtransPaymentTestUser(): User
{
    $division = Division::firstOrCreate(
        ['slug' => 'marketing'],
        ['name' => 'Marketing', 'is_active' => true],
    );

    return User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
}

function makeMidtransPaymentTestBooking(): Booking
{
    $property = Property::create(['name' => 'Perumahan Midtrans']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 100000000,
        'status' => 'booked',
    ]);
    $customer = Customer::create([
        'name' => 'Pembeli Midtrans',
        'phone' => '081234567890',
        'email' => 'pembeli@example.test',
    ]);

    return Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'booking_date' => today(),
        'house_price_snapshot' => 100000000,
        'dp_percentage' => 100,
        'akad_fee' => 2000000,
        'status' => 'booking',
    ]);
}

/** @return array<string, string> */
function makeMidtransSettlementNotification(string $orderId, string $grossAmount): array
{
    $statusCode = '200';

    return [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'transaction_status' => 'settlement',
        'fraud_status' => 'accept',
        'payment_type' => 'bank_transfer',
        'transaction_id' => 'midtrans-transaction-1',
        'signature_key' => hash('sha512', $orderId.$statusCode.$grossAmount.'SB-Mid-server-test'),
    ];
}
