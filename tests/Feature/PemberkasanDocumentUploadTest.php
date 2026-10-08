<?php

use App\Jobs\UploadGoogleDriveDocument;
use App\Models\Block;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Division;
use App\Models\Lot;
use App\Models\PemberkasanDocument;
use App\Models\Property;
use App\Models\User;
use App\Services\GoogleDriveService;
use App\Services\PemberkasanDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('google drive quota errors are translated into a actionable message', function () {
    $service = new GoogleDriveService;

    $message = $service->formatUploadError(new RuntimeException('Service Accounts do not have storage quota. Leverage shared drives or use OAuth delegation instead.'));

    expect($message)
        ->toContain('Shared Drive')
        ->toContain('quota');
});

beforeEach(function () {
    Storage::fake('local');
});

test('pemberkasan staff can upload a customer document to google drive and save metadata', function () {
    $division = Division::create([
        'name' => 'Pemberkasan',
        'slug' => 'pemberkasan',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_pemberkasan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Griya Asri']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'booked',
    ]);
    $customer = Customer::create(['name' => 'Budi Santoso']);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-09-30',
        'status' => 'booking',
    ]);

    config(['services.google_drive.root_folder_id' => 'root-folder']);

    $googleDriveService = Mockery::mock(GoogleDriveService::class);
    foreach ([
        ['Griya Asri', 'root-folder', 'property-folder'],
        ['A', 'property-folder', 'block-folder'],
        ['A-01', 'block-folder', 'lot-folder'],
        ['Budi Santoso', 'lot-folder', 'customer-folder'],
        ['KTP', 'customer-folder', 'document-folder'],
    ] as [$name, $parentId, $folderId]) {
        $googleDriveService->shouldReceive('findOrCreateFolder')
            ->once()
            ->with($name, $parentId)
            ->andReturn([
                'id' => $folderId,
                'name' => $name,
                'mimeType' => 'application/vnd.google-apps.folder',
                'url' => 'https://drive.google.com/folderview?id='.$folderId,
            ]);
    }

    $googleDriveService->shouldReceive('uploadFile')
        ->once()
        ->withArgs(fn (string $path, string $name, string $folderId, string $mimeType): bool => $name === 'KTP_Budi Santoso.pdf'
            && $folderId === 'document-folder'
            && $mimeType === 'application/pdf'
            && is_file($path))
        ->andReturn([
            'id' => 'file-123',
            'name' => 'KTP_Budi Santoso.pdf',
            'mimeType' => 'application/pdf',
            'url' => 'https://drive.google.com/file/d/file-123/view',
        ]);
    $googleDriveService->shouldReceive('getFileUrl')
        ->never();

    $this->app->instance(GoogleDriveService::class, $googleDriveService);
    Queue::fake([UploadGoogleDriveDocument::class]);

    $response = $this->actingAs($user)->post(route('pemberkasan.bookings.documents.store', $booking), [
        'document_type' => 'KTP',
        'document' => UploadedFile::fake()->create('ktp.pdf', 120, 'application/pdf'),
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Dokumen tersimpan di sistem dan sedang menunggu upload ke Google Drive.');

    $document = PemberkasanDocument::query()->where('booking_id', $booking->id)->firstOrFail();

    expect($document->status)->toBe('pending')
        ->and($document->local_path)->not->toBeNull();

    Queue::assertPushed(
        UploadGoogleDriveDocument::class,
        fn (UploadGoogleDriveDocument $job): bool => $job->documentId === $document->id,
    );

    $this->app->make(PemberkasanDocumentService::class)->uploadStoredDocument($document->id);

    $this->assertDatabaseHas('pemberkasan_documents', [
        'booking_id' => $booking->id,
        'document_type' => 'KTP',
        'status' => 'uploaded',
        'drive_file_id' => 'file-123',
    ]);

    $document = $document->fresh();
    $document->update([
        'status' => 'failed',
        'upload_error' => 'Temporary API failure',
    ]);

    $retriedDocument = $this->app->make(PemberkasanDocumentService::class)
        ->retryUpload($document, $user);

    expect($retriedDocument->status)->toBe('pending')
        ->and($retriedDocument->retry_count)->toBe(1)
        ->and($retriedDocument->local_path)->toBe($document->local_path);

    (new UploadGoogleDriveDocument($retriedDocument->id))
        ->failed(new RuntimeException('Google Drive retry failed.'));

    expect($retriedDocument->fresh()->status)->toBe('failed')
        ->and($retriedDocument->fresh()->upload_error)->toBe('Google Drive retry failed.');
});

