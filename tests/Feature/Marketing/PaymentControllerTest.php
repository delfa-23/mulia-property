<?php

use App\Models\Block;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingPaymentOrder;
use App\Models\BookingPaymentSchedule;
use App\Models\Customer;
use App\Models\Division;
use App\Models\Lot;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('saves an unpaid payment reason for a booking with payments', function () {
    $marketingDivision = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $marketingDivision->id,
    ]);
    $booking = makePaymentControllerTestBooking($user);

    $this->actingAs($user)
        ->put(route('marketing.payments.unpaid-reason.update', $booking), [
            'unpaid_payment_reason' => 'Customer menunggu pencairan dana untuk melunasi sisa pembayaran.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'unpaid_payment_reason' => 'Customer menunggu pencairan dana untuk melunasi sisa pembayaran.',
    ]);
});

it('prompts marketing to edit an expired booking after an admin approves its unpaid reason', function () {
    $marketingDivision = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $marketingDivision->id,
    ]);
    $booking = makePaymentControllerTestBooking($user);
    $booking->update([
        'booking_date' => today()->subDays(3),
        'blocking_until' => now()->subDay(),
        'unpaid_payment_reason' => 'Customer meminta tambahan waktu untuk menyelesaikan pembayaran.',
        'unpaid_payment_reason_status' => 'approved',
    ]);
    $schedule = BookingPaymentSchedule::query()->create([
        'property_id' => $booking->lot->block->property_id,
        'title' => 'Pembayaran 1',
        'amount' => 1000000,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    BookingPayment::query()->create([
        'booking_id' => $booking->id,
        'booking_payment_schedule_id' => $schedule->id,
        'stage' => 'booking',
        'amount' => 500000,
        'paid_at' => now()->subDays(2),
        'payment_channel' => 'manual',
        'approval_status' => 'approved',
    ]);

    $this->actingAs($user)
        ->get(route('marketing.payments.index'))
        ->assertSee('Alasan telah disetujui admin.')
        ->assertSee('Silakan perbarui tanggal booking dan batas booking agar masa booking kembali sesuai.')
        ->assertSee('Edit Booking')
        ->assertSee(route('marketing.bookings.edit', $booking), false)
        ->assertDontSee('Kirim ulang alasan');
});

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
    BookingPaymentSchedule::query()->create([
        'property_id' => $property->id,
        'title' => 'Pembayaran 1',
        'amount' => 100000000,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $customer = Customer::create(['name' => 'Customer Pembayaran']);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'created_by' => $user->id,
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
        'created_by' => $user->id,
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
        'created_by' => $user->id,
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
        'created_by' => $user->id,
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
        ->assertSee('Rp 100.000.000')
        ->assertDontSee('Sisa Pembayaran Booking')
        ->assertSee('Bayar Midtrans')
        ->assertSee('Bayar Manual')
        ->assertSee('Pembayaran 1')
        ->assertSee('Biaya Akad')
        ->assertSee('B/08')
        ->assertSee(route('marketing.bookings.payments.midtrans.store', $booking), false)
        ->assertDontSee('B/09')
        ->assertDontSee('B/10')
        ->assertDontSee('B/11')
        ->assertDontSee('Customer Lain')
        ->assertDontSee('Tidak ada booking yang cocok dengan filter.');
});

it('shows admin-defined booking payment amounts and records manual payments at the configured amount', function () {
    Storage::fake('public');
    $marketingDivision = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $marketingDivision->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Jadwal Booking']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 100000000,
    ]);
    $customer = Customer::create(['name' => 'Customer Jadwal Booking']);
    $schedule = BookingPaymentSchedule::query()->create([
        'property_id' => $property->id,
        'title' => 'Pembayaran 1',
        'amount' => 1000000,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'created_by' => $user->id,
        'booking_date' => today(),
        'house_price_snapshot' => 100000000,
        'akad_fee' => 0,
        'status' => 'booking',
    ]);

    $this->actingAs($user)
        ->get(route('marketing.payments.index'))
        ->assertSee('Pembayaran 1')
        ->assertSee('Bayar Midtrans')
        ->assertSee('Bayar Manual')
        ->assertSee('id="payment-dialog-'.$booking->id.'-booking_schedule_'.$schedule->id.'"', false)
        ->assertSee('Nominal pembayaran: Rp 1.000.000')
        ->assertSee('name="notes"', false)
        ->assertSee('Ajukan Pembayaran Manual')
        ->assertDontSee('Sisa Pembayaran Booking')
        ->assertDontSee('Tambah Pembayaran Booking')
        ->assertDontSee('xl:grid-cols-[minmax(0,1fr)_minmax(18rem,0.8fr)]', false)
        ->assertDontSee('name="amount"', false);

    $this->post(route('marketing.bookings.payments.store', $booking), [
        'stage' => 'booking_schedule_'.$schedule->id,
        'booking_payment_schedule_id' => $schedule->id,
        'amount' => 1,
        'paid_at' => now()->subMinute()->format('Y-m-d H:i'),
        'payment_method' => 'Transfer bank',
        'proof' => UploadedFile::fake()->image('bukti.jpg'),
    ])->assertRedirect(route('marketing.bookings.show', $booking));

    $this->assertDatabaseHas('booking_payments', [
        'booking_id' => $booking->id,
        'booking_payment_schedule_id' => $schedule->id,
        'stage' => 'booking',
        'amount' => '1000000.00',
        'approval_status' => 'pending',
    ]);
    $this->assertDatabaseCount('finance_cash_flows', 0);
});

