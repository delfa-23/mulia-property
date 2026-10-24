<?php

use App\Models\Block;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingPaymentOrder;
use App\Models\Customer;
use App\Models\Division;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;

it('shows filtered booking payment details and a Midtrans checkout for the next stage', function () {
    $marketingDivision = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $marketingDivision->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Pembayaran']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'B']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '08',
        'house_price' => 120000000,
    ]);
    $customer = Customer::create(['name' => 'Customer Pembayaran']);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-10-10',
        'house_price_snapshot' => 120000000,
        'akad_fee' => 2500000,
        'status' => 'booking',
    ]);
    $otherCustomer = Customer::create(['name' => 'Customer Lain']);
    $searchFilteredLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '09',
        'house_price' => 120000000,
    ]);
    Booking::create([
        'lot_id' => $searchFilteredLot->id,
        'customer_id' => $otherCustomer->id,
        'booking_date' => '2026-10-09',
        'house_price_snapshot' => 120000000,
        'akad_fee' => 2500000,
        'status' => 'booking',
    ]);
    $statusFilteredLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '10',
        'house_price' => 120000000,
    ]);
    Booking::create([
        'lot_id' => $statusFilteredLot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-10-08',
        'house_price_snapshot' => 120000000,
        'akad_fee' => 2500000,
        'status' => 'process',
    ]);
    $otherProperty = Property::create(['name' => 'Perumahan Lain']);
    $otherBlock = Block::create(['property_id' => $otherProperty->id, 'name' => 'C']);
    $propertyFilteredLot = Lot::create([
        'block_id' => $otherBlock->id,
        'lot_number' => '11',
        'house_price' => 120000000,
    ]);
    Booking::create([
        'lot_id' => $propertyFilteredLot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-10-07',
        'house_price_snapshot' => 120000000,
        'akad_fee' => 2500000,
        'status' => 'booking',
    ]);

    $response = $this->actingAs($user)
        ->get(route('marketing.payments.index', [
            'property_id' => $property->id,
            'status' => 'booking',
            'search' => 'Customer Pembayaran',
        ]));

    $response
        ->assertViewIs('marketing.payments.index')
        ->assertSee('Customer Pembayaran')
        ->assertSee('Perumahan Pembayaran')
        ->assertSee('Rp 120.000.000')
        ->assertSee('Virtual Account, QRIS, atau e-wallet')
        ->assertSee('B/08')
        ->assertSee(route('marketing.bookings.payments.midtrans.store', $booking), false)
        ->assertDontSee('B/09')
        ->assertDontSee('B/10')
        ->assertDontSee('B/11')
        ->assertDontSee('Perumahan Lain')
        ->assertDontSee('Customer Lain')
        ->assertDontSee('Tidak ada booking yang cocok dengan filter.');
});

it('requires marketing access to view booking payments', function () {
    $financeDivision = Division::create([
        'name' => 'Finance',
        'slug' => 'finance',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_finance',
        'division_id' => $financeDivision->id,
    ]);

    $this->actingAs($user)
        ->get(route('marketing.payments.index'))
        ->assertForbidden();
});