test('pemberkasan staff can replace an existing customer document in google drive', function () {
    $division = Division::create([
        'name' => 'Pemberkasan',
        'slug' => 'pemberkasan',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_pemberkasan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Griya Asri']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'booked',
    ]);
    $customer = Customer::create(['name' => 'Budi Santoso']);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-09-30',
        'status' => 'booking',
    ]);
    $oldLocalPath = 'pemberkasan/documents/old-ktp.pdf';
    Storage::disk('local')->put($oldLocalPath, 'old file');
    Storage::fake('public');
    $document = PemberkasanDocument::create([
        'booking_id' => $booking->id,
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'document_type' => 'KTP',
        'original_name' => 'old-ktp.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 8,
        'local_path' => $oldLocalPath,
        'drive_file_id' => 'drive-file-123',
        'drive_folder_id' => 'drive-folder-123',
        'drive_url' => 'https://drive.google.com/file/d/drive-file-123/view',
        'status' => 'uploaded',
        'uploaded_at' => now(),
        'uploaded_by' => $user->id,
    ]);
    config(['services.google_drive.root_folder_id' => 'root-folder']);

    $googleDriveService = Mockery::mock(GoogleDriveService::class);
    foreach ([
        ['Griya Asri', 'root-folder', 'property-folder'],
        ['A', 'property-folder', 'block-folder'],
        ['A-01', 'block-folder', 'lot-folder'],
        ['Budi Santoso', 'lot-folder', 'customer-folder'],
        ['KTP', 'customer-folder', 'document-folder'],
    ] as [$name, $parentId, $folderId]) {
        $googleDriveService->shouldReceive('findOrCreateFolder')
            ->once()
            ->with($name, $parentId)
            ->andReturn([
                'id' => $folderId,
                'name' => $name,
                'mimeType' => 'application/vnd.google-apps.folder',
                'url' => 'https://drive.google.com/folderview?id='.$folderId,
            ]);
    }
    $googleDriveService->shouldReceive('updateFile')
        ->once()
        ->withArgs(fn (string $fileId, string $path, string $name, string $mimeType): bool => $fileId === 'drive-file-123'
            && is_file($path)
            && $name === 'KTP_Budi Santoso.pdf'
            && $mimeType === 'application/pdf')
        ->andReturn([
            'id' => 'drive-file-123',
            'name' => 'KTP_Budi Santoso.pdf',
            'mimeType' => 'application/pdf',
            'url' => 'https://drive.google.com/file/d/drive-file-123/view',
        ]);
    $googleDriveService->shouldReceive('getFileUrl')->never();
    $this->app->instance(GoogleDriveService::class, $googleDriveService);
    Queue::fake([UploadGoogleDriveDocument::class]);

    $this->actingAs($user)
        ->put(route('pemberkasan.bookings.update', $booking), [
            'customer_name' => 'Budi Santoso',
            'customer_ktp_file' => UploadedFile::fake()->create('new-ktp.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect(route('pemberkasan.bookings.show', $booking))
        ->assertSessionHas('success', 'Data pemberkasan berhasil diperbarui.');

    $document = $document->fresh();
    expect($document->status)->toBe('pending')
        ->and($document->drive_file_id)->toBe('drive-file-123')
        ->and($document->local_path)->not->toBe($oldLocalPath);
    Queue::assertPushed(
        UploadGoogleDriveDocument::class,
        fn (UploadGoogleDriveDocument $job): bool => $job->documentId === $document->id,
    );

    $this->app->make(PemberkasanDocumentService::class)->uploadStoredDocument($document->id);

    $this->assertDatabaseHas('pemberkasan_documents', [
        'id' => $document->id,
        'status' => 'uploaded',
        'drive_file_id' => 'drive-file-123',
    ]);
    expect(Storage::disk('local')->exists($oldLocalPath))->toBeFalse();
});

test('pemberkasan staff cannot replace a customer document while its upload is pending', function () {
    $division = Division::create([
        'name' => 'Pemberkasan',
        'slug' => 'pemberkasan',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_pemberkasan',
        'division_id' => $division->id,
    ]);
    $property = Property::create(['name' => 'Griya Asri']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'booked',
    ]);
    $customer = Customer::create(['name' => 'Budi Santoso']);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-09-30',
        'status' => 'booking',
    ]);
    $oldLocalPath = 'pemberkasan/documents/pending-ktp.pdf';
    Storage::disk('local')->put($oldLocalPath, 'pending file');
    Storage::fake('public');
    $document = PemberkasanDocument::create([
        'booking_id' => $booking->id,
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'document_type' => 'KTP',
        'original_name' => 'pending-ktp.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 12,
        'local_path' => $oldLocalPath,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->put(route('pemberkasan.bookings.update', $booking), [
            'customer_name' => 'Budi Santoso',
            'customer_ktp_file' => UploadedFile::fake()->create('new-ktp.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('customer_ktp_file');

    $this->assertDatabaseHas('pemberkasan_documents', [
        'id' => $document->id,
        'status' => 'pending',
        'local_path' => $oldLocalPath,
    ]);
    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'ktp_file' => null,
    ]);
    expect(Storage::disk('local')->exists($oldLocalPath))->toBeTrue();
});

test('admin can replace an uploaded document and delete it from Google Drive', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Griya Asri']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'booked',
    ]);
    $customer = Customer::create(['name' => 'Budi Santoso']);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-09-30',
        'status' => 'booking',
    ]);
    $originalPath = 'pemberkasan/documents/original.pdf';
    Storage::disk('local')->put($originalPath, 'original file');
    $document = PemberkasanDocument::create([
        'booking_id' => $booking->id,
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'document_type' => 'KTP',
        'original_name' => 'original.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 13,
        'local_path' => $originalPath,
        'drive_file_id' => 'drive-file-123',
        'drive_folder_id' => 'drive-folder-123',
        'drive_url' => 'https://drive.google.com/file/d/drive-file-123/view',
        'status' => 'uploaded',
        'uploaded_at' => now(),
        'uploaded_by' => $admin->id,
    ]);
    config(['services.google_drive.root_folder_id' => 'root-folder']);

    $googleDriveService = Mockery::mock(GoogleDriveService::class);
    foreach ([
        ['Griya Asri', 'root-folder'],
        ['A', 'property-folder'],
        ['A-01', 'block-folder'],
        ['Budi Santoso', 'lot-folder'],
        ['KTP', 'customer-folder'],
    ] as [$name, $parentId]) {
        $googleDriveService->shouldReceive('findOrCreateFolder')
            ->once()
            ->with($name, $parentId)
            ->andReturn(['id' => 'document-folder']);
    }
    $googleDriveService->shouldReceive('updateFile')
        ->once()
        ->withArgs(fn (string $fileId, string $path, string $name, string $mimeType): bool => $fileId === 'drive-file-123'
            && is_file($path)
            && $name === 'KTP_Budi Santoso.pdf'
            && $mimeType === 'application/pdf')
        ->andReturn([
            'id' => 'drive-file-123',
            'name' => 'KTP_Budi Santoso.pdf',
            'mimeType' => 'application/pdf',
            'url' => 'https://drive.google.com/file/d/drive-file-123/view',
        ]);
    $googleDriveService->shouldReceive('deleteFile')
        ->once()
        ->with('drive-file-123')
        ->andReturnTrue();

    $this->app->instance(GoogleDriveService::class, $googleDriveService);
    Queue::fake([UploadGoogleDriveDocument::class]);

    $replacementResponse = $this->actingAs($admin)
        ->put(route('admin.google-drive.documents.replace', $document), [
            'document' => UploadedFile::fake()->create('replacement.pdf', 64, 'application/pdf'),
        ]);

    $replacementResponse->assertRedirect()
        ->assertSessionHas('success', 'File pengganti disimpan dan sedang menunggu upload ke Google Drive.');
    $document = $document->fresh();
    $replacementPath = $document->replacement_local_path
        ?? throw new RuntimeException('Replacement file should be staged before upload.');

    expect($document->status)->toBe('pending')
        ->and($document->original_name)->toBe('replacement.pdf')
        ->and($replacementPath)->not->toBeNull()
        ->and(Storage::disk('local')->exists($originalPath))->toBeTrue();
    Queue::assertPushed(
        UploadGoogleDriveDocument::class,
        fn (UploadGoogleDriveDocument $job): bool => $job->documentId === $document->id,
    );

    $this->app->make(PemberkasanDocumentService::class)->uploadStoredDocument($document->id);

    $this->assertDatabaseHas('pemberkasan_documents', [
        'id' => $document->id,
        'status' => 'uploaded',
        'drive_file_id' => 'drive-file-123',
        'replacement_local_path' => null,
    ]);
    expect(Storage::disk('local')->exists($originalPath))->toBeFalse()
        ->and(Storage::disk('local')->exists($replacementPath))->toBeTrue();

    $this->delete(route('admin.google-drive.documents.destroy', $document))
        ->assertRedirect()
        ->assertSessionHas('success', 'Dokumen berhasil dihapus dari Google Drive dan aplikasi.');

    $this->assertDatabaseMissing('pemberkasan_documents', ['id' => $document->id]);
    expect(Storage::disk('local')->exists($replacementPath))->toBeFalse();
});