it('lets admins add a booking payment schedule from the payment list', function () {
    $marketingDivision = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $staff = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $marketingDivision->id,
    ]);
    $admin = User::factory()->create([
        'role' => 'admin',
        'division_id' => $marketingDivision->id,
    ]);
    $booking = makePaymentControllerTestBooking($staff);
    $scheduleUrl = route('admin.properties.payment-schedules.index', $booking->lot->block->property);

    $this->actingAs($staff)
        ->get(route('marketing.payments.index'))
        ->assertSee('Jadwal belum diatur admin')
        ->assertDontSee('Tambah Pembayaran')
        ->assertDontSee($scheduleUrl, false);

    $this->actingAs($admin)
        ->get(route('marketing.payments.index'))
        ->assertSee('Tambah Pembayaran')
        ->assertSee('Tambah Pembayaran Booking')
        ->assertSee('Judul pembayaran')
        ->assertSee('Nominal (Rp)')
        ->assertSee($scheduleUrl, false);

    $this->from(route('marketing.payments.index'))
        ->actingAs($admin)
        ->post(route('admin.properties.payment-schedules.store', $booking->lot->block->property), [
            'booking_id' => $booking->id,
            'title' => 'Pembayaran Tiang',
            'amount' => '1.000.000',
        ])
        ->assertRedirect(route('marketing.payments.index'));

    $this->assertDatabaseHas('booking_payment_schedules', [
        'property_id' => $booking->lot->block->property_id,
        'title' => 'Pembayaran Tiang',
        'amount' => '1000000.00',
        'is_active' => true,
    ]);
});

it('marks a paid stage green and allows later stages and akad to be paid independently', function () {
    $marketingDivision = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $marketingDivision->id,
    ]);
    $booking = makePaymentControllerTestBooking($user);
    $property = $booking->lot->block->property;
    $firstSchedule = BookingPaymentSchedule::query()->create([
        'property_id' => $property->id,
        'title' => 'Pembayaran Tiang',
        'amount' => 1000000,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $secondSchedule = BookingPaymentSchedule::query()->create([
        'property_id' => $property->id,
        'title' => 'Pembayaran Atap',
        'amount' => 5000000,
        'sort_order' => 2,
        'is_active' => true,
    ]);
    BookingPayment::query()->create([
        'booking_id' => $booking->id,
        'booking_payment_schedule_id' => $firstSchedule->id,
        'stage' => 'booking',
        'amount' => 1000000,
        'paid_at' => now(),
        'payment_channel' => 'manual',
        'approval_status' => 'approved',
    ]);

    $this->actingAs($user)
        ->get(route('marketing.payments.index'))
        ->assertSee('Pembayaran Tiang')
        ->assertSee('bg-emerald-100 text-emerald-800', false)
        ->assertSee('Pembayaran Atap')
        ->assertSee('Bayar Midtrans')
        ->assertSee('Bayar Manual')
        ->assertSee('name="stage" value="booking_schedule_'.$secondSchedule->id.'"', false)
        ->assertSee('name="stage" value="akad_fee"', false)
        ->assertDontSee('Selesaikan tahap sebelumnya');
});

it('requires marketing access to view booking payments', function () {
    $financeDivision = Division::query()->firstOrCreate(
        ['slug' => 'finance'],
        ['name' => 'Finance', 'is_active' => true],
    );
    $user = User::factory()->create([
        'role' => 'staff_finance',
        'division_id' => $financeDivision->id,
    ]);

    $this->actingAs($user)
        ->get(route('marketing.payments.index'))
        ->assertForbidden();
});

it('shows payment transaction history only on the booking detail page', function () {
    $marketingDivision = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $marketingDivision->id,
    ]);
    $booking = makePaymentControllerTestBooking($user);
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
        ->assertDontSee('Riwayat pembayaran')
        ->assertDontSee('Riwayat transaksi Midtrans')
        ->assertDontSee('midtrans-transaction-history')
        ->assertDontSee('BK'.$booking->id.'-HISTORY')
        ->assertSee('Lihat detail booking dan riwayat pembayaran')
        ->assertSee(route('marketing.bookings.show', $booking), false)
        ->assertSee('Cek status pembayaran');

    $this->get(route('marketing.bookings.show', $booking))
        ->assertViewIs('marketing.bookings.show')
        ->assertSee('Riwayat Transaksi Pembayaran')
        ->assertSee('Riwayat pembayaran')
        ->assertSee('Riwayat transaksi Midtrans')
        ->assertSee('21/10/2026 10:30')
        ->assertSee('Midtrans - bank_transfer')
        ->assertSee($paymentOrder->order_id)
        ->assertSee('midtrans-transaction-history')
        ->assertSee('Berhasil');
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
    $booking = makePaymentControllerTestBooking($user);
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
    $booking = makePaymentControllerTestBooking($user);

    $this->actingAs($user)
        ->post(route('marketing.payments.archive.store', $booking))
        ->assertUnprocessable();

    expect($booking->fresh()->payment_archived_at)->toBeNull();
});

it('redirects guests to sign in before showing booking payments', function () {
    $this->get(route('marketing.payments.index'))
        ->assertRedirect(route('login'));
});

function makePaymentControllerTestBooking(User $creator): Booking
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
        'created_by' => $creator->id,
        'booking_date' => '2026-10-21',
        'house_price_snapshot' => 100000000,
        'akad_fee' => 2000000,
        'status' => 'booking',
    ]);
}
