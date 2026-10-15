<?php

use App\Models\Block;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Division;
use App\Models\DocumentProcess;
use App\Models\Lot;
use App\Models\PemberkasanDocument;
use App\Models\Property;
use App\Models\User;
use App\Services\GoogleDriveService;
use App\Services\PemberkasanDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function mockSharedDocumentGoogleDriveUpload(Booking $booking, string $documentType): void
{
    config(['services.google_drive.root_folder_id' => 'root-folder']);

    $googleDriveService = Mockery::mock(GoogleDriveService::class);
    $documentFolderName = str_starts_with(strtolower($documentType), 'ktp')
        ? 'KTP'
        : 'DOKUMEN LAINNYA';
    $googleDriveService->shouldReceive('configuredRootFolderId')
        ->once()
        ->andReturn('root-folder');

    foreach ([
        [$booking->lot->block->property->name, 'root-folder', 'property-folder'],
        [$booking->lot->block->name, 'property-folder', 'block-folder'],
        [$booking->lot->block->name.'-'.$booking->lot->lot_number, 'block-folder', 'lot-folder'],
        [$booking->customer->name, 'lot-folder', 'customer-folder'],
        [$documentFolderName, 'customer-folder', 'document-folder'],
    ] as [$name, $parentId, $folderId]) {
        $googleDriveService->shouldReceive('findOrCreateFolder')
            ->once()
            ->with($name, $parentId)
            ->andReturn(['id' => $folderId]);
    }

    $googleDriveService->shouldReceive('uploadFile')
        ->once()
        ->withArgs(fn (string $path, string $name, string $folderId, string $mimeType): bool => is_file($path)
            && $folderId === 'document-folder'
            && $mimeType === 'application/pdf')
        ->andReturn([
            'id' => 'shared-drive-file',
            'name' => $documentType.'_'.$booking->customer->name.'.pdf',
            'mimeType' => 'application/pdf',
            'url' => 'https://drive.google.com/file/d/shared-drive-file/view',
        ]);

    app()->instance(GoogleDriveService::class, $googleDriveService);
}

function createSharedDocumentBooking(string $name = 'Prospek Booking'): Booking
{
    $property = Property::create(['name' => 'Perumahan Berkas']);
    $block = Block::create([
        'property_id' => $property->id,
        'name' => 'A',
    ]);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'booked',
    ]);
    $customer = Customer::create(['name' => $name]);

    return Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-10-01',
        'status' => 'booking',
    ]);
}