test('admin cannot replace or delete a document while its upload is pending', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Griya Asri']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'booked',
    ]);
    $customer = Customer::create(['name' => 'Budi Santoso']);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-09-30',
        'status' => 'booking',
    ]);
    $document = PemberkasanDocument::create([
        'booking_id' => $booking->id,
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'document_type' => 'KTP',
        'original_name' => 'ktp.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 13,
        'local_path' => 'pemberkasan/documents/ktp.pdf',
        'status' => 'pending',
    ]);
    Storage::disk('local')->put('pemberkasan/documents/ktp.pdf', 'pending file');
    $googleDriveService = Mockery::mock(GoogleDriveService::class);
    $googleDriveService->shouldNotReceive('deleteFile');
    $googleDriveService->shouldNotReceive('updateFile');
    $this->app->instance(GoogleDriveService::class, $googleDriveService);

    $replacementResponse = $this->actingAs($admin)
        ->put(route('admin.google-drive.documents.replace', $document), [
            'document' => UploadedFile::fake()->create('replacement.pdf', 64, 'application/pdf'),
        ]);

    $replacementResponse->assertRedirect()
        ->assertSessionHasErrors('document');
    $this->delete(route('admin.google-drive.documents.destroy', $document))
        ->assertRedirect()
        ->assertSessionHasErrors('document');
    $this->assertModelExists($document);
    expect(Storage::disk('local')->exists('pemberkasan/documents/ktp.pdf'))->toBeTrue();
});

