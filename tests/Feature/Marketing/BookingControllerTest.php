<?php

use App\Models\Block;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Customer;
use App\Models\Division;
use App\Models\Lot;
use App\Models\PemberkasanDocument;
use App\Models\Property;
use App\Models\User;
use App\Services\GoogleDriveService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

it('deletes only the selected booking customer files from Google Drive when the cancelled booking is deleted', function () {
    Storage::fake('local');

    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Hapus Booking']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
        'status' => 'booked',
    ]);
    $customer = Customer::create(['name' => 'Customer Dihapus']);
    $otherCustomer = Customer::create(['name' => 'Customer Lain']);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-10-01',
        'status' => 'cancelled',
    ]);
    $otherBooking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $otherCustomer->id,
        'booking_date' => '2026-10-02',
        'status' => 'cancelled',
    ]);
    $document = PemberkasanDocument::create([
        'booking_id' => $booking->id,
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'document_type' => 'KTP Pemohon',
        'original_name' => 'ktp-dihapus.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 120,
        'local_path' => 'pemberkasan/documents/ktp-dihapus.pdf',
        'drive_file_id' => 'booking-drive-file',
        'status' => 'uploaded',
    ]);
    $otherDocument = PemberkasanDocument::create([
        'booking_id' => $otherBooking->id,
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'customer_id' => $otherCustomer->id,
        'document_type' => 'KTP Pemohon',
        'original_name' => 'ktp-lain.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 120,
        'local_path' => 'pemberkasan/documents/ktp-lain.pdf',
        'drive_file_id' => 'other-booking-drive-file',
        'status' => 'uploaded',
    ]);
    Storage::disk('local')->put('pemberkasan/documents/ktp-dihapus.pdf', 'selected booking document');
    Storage::disk('local')->put('pemberkasan/documents/ktp-lain.pdf', 'other booking document');

    $googleDriveService = Mockery::mock(GoogleDriveService::class);
    $googleDriveService->shouldReceive('deleteFileIfExists')
        ->once()
        ->with('booking-drive-file')
        ->andReturnTrue();
    $googleDriveService->shouldNotReceive('deleteFileIfExists')
        ->with('other-booking-drive-file');
    $this->app->instance(GoogleDriveService::class, $googleDriveService);

    $this->actingAs($user)
        ->from(route('marketing.bookings.index'))
        ->delete(route('marketing.bookings.destroy', $booking))
        ->assertRedirect(route('marketing.bookings.index'))
        ->assertSessionHas('success', 'Booking cancelled berhasil dihapus.');

    $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    $this->assertDatabaseMissing('pemberkasan_documents', ['id' => $document->id]);
    $this->assertModelExists($otherBooking);
    $this->assertModelExists($otherDocument);
    $this->assertDatabaseHas('lots', ['id' => $lot->id, 'status' => 'available']);
    Storage::disk('local')->assertMissing('pemberkasan/documents/ktp-dihapus.pdf');
    Storage::disk('local')->assertExists('pemberkasan/documents/ktp-lain.pdf');
});

it('keeps a cancelled booking when deleting its Google Drive documents fails', function () {
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Gagal Hapus']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
        'status' => 'booked',
    ]);
    $customer = Customer::create(['name' => 'Customer Gagal Hapus']);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-10-01',
        'status' => 'cancelled',
    ]);
    $document = PemberkasanDocument::create([
        'booking_id' => $booking->id,
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'document_type' => 'KTP Pemohon',
        'original_name' => 'ktp.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 120,
        'local_path' => 'pemberkasan/documents/ktp.pdf',
        'drive_file_id' => 'booking-drive-file',
        'status' => 'uploaded',
    ]);

    $googleDriveService = Mockery::mock(GoogleDriveService::class);
    $googleDriveService->shouldReceive('deleteFileIfExists')
        ->once()
        ->with('booking-drive-file')
        ->andThrow(new RuntimeException('Google Drive deletion failed.'));
    $this->app->instance(GoogleDriveService::class, $googleDriveService);

    $this->actingAs($user)
        ->from(route('marketing.bookings.index'))
        ->delete(route('marketing.bookings.destroy', $booking))
        ->assertRedirect(route('marketing.bookings.index'))
        ->assertSessionHasErrors(['booking' => 'Google Drive deletion failed.']);

    $this->assertModelExists($booking);
    $this->assertModelExists($document);
    $this->assertDatabaseHas('lots', ['id' => $lot->id, 'status' => 'booked']);
});