test('pemberkasan uploads additional booking documents that marketing can view from booking details', function () {
    Storage::fake('local');

    $marketingDivision = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $pemberkasanDivision = Division::create([
        'name' => 'Pemberkasan',
        'slug' => 'pemberkasan',
        'is_active' => true,
    ]);
    $pemberkasanUser = User::factory()->create([
        'role' => 'staff_pemberkasan',
        'division_id' => $pemberkasanDivision->id,
    ]);
    $marketingUser = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $marketingDivision->id,
    ]);
    $booking = createSharedDocumentBooking();
    DocumentProcess::create([
        'booking_id' => $booking->id,
        'bi_checking_status' => 'approved',
    ]);
    mockSharedDocumentGoogleDriveUpload($booking, 'Surat Keterangan Kerja');

    $this->actingAs($pemberkasanUser)
        ->post(route('pemberkasan.other-documents.store'), [
            'booking_id' => $booking->id,
            'document_type' => 'Surat Keterangan Kerja',
            'document' => UploadedFile::fake()->create('surat-kerja.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect(route('pemberkasan.other-documents.index'));

    $document = PemberkasanDocument::query()->where('booking_id', $booking->id)->firstOrFail();
    expect($document->document_category)->toBe('other')
        ->and($document->uploaded_by)->toBe($pemberkasanUser->id)
        ->and($document->status)->toBe('uploaded')
        ->and($document->drive_file_id)->toBe('shared-drive-file');

    $this->actingAs($pemberkasanUser)
        ->get(route('pemberkasan.other-documents.index'))
        ->assertSee('surat-kerja.pdf')
        ->assertSee('Prospek Booking');

    $this->actingAs($marketingUser)
        ->get(route('marketing.bookings.show', $booking))
        ->assertSee('surat-kerja.pdf')
        ->assertSee('Berkas lainnya')
        ->assertDontSee('Buka Berkas Lainnya');
});

test('marketing cannot access the separate additional documents page', function () {
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
        ->get('/marketing/other-documents')
        ->assertNotFound();

    $this->actingAs($user)
        ->post('/marketing/other-documents')
        ->assertNotFound();
});

test('pemberkasan can upload additional booking documents before BI checking approval', function () {
    Storage::fake('local');

    $division = Division::create([
        'name' => 'Pemberkasan',
        'slug' => 'pemberkasan',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_pemberkasan',
        'division_id' => $division->id,
    ]);
    $booking = createSharedDocumentBooking('BI Belum Lolos');
    DocumentProcess::create([
        'booking_id' => $booking->id,
        'bi_checking_status' => 'processing',
    ]);
    mockSharedDocumentGoogleDriveUpload($booking, 'Surat Keterangan Kerja');

    $this->actingAs($user)
        ->post(route('pemberkasan.other-documents.store'), [
            'booking_id' => $booking->id,
            'document_type' => 'Surat Keterangan Kerja',
            'document' => UploadedFile::fake()->create('surat-kerja.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect(route('pemberkasan.other-documents.index'))
        ->assertSessionHas('success', 'Berkas lainnya berhasil diunggah langsung ke Google Drive.');

    $this->assertDatabaseHas('pemberkasan_documents', [
        'booking_id' => $booking->id,
        'document_category' => 'other',
        'document_type' => 'Surat Keterangan Kerja',
        'status' => 'uploaded',
    ]);
});

test('marketing can upload BI checking identity documents before approval', function () {
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
    $booking = createSharedDocumentBooking('Pemohon BI');
    DocumentProcess::create([
        'booking_id' => $booking->id,
        'bi_checking_status' => 'processing',
    ]);
    mockSharedDocumentGoogleDriveUpload($booking, 'KTP Pemohon');

    $this->actingAs($user)
        ->post(route('marketing.bookings.documents.store', $booking), [
            'document_category' => 'standard',
            'document_type' => 'KTP Pemohon',
            'document' => UploadedFile::fake()->create('ktp-pemohon.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('pemberkasan_documents', [
        'booking_id' => $booking->id,
        'document_type' => 'KTP Pemohon',
        'document_category' => 'standard',
        'status' => 'uploaded',
    ]);
});

test('marketing cannot bypass BI approval by assigning an additional document to the standard category', function () {
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
    $booking = createSharedDocumentBooking('BI Belum Disetujui');
    DocumentProcess::create([
        'booking_id' => $booking->id,
        'bi_checking_status' => 'processing',
    ]);

    $this->actingAs($user)
        ->post(route('marketing.bookings.documents.store', $booking), [
            'document_category' => 'standard',
            'document_type' => 'Kartu Keluarga',
            'document' => UploadedFile::fake()->create('kk.pdf', 120, 'application/pdf'),
        ])
        ->assertSessionHasErrors('document_category');

    $this->assertDatabaseMissing('pemberkasan_documents', [
        'booking_id' => $booking->id,
        'document_type' => 'Kartu Keluarga',
    ]);
});

test('marketing booking details move document uploads to the dedicated edit documents page', function () {
    $division = Division::create([
        'name' => 'Marketing',
        'slug' => 'marketing',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_marketing',
        'division_id' => $division->id,
    ]);
    $booking = createSharedDocumentBooking('Edit Berkas Marketing');

    $this->actingAs($user)
        ->get(route('marketing.bookings.show', $booking))
        ->assertOk()
        ->assertSee('Edit Booking')
        ->assertSee('Edit Berkas')
        ->assertDontSee('Unggah berkas ke Google Drive')
        ->assertDontSee('type="file"', false);

    $response = $this->get(route('marketing.bookings.documents.edit', $booking))
        ->assertOk()
        ->assertSee('Edit Berkas Booking');

    foreach (array_merge(
        PemberkasanDocumentService::BI_CHECKING_DOCUMENT_TYPES,
        PemberkasanDocumentService::ADDITIONAL_MARKETING_DOCUMENT_TYPES,
        PemberkasanDocumentService::PROPERTY_DOCUMENT_TYPES,
    ) as $documentType) {
        $response->assertSee('name="document_uploads['.$documentType.']"', false);
    }
});

test('marketing can upload property documents from edit documents before BI approval', function () {
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
    $booking = createSharedDocumentBooking('Upload Berkas Properti');
    mockSharedDocumentGoogleDriveUpload($booking, 'PBG');

    $this->actingAs($user)
        ->put(route('marketing.bookings.documents.update', $booking), [
            'document_uploads' => [
                'PBG' => UploadedFile::fake()->create('pbg.pdf', 120, 'application/pdf'),
            ],
        ])
        ->assertRedirect(route('marketing.bookings.documents.edit', $booking))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', '1 berkas berhasil diunggah ke Google Drive.');

    $this->assertDatabaseHas('pemberkasan_documents', [
        'booking_id' => $booking->id,
        'document_type' => 'PBG',
        'document_category' => 'other',
        'status' => 'uploaded',
        'uploaded_by' => $user->id,
    ]);
});

test('marketing cannot upload unsupported document types from edit documents', function () {
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
    $booking = createSharedDocumentBooking('Jenis Berkas Tidak Valid');

    $this->actingAs($user)
        ->put(route('marketing.bookings.documents.update', $booking), [
            'document_uploads' => [
                'Berkas Rahasia' => UploadedFile::fake()->create('berkas.pdf', 120, 'application/pdf'),
            ],
        ])
        ->assertSessionHasErrors('document_uploads');

    $this->assertDatabaseMissing('pemberkasan_documents', [
        'booking_id' => $booking->id,
        'document_type' => 'Berkas Rahasia',
    ]);
});

test('pemberkasan can upload legacy customer files before BI approval', function () {
    Storage::fake('local');

    $division = Division::create([
        'name' => 'Pemberkasan',
        'slug' => 'pemberkasan',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_pemberkasan',
        'division_id' => $division->id,
    ]);
    $booking = createSharedDocumentBooking('Customer Berkas');
    DocumentProcess::create([
        'booking_id' => $booking->id,
        'bi_checking_status' => 'processing',
    ]);
    Storage::fake('public');
    mockSharedDocumentGoogleDriveUpload($booking, 'KK');

    $this->actingAs($user)
        ->put(route('pemberkasan.bookings.update', $booking), [
            'customer_kk_file' => UploadedFile::fake()->create('kk.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect(route('pemberkasan.bookings.show', $booking))
        ->assertSessionHas('success', 'Data pemberkasan berhasil diperbarui.');

    expect($booking->customer->fresh()->kk_file)->not->toBeNull();
    $this->assertDatabaseHas('pemberkasan_documents', [
        'booking_id' => $booking->id,
        'document_type' => 'KK',
        'document_category' => 'other',
        'status' => 'uploaded',
    ]);
});

test('legacy customer document sync accepts files before BI checking approval', function () {
    Storage::fake('local');
    Storage::fake('public');
    Storage::disk('public')->put('customers/kk.pdf', 'pdf-data');

    $division = Division::create([
        'name' => 'Pemberkasan',
        'slug' => 'pemberkasan',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_pemberkasan',
        'division_id' => $division->id,
    ]);
    $booking = createSharedDocumentBooking('Customer Sinkron');
    $booking->customer->update(['kk_file' => 'customers/kk.pdf']);
    DocumentProcess::create([
        'booking_id' => $booking->id,
        'bi_checking_status' => 'processing',
    ]);
    mockSharedDocumentGoogleDriveUpload($booking, 'KK');

    $this->actingAs($user)
        ->post(route('pemberkasan.bookings.documents.sync', $booking))
        ->assertRedirect()
        ->assertSessionHas('success', '1 dokumen lama berhasil diunggah langsung ke Google Drive.');

    $this->assertDatabaseHas('pemberkasan_documents', [
        'booking_id' => $booking->id,
        'document_type' => 'KK',
        'document_category' => 'other',
        'status' => 'uploaded',
    ]);
});

test('pemberkasan Google Drive upload failures are shown and marked failed', function () {
    Storage::fake('local');

    $division = Division::create([
        'name' => 'Pemberkasan',
        'slug' => 'pemberkasan',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_pemberkasan',
        'division_id' => $division->id,
    ]);
    $booking = createSharedDocumentBooking('Prospek Upload Gagal');
    DocumentProcess::create([
        'booking_id' => $booking->id,
        'bi_checking_status' => 'approved',
    ]);

    $googleDriveService = Mockery::mock(GoogleDriveService::class);
    $googleDriveService->shouldReceive('configuredRootFolderId')
        ->once()
        ->andReturn('root-folder');
    $googleDriveService->shouldReceive('findOrCreateFolder')
        ->once()
        ->with('Perumahan Berkas', 'root-folder')
        ->andThrow(new RuntimeException('Google Drive tidak tersedia.'));
    $googleDriveService->shouldReceive('formatUploadError')
        ->once()
        ->andReturn('Google Drive tidak tersedia.');
    app()->instance(GoogleDriveService::class, $googleDriveService);

    $this->actingAs($user)
        ->post(route('pemberkasan.other-documents.store'), [
            'booking_id' => $booking->id,
            'document_type' => 'Surat Keterangan Kerja',
            'document' => UploadedFile::fake()->create('surat-kerja.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors([
            'document' => 'Google Drive tidak tersedia.',
        ]);

    $this->assertDatabaseHas('pemberkasan_documents', [
        'booking_id' => $booking->id,
        'document_category' => 'other',
        'status' => 'failed',
        'upload_error' => 'Google Drive tidak tersedia.',
    ]);
});

test('pemberkasan team leads and staff can change BI checking status', function (string $role) {
    $division = Division::create([
        'name' => 'Pemberkasan',
        'slug' => 'pemberkasan',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => $role,
        'division_id' => $division->id,
    ]);
    $booking = createSharedDocumentBooking('BI Review');
    DocumentProcess::create([
        'booking_id' => $booking->id,
        'bi_checking_status' => 'processing',
    ]);

    $this->actingAs($user)
        ->get(route('pemberkasan.bookings.show', $booking))
        ->assertSee('name="bi_checking_status"', false)
        ->assertSee('value="approved"', false);

    $this->actingAs($user)
        ->put(route('pemberkasan.bookings.update', $booking), [
            'bi_checking_status' => 'approved',
        ])
        ->assertRedirect(route('pemberkasan.bookings.show', $booking))
        ->assertSessionHas('success', 'Data pemberkasan berhasil diperbarui.');

    expect($booking->documentProcess()->value('bi_checking_status'))->toBe('approved');
})->with([
    'team lead' => 'tl_pemberkasan',
    'staff' => 'staff_pemberkasan',
]);

test('booking documents are uploaded from customer data and only missing documents are listed above', function () {
    Storage::fake('local');

    $division = Division::create([
        'name' => 'Pemberkasan',
        'slug' => 'pemberkasan',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_pemberkasan',
        'division_id' => $division->id,
    ]);
    $booking = createSharedDocumentBooking('Dokumen Customer');
    DocumentProcess::create([
        'booking_id' => $booking->id,
        'bi_checking_status' => 'processing',
    ]);

    $this->actingAs($user)
        ->get(route('pemberkasan.bookings.show', $booking))
        ->assertSee('Daftar berkas yang belum tersedia')
        ->assertSee('name="document_uploads[KTP Pemohon]"', false)
        ->assertSee('name="document_uploads[Kartu Keluarga]"', false)
        ->assertSee('Dokumen tetap dapat diunggah', false)
        ->assertDontSee('disabled', false)
        ->assertDontSee('booking-document-file')
        ->assertDontSee('Unggah berkas ke Google Drive');
});

test('pemberkasan staff can upload the new customer document fields before BI approval', function () {
    Storage::fake('local');

    $division = Division::create([
        'name' => 'Pemberkasan',
        'slug' => 'pemberkasan',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_pemberkasan',
        'division_id' => $division->id,
    ]);
    $booking = createSharedDocumentBooking('Upload Berkas Customer');
    DocumentProcess::create([
        'booking_id' => $booking->id,
        'bi_checking_status' => 'processing',
    ]);
    mockSharedDocumentGoogleDriveUpload($booking, 'Kartu Keluarga');

    $this->actingAs($user)
        ->put(route('pemberkasan.bookings.update', $booking), [
            'document_uploads' => [
                'Kartu Keluarga' => UploadedFile::fake()->create('kk.pdf', 120, 'application/pdf'),
            ],
        ])
        ->assertRedirect(route('pemberkasan.bookings.show', $booking))
        ->assertSessionHas('success', 'Data pemberkasan berhasil diperbarui.');

    $this->assertDatabaseHas('pemberkasan_documents', [
        'booking_id' => $booking->id,
        'document_type' => 'Kartu Keluarga',
        'document_category' => 'other',
        'status' => 'uploaded',
    ]);
});

test('pemberkasan staff can upload property documents through the booking form before BI approval', function () {
    Storage::fake('local');

    $division = Division::create([
        'name' => 'Pemberkasan',
        'slug' => 'pemberkasan',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_pemberkasan',
        'division_id' => $division->id,
    ]);
    $booking = createSharedDocumentBooking('BI Belum Disetujui');
    DocumentProcess::create([
        'booking_id' => $booking->id,
        'bi_checking_status' => 'processing',
    ]);
    mockSharedDocumentGoogleDriveUpload($booking, 'SPR');

    $this->actingAs($user)
        ->put(route('pemberkasan.bookings.update', $booking), [
            'document_uploads' => [
                'SPR' => UploadedFile::fake()->create('spr.pdf', 120, 'application/pdf'),
            ],
        ])
        ->assertRedirect(route('pemberkasan.bookings.show', $booking))
        ->assertSessionHas('success', 'Data pemberkasan berhasil diperbarui.');

    $this->assertDatabaseHas('pemberkasan_documents', [
        'booking_id' => $booking->id,
        'document_type' => 'SPR',
        'document_category' => 'other',
        'status' => 'uploaded',
    ]);
});

test('pemberkasan cannot continue to bank processing before BI checking is approved', function () {
    $division = Division::create([
        'name' => 'Pemberkasan',
        'slug' => 'pemberkasan',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_pemberkasan',
        'division_id' => $division->id,
    ]);
    $booking = createSharedDocumentBooking('Bank Belum Bisa Diproses');
    DocumentProcess::create([
        'booking_id' => $booking->id,
        'status' => 'complete',
        'bi_checking_status' => 'processing',
    ]);

    $this->actingAs($user)
        ->put(route('pemberkasan.bookings.update', $booking), [
            'document_status' => 'complete',
            'bank_name' => 'Bank Contoh',
            'bank_status' => 'processing',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors([
            'bank_name' => 'Berkas mandatory harus complete dan BI Checking harus approved sebelum proses bank.',
        ]);

    $this->assertDatabaseMissing('bank_processes', [
        'booking_id' => $booking->id,
    ]);
});