test('users without an assigned pemberkasan division cannot manage documents in the global Google Drive list', function () {
    $user = User::factory()->create(['role' => 'staff_pemberkasan']);
    $property = Property::create(['name' => 'Griya Asri']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $lot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'booked',
    ]);
    $customer = Customer::create(['name' => 'Budi Santoso']);
    $booking = Booking::create([
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'booking_date' => '2026-09-30',
        'status' => 'booking',
    ]);
    $document = PemberkasanDocument::create([
        'booking_id' => $booking->id,
        'property_id' => $property->id,
        'lot_id' => $lot->id,
        'customer_id' => $customer->id,
        'document_type' => 'KTP',
        'original_name' => 'ktp.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 13,
        'status' => 'uploaded',
    ]);

    $this->actingAs($user)
        ->put(route('admin.google-drive.documents.replace', $document), [
            'document' => UploadedFile::fake()->create('replacement.pdf', 64, 'application/pdf'),
        ])
        ->assertForbidden();

    $this->delete(route('admin.google-drive.documents.destroy', $document))
        ->assertForbidden();
});

test('admin can filter the Google Drive customer list and view documents on the customer page', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $property = Property::create(['name' => 'Griya Asri']);
    $otherProperty = Property::create(['name' => 'Taman Indah']);
    $block = Block::create(['property_id' => $property->id, 'name' => 'A']);
    $otherBlock = Block::create(['property_id' => $otherProperty->id, 'name' => 'B']);
    $matchingLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '01',
        'house_price' => 500000000,
        'status' => 'booked',
    ]);
    $otherLot = Lot::create([
        'block_id' => $block->id,
        'lot_number' => '02',
        'house_price' => 500000000,
        'status' => 'booked',
    ]);
    $otherPropertyLot = Lot::create([
        'block_id' => $otherBlock->id,
        'lot_number' => '03',
        'house_price' => 500000000,
        'status' => 'booked',
    ]);
    $customer = Customer::create(['name' => 'Budi Santoso']);
    $otherCustomer = Customer::create(['name' => 'Siti Rahma']);
    $createDocument = function (Lot $lot, Customer $documentCustomer, string $documentType): void {
        $booking = Booking::create([
            'lot_id' => $lot->id,
            'customer_id' => $documentCustomer->id,
            'booking_date' => '2026-09-30',
            'status' => 'booking',
        ]);

        PemberkasanDocument::create([
            'booking_id' => $booking->id,
            'property_id' => $lot->block->property_id,
            'lot_id' => $lot->id,
            'customer_id' => $documentCustomer->id,
            'document_type' => $documentType,
            'original_name' => strtolower($documentType).'.pdf',
            'status' => 'uploaded',
        ]);
    };
    $createDocument($matchingLot, $customer, 'KTP');
    $createDocument($otherLot, $customer, 'KK');
    $createDocument($otherPropertyLot, $customer, 'SIM');
    $createDocument($matchingLot, $otherCustomer, 'Passport');

    $response = $this->actingAs($admin)->get(route('admin.google-drive.documents.index', [
        'property_id' => $property->id,
        'lot_id' => $matchingLot->id,
        'customer_id' => $customer->id,
    ]));

    $response->assertSee('Budi Santoso')
        ->assertDontSee('Siti Rahma')
        ->assertSee('Griya Asri')
        ->assertSee('Blok A')
        ->assertSee('Kavling 01')
        ->assertSee(route('admin.google-drive.documents.customer', $customer));

    $this->actingAs($admin)
        ->get(route('admin.google-drive.documents.index'))
        ->assertSee('Griya Asri')
        ->assertSee('Taman Indah')
        ->assertSee('Blok B')
        ->assertSee('Kavling 02')
        ->assertSee('Kavling 03');

    $this->actingAs($admin)
        ->get(route('admin.google-drive.documents.customer', $customer))
        ->assertSee('KTP')
        ->assertSee('KK')
        ->assertSee('SIM')
        ->assertDontSee('Passport');
});