it('filters marketing bookings by selected customer', function () {
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Filter Customer']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $filteredCustomer = Customer::create(['name' => 'Customer Terpilih']);
    $otherCustomer = Customer::create(['name' => 'Customer Lain']);
    $filteredLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
    ]);
    $otherLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '02',
        'house_price' => 350000000,
    ]);
    Booking::create([
        'lot_id' => $filteredLot->id,
        'customer_id' => $filteredCustomer->id,
        'booking_date' => '2026-10-01',
        'status' => 'booking',
    ]);
    Booking::create([
        'lot_id' => $otherLot->id,
        'customer_id' => $otherCustomer->id,
        'booking_date' => '2026-10-02',
        'status' => 'booking',
    ]);

    $this->actingAs($user)
        ->get(route('marketing.bookings.index', ['customer_id' => $filteredCustomer->id]))
        ->assertSee('01/10/2026')
        ->assertDontSee('02/10/2026')
        ->assertSee('value="'.$filteredCustomer->id.'" selected', false);
});

it('renders the full-house DP amount input with a Rupiah-formatted akad fee field', function () {
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);

    $this->actingAs($user)
        ->get(route('marketing.bookings.create'))
        ->assertSee('name="akad_fee"', false)
        ->assertDontSee('name="dp_percentage"', false)
        ->assertSee('name="dp_paid_amount"', false)
        ->assertSee('DP Sudah Dibayar (Rp)')
        ->assertSee('name="akad_paid_amount"', false)
        ->assertSee('Biaya Akad Sudah Dibayar (Rp)')
        ->assertSee('Total pembayaran DP sampai lunas adalah 100% harga rumah')
        ->assertSee('Jika ada pembayaran dari bank, nominalnya ikut dicatat sebagai pembayaran DP.')
        ->assertSee('dpPaidAmount / housePrice * 100', false)
        ->assertDontSee('DP Wajib')
        ->assertSee('data-currency-input', false)
        ->assertSee('inputmode="decimal"', false)
        ->assertSee('data-currency-min="0"', false)
        ->assertDontSee('name="akad_fee" type="number"', false);
});

it('stores valid Google Maps work-location links and rejects malformed URLs', function () {
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Lokasi Kerja']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 350000000,
        'status' => 'booked',
    ]);
    $customer = Customer::create(['name' => 'Customer Lokasi Kerja']);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-10-01',
        'blocking_until' => now()->addDay(),
        'status' => 'booking',
    ]);
    $payload = [
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'customer_mode' => 'existing',
        'customer_id' => $customer->id,
        'booking_date' => '2026-10-01',
        'blocking_until' => now()->addDay()->toDateTimeString(),
        'akad_fee' => 0,
        'status' => 'booking',
    ];

    $this->actingAs($user)
        ->put(route('marketing.bookings.update', $booking), [
            ...$payload,
            'applicant_work_location_url' => 'not-a-url',
        ])
        ->assertSessionHasErrors('applicant_work_location_url');

    $this->put(route('marketing.bookings.update', $booking), [
        ...$payload,
        'applicant_work_location_url' => 'https://maps.google.com/?q=kantor-pemohon',
        'spouse_work_location_url' => 'https://maps.app.goo.gl/lokasi-pasangan',
    ])->assertRedirect(route('marketing.bookings.index'));

    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'applicant_work_location_url' => 'https://maps.google.com/?q=kantor-pemohon',
        'spouse_work_location_url' => 'https://maps.app.goo.gl/lokasi-pasangan',
    ]);

    $this->get(route('marketing.bookings.show', $booking))
        ->assertSee('https://maps.google.com/?q=kantor-pemohon')
        ->assertSee('https://maps.app.goo.gl/lokasi-pasangan');
});