it('shows detailed payment and Midtrans transaction history in the payment list', function () {
    $marketingDivision = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $marketingDivision->id,
    ]);
    $booking = makePaymentControllerTestBooking();
    $paymentOrder = BookingPaymentOrder::query()->create([
        'booking_id' => $booking->id,
        'stage' => 'dp',
        'amount' => 2500000,
        'order_id' => 'BK'.$booking->id.'-HISTORY',
        'status' => 'paid',
        'transaction_id' => 'midtrans-transaction-history',
        'payment_type' => 'bank_transfer',
    ]);
    BookingPayment::query()->create([
        'booking_id' => $booking->id,
        'booking_payment_order_id' => $paymentOrder->id,
        'stage' => 'dp',
        'amount' => 2500000,
        'paid_at' => '2026-10-21 10:30:00',
        'payment_method' => 'Midtrans - bank_transfer',
        'reference' => $paymentOrder->order_id,
        'notes' => 'Pembayaran Midtrans terkonfirmasi.',
        'recorded_by' => $user->id,
    ]);
    BookingPaymentOrder::query()->create([
        'booking_id' => $booking->id,
        'stage' => 'dp',
        'amount' => 1000000,
        'order_id' => 'BK'.$booking->id.'-PENDINGHISTORY',
        'status' => 'pending',
        'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/pending',
    ]);

    $this->actingAs($user)
        ->get(route('marketing.payments.index'))
        ->assertViewIs('marketing.payments.index')
        ->assertSee('Riwayat pembayaran')
        ->assertSee('Riwayat transaksi Midtrans')
        ->assertSee('21/10/2026 10:30')
        ->assertSee('Midtrans - bank_transfer')
        ->assertSee($paymentOrder->order_id)
        ->assertSee('midtrans-transaction-history')
        ->assertSee('Berhasil')
        ->assertSee('Cek status pembayaran');
});

it('allows fully paid bookings to be manually archived and restored', function () {
    $marketingDivision = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $marketingDivision->id,
    ]);
    $booking = makePaymentControllerTestBooking();
    BookingPayment::query()->create([
        'booking_id' => $booking->id,
        'stage' => 'dp',
        'amount' => 100000000,
        'paid_at' => '2026-10-21 10:30:00',
        'payment_method' => 'Midtrans - bank_transfer',
    ]);
    BookingPayment::query()->create([
        'booking_id' => $booking->id,
        'stage' => 'akad_fee',
        'amount' => 2000000,
        'paid_at' => '2026-10-21 11:30:00',
        'payment_method' => 'Transfer bank',
    ]);

    $this->actingAs($user)
        ->post(route('marketing.payments.archive.store', $booking))
        ->assertRedirect(route('marketing.payments.index', ['archive' => 'active']))
        ->assertSessionHas('success', 'Pembayaran lunas berhasil dimasukkan ke arsip.');

    expect($booking->fresh()->payment_archived_at)->not->toBeNull();
    $this->assertDatabaseHas('activity_logs', [
        'action' => 'booking.payment_archived',
        'subject_id' => $booking->id,
    ]);
    $this->actingAs($user)
        ->get(route('marketing.payments.index'))
        ->assertDontSee('Customer Riwayat');
    $this->actingAs($user)
        ->get(route('marketing.payments.index', ['archive' => 'archived']))
        ->assertSee('Customer Riwayat')
        ->assertSee('Kembalikan ke pembayaran aktif');

    $this->actingAs($user)
        ->delete(route('marketing.payments.archive.destroy', $booking))
        ->assertRedirect(route('marketing.payments.index', ['archive' => 'archived']))
        ->assertSessionHas('success', 'Pembayaran dikembalikan ke daftar pembayaran aktif.');

    expect($booking->fresh()->payment_archived_at)->toBeNull();
    $this->assertDatabaseHas('activity_logs', [
        'action' => 'booking.payment_archive_restored',
        'subject_id' => $booking->id,
    ]);
});

it('does not allow a booking with an unpaid balance to be archived', function () {
    $marketingDivision = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $marketingDivision->id,
    ]);
    $booking = makePaymentControllerTestBooking();

    $this->actingAs($user)
        ->post(route('marketing.payments.archive.store', $booking))
        ->assertUnprocessable();

    expect($booking->fresh()->payment_archived_at)->toBeNull();
});

it('redirects guests to sign in before showing booking payments', function () {
    $this->get(route('marketing.payments.index'))
        ->assertRedirect(route('login'));
});

function makePaymentControllerTestBooking(): Booking
{
    $property = Property::create(['name' => 'Perumahan Riwayat']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 100000000,
    ]);
    $customer = Customer::create(['name' => 'Customer Riwayat']);

    return Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-10-21',
        'house_price_snapshot' => 100000000,
        'akad_fee' => 2000000,
        'status' => 'booking',
    ]);
}