it('shows total DP and customer payment progress in the booking list, edit form, and detail', function () {
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Progres DP']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 100000000,
        'status' => 'booked',
    ]);
    $customer = Customer::create(['name' => 'Customer DP']);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-10-12',
        'blocking_until' => '2026-10-20 10:00:00',
        'house_price_snapshot' => 100000000,
        'dp_percentage' => 20,
        'akad_fee' => 2000000,
        'status' => 'booking',
    ]);
    BookingPayment::create([
        'booking_id' => $booking->id,
        'stage' => 'dp',
        'amount' => 5000000,
        'paid_at' => '2026-10-12 10:00:00',
        'recorded_by' => $user->id,
    ]);
    BookingPayment::create([
        'booking_id' => $booking->id,
        'stage' => 'bank_settlement',
        'amount' => 5000000,
        'paid_at' => '2026-10-12 11:00:00',
        'recorded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('marketing.bookings.index'))
        ->assertSee('DP terbayar')
        ->assertSee('10.00%')
        ->assertSee('text-orange-700')
        ->assertSee('Rp 10.000.000')
        ->assertSee('Target Rp 100.000.000')
        ->assertSee('Sisa Rp 90.000.000')
        ->assertSee('bg-amber-100')
        ->assertSee('>Booking</span>', false)
        ->assertSee('Termasuk pembayaran dari bank');

    $this->get(route('marketing.bookings.edit', $booking))
        ->assertSee('Progres DP customer')
        ->assertSee('value="10000000.00"', false)
        ->assertSee('Target pembayaran DP: 100% harga rumah (Rp 100.000.000)')
        ->assertSee('Sudah dibayar: Rp 10.000.000 · 10.00% dari harga rumah')
        ->assertSee('Sisa pembayaran DP: Rp 90.000.000.');

    $this->get(route('marketing.bookings.show', $booking))
        ->assertSee('Target pembayaran DP')
        ->assertSee('DP dibayar dari harga rumah')
        ->assertSee('Total DP terbayar')
        ->assertSee('100% harga')
        ->assertSee('10.00%')
        ->assertSee('Pembayaran DP (Bank)')
        ->assertSee('Pencatatan atau pembaruan nominal DP dilakukan melalui')
        ->assertDontSee('Catat Pembayaran: Pembayaran DP')
        ->assertDontSee('name="stage" value="dp"', false);

    $this->get(route('marketing.bookings.edit', $booking))
        ->assertSee('name="dp_paid_amount"', false)
        ->assertSee('DP Sudah Dibayar (Rp)')
        ->assertSee('name="akad_paid_amount"', false)
        ->assertSee('Biaya Akad Sudah Dibayar (Rp)')
        ->assertSee('Progres biaya akad');
});

it('creates a booking with inline customer details and locked payment terms', function () {
    $this->travelTo(Carbon::parse('2026-10-12 10:00:00'));
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Booking Baru']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'available',
    ]);

    $bookingData = [
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'customer_mode' => 'new',
        'new_customer' => [
            'name' => 'Pembeli Baru',
            'nik' => '1234567890123456',
            'phone' => '081234567890',
        ],
        'booking_date' => '2026-10-12',
        'blocking_until' => '2026-10-15',
        'dp_paid_amount' => 25000000,
        'akad_fee' => 2500000,
        'status' => 'booking',
    ];
    $missingDeadlineData = $bookingData;
    unset($missingDeadlineData['blocking_until']);

    $this->actingAs($user)
        ->post(route('marketing.bookings.store'), $missingDeadlineData)
        ->assertSessionHasErrors('blocking_until');
    $excessiveInitialDpData = [...$bookingData, 'dp_paid_amount' => 500000000.01];
    $this->post(route('marketing.bookings.store'), $excessiveInitialDpData)
        ->assertSessionHasErrors('dp_paid_amount');
    $this->post(route('marketing.bookings.store'), [...$bookingData, 'akad_paid_amount' => 1])
        ->assertSessionHasErrors('akad_paid_amount');
    $this->post(route('marketing.bookings.store'), [
        ...$bookingData,
        'dp_paid_amount' => 500000000,
        'akad_paid_amount' => 2500000.01,
    ])
        ->assertSessionHasErrors('akad_paid_amount');
    $this->assertDatabaseCount('bookings', 0);

    $this->post(route('marketing.bookings.store'), $bookingData)
        ->assertRedirect(route('marketing.bookings.index'))
        ->assertSessionHasNoErrors();

    $customer = Customer::query()->where('nik', '1234567890123456')->firstOrFail();
    $booking = Booking::query()->where('customer_id', $customer->id)->firstOrFail();

    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'lot_id' => $lot->id,
        'house_price_snapshot' => '500000000.00',
        'dp_percentage' => '100.00',
        'akad_fee' => '2500000.00',
        'status' => 'booking',
    ]);
    $this->assertDatabaseHas('lots', ['id' => $lot->id, 'status' => 'booked']);
    $this->assertDatabaseHas('booking_histories', [
        'booking_id' => $booking->id,
        'to_status' => 'booking',
        'changed_by' => $user->id,
    ]);
    $this->assertDatabaseHas('booking_payments', [
        'booking_id' => $booking->id,
        'stage' => 'dp',
        'amount' => '25000000.00',
        'recorded_by' => $user->id,
    ]);
});

it('records payments in order and rejects out-of-order or excessive amounts', function () {
    $this->travelTo(Carbon::parse('2026-10-12 10:00:00'));
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Perumahan Pembayaran']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 100000000,
        'status' => 'booked',
    ]);
    $customer = Customer::create(['name' => 'Pembeli Pembayaran']);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-10-01',
        'house_price_snapshot' => 100000000,
        'dp_percentage' => 20,
        'akad_fee' => 2000000,
        'status' => 'booking',
    ]);
    $paymentRoute = route('marketing.bookings.payments.store', $booking);
    $paymentData = [
        'paid_at' => '2026-10-12 10:00:00',
        'payment_method' => 'transfer',
    ];

    $this->actingAs($user)
        ->post($paymentRoute, [...$paymentData, 'stage' => 'bank_settlement', 'amount' => 1])
        ->assertSessionHasErrors('stage');
    $this->post($paymentRoute, [...$paymentData, 'stage' => 'dp', 'amount' => 50000001])
        ->assertSessionHasErrors('amount');
    $this->post($paymentRoute, [...$paymentData, 'stage' => 'dp', 'amount' => 1.001])
        ->assertSessionHasErrors('amount');
    $this->post($paymentRoute, [...$paymentData, 'stage' => 'dp', 'amount' => 10000000])
        ->assertRedirect(route('marketing.bookings.show', $booking))
        ->assertSessionHasNoErrors();
    $otherCustomer = Customer::create(['name' => 'Customer Pengganti']);
    $this->put(route('marketing.bookings.update', $booking), [
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'customer_mode' => 'existing',
        'customer_id' => $otherCustomer->id,
        'booking_date' => '2026-10-01',
        'blocking_until' => '2026-10-20 10:00:00',
        'dp_percentage' => 20,
        'akad_fee' => 2000000,
        'status' => 'booking',
    ])->assertSessionHasErrors('customer_id');
    $this->put(route('marketing.bookings.update', $booking), [
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'customer_mode' => 'existing',
        'customer_id' => $customer->id,
        'booking_date' => '2026-10-01',
        'blocking_until' => '2026-10-20 10:00:00',
        'dp_paid_amount' => 5000000,
        'akad_fee' => 2000000,
        'status' => 'booking',
    ])->assertSessionHasErrors('dp_paid_amount');

    $this->put(route('marketing.bookings.update', $booking), [
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'customer_mode' => 'existing',
        'customer_id' => $customer->id,
        'booking_date' => '2026-10-01',
        'blocking_until' => '2026-10-20 10:00:00',
        'dp_paid_amount' => 15000000,
        'akad_fee' => 2000000,
        'status' => 'booking',
    ])->assertRedirect(route('marketing.bookings.index'));

    $this->assertDatabaseHas('booking_payments', [
        'booking_id' => $booking->id,
        'stage' => 'dp',
        'amount' => '5000000.00',
        'notes' => 'Tambahan pembayaran DP dicatat dari form booking.',
    ]);

    $this->post($paymentRoute, [...$paymentData, 'stage' => 'dp', 'amount' => 85000000.01])
        ->assertSessionHasErrors('amount');
    $this->post($paymentRoute, [...$paymentData, 'stage' => 'dp', 'amount' => 85000000, 'payment_method' => 'Bank KPR'])
        ->assertRedirect(route('marketing.bookings.show', $booking));
    $this->put(route('marketing.bookings.update', $booking), [
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'customer_mode' => 'existing',
        'customer_id' => $customer->id,
        'booking_date' => '2026-10-01',
        'blocking_until' => '2026-10-20 10:00:00',
        'dp_paid_amount' => 100000000,
        'akad_fee' => 2000000,
        'akad_paid_amount' => 1000000,
        'status' => 'booking',
    ])->assertRedirect(route('marketing.bookings.index'));
    $this->get(route('marketing.bookings.edit', $booking))
        ->assertSee('name="akad_paid_amount" value="1000000.00"', false)
        ->assertSee('Sudah dibayar: Rp 1.000.000 · 50.00%');
    $this->put(route('marketing.bookings.update', $booking), [
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'customer_mode' => 'existing',
        'customer_id' => $customer->id,
        'booking_date' => '2026-10-01',
        'blocking_until' => '2026-10-20 10:00:00',
        'dp_paid_amount' => 100000000,
        'akad_fee' => 2000000,
        'akad_paid_amount' => 0,
        'status' => 'booking',
    ])->assertSessionHasErrors('akad_paid_amount');
    $this->post($paymentRoute, [...$paymentData, 'stage' => 'akad_fee', 'amount' => 1000000])
        ->assertRedirect(route('marketing.bookings.show', $booking));
    $this->assertDatabaseCount('booking_payments', 5);
    $this->assertDatabaseHas('booking_payments', [
        'booking_id' => $booking->id,
        'stage' => 'dp',
        'amount' => '85000000.00',
        'payment_method' => 'Bank KPR',
    ]);
    $this->assertDatabaseHas('booking_payments', [
        'booking_id' => $booking->id,
        'stage' => 'akad_fee',
        'amount' => '1000000.00',
        'notes' => 'Tambahan pembayaran biaya akad dicatat dari form booking.',
    ]);
    $this->get(route('marketing.bookings.show', $booking))
        ->assertSee('Pembayaran DP')
        ->assertSee('Biaya Akad')
        ->assertDontSee('Pelunasan dari Bank');
});
