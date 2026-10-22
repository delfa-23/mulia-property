<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PemberkasanDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PemberkasanDocumentService
{
    public const BI_CHECKING_DOCUMENT_TYPES = [
        'KTP Pemohon',
        'KTP Pasangan',
    ];

    public const LEGACY_BI_CHECKING_DOCUMENT_TYPES = [
        'KTP',
    ];

    public const ADDITIONAL_MARKETING_DOCUMENT_TYPES = [
        'Kartu Keluarga',
        'Buku Nikah',
        'NPWP',
        'SK Kerja/SK Usaha Suami',
        'SK Kerja/SK Usaha Istri',
        'Slip Gaji/SK Penghasilan Suami',
        'Slip Gaji/SK Penghasilan Istri',
        'Rek Koran Pemohon/Pasangan',
        'Foto Tempat Kerja/Usaha Pemohon',
        'Foto Tempat Kerja Pasangan (Jika Bekerja/Usaha)',
    ];

    public const PROPERTY_DOCUMENT_TYPES = [
        'SPR',
        'SHGB',
        'PBG',
    ];

    public const LEGACY_ADDITIONAL_DOCUMENT_TYPES = [
        'KK',
        'Dokumen Pemesanan',
        'Surat Pemesanan',
    ];

    public const BOOKING_DOCUMENT_TYPES = [
        'KTP Pemohon',
        'KTP Pasangan',
        'Kartu Keluarga',
        'Buku Nikah',
        'NPWP',
        'SK Kerja/SK Usaha Suami',
        'SK Kerja/SK Usaha Istri',
        'Slip Gaji/SK Penghasilan Suami',
        'Slip Gaji/SK Penghasilan Istri',
        'Rek Koran Pemohon/Pasangan',
        'Foto Tempat Kerja/Usaha Pemohon',
        'Foto Tempat Kerja Pasangan (Jika Bekerja/Usaha)',
        'SPR',
        'SHGB',
        'PBG',
        'KTP',
        'KK',
        'Dokumen Pemesanan',
        'Surat Pemesanan',
    ];

    public const MARKETING_DOCUMENT_TYPES = [
        'KTP Pemohon',
        'KTP Pasangan',
        'Kartu Keluarga',
        'Buku Nikah',
        'NPWP',
        'SK Kerja/SK Usaha Suami',
        'SK Kerja/SK Usaha Istri',
        'Slip Gaji/SK Penghasilan Suami',
        'Slip Gaji/SK Penghasilan Istri',
        'Rek Koran Pemohon/Pasangan',
        'Foto Tempat Kerja/Usaha Pemohon',
        'Foto Tempat Kerja Pasangan (Jika Bekerja/Usaha)',
    ];

    public function __construct(protected GoogleDriveService $googleDriveService) {}

    public function uploadForBooking(
        Booking $booking,
        UploadedFile $uploadedFile,
        string $documentType,
        ?User $user = null,
        string $documentCategory = 'standard',
    ): PemberkasanDocument {
        $documentType = trim($documentType);

        if ($documentType === '') {
            throw new \InvalidArgumentException('Jenis dokumen wajib dipilih.');
        }

        if (! in_array($documentCategory, ['standard', 'other'], true)) {
            throw new \InvalidArgumentException('Kategori dokumen tidak valid.');
        }

        $requiredCategory = $this->requiredCategoryFor($documentType);
        if ($requiredCategory !== null && $documentCategory !== $requiredCategory) {
            throw new \InvalidArgumentException('Kategori dokumen tidak sesuai dengan jenis berkas.');
        }

        $this->archiveCancelledBookingDocumentsForLot($booking);

        $document = $this->resolveDocument($booking, $documentType, $documentCategory);

        if ($document->exists && $document->status === 'archived') {
            throw new RuntimeException('Dokumen ini sudah diarsipkan dan tidak dapat diubah.');
        }

        if ($document->exists && in_array($document->status, ['pending', 'uploading'], true)) {
            throw new RuntimeException('Dokumen ini masih menunggu atau sedang diproses oleh Google Drive.');
        }

        $localPath = $this->storeLocalFile($uploadedFile);

        $document->booking_id = $booking->id;
        $document->property_id = $booking->lot?->block?->property?->id;
        $document->lot_id = $booking->lot?->id;
        $document->customer_id = $booking->customer?->id;
        $document->document_type = $documentType;
        $document->document_category = $documentCategory;
        $document->original_name = $uploadedFile->getClientOriginalName();
        $document->mime_type = $uploadedFile->getMimeType() ?: 'application/octet-stream';
        $document->file_size = $uploadedFile->getSize();
        $document->local_path = $localPath;
        $document->status = 'pending';
        $document->uploaded_by = $user?->id ?? $document->uploaded_by;
        $document->upload_error = null;
        $document->uploaded_at = null;
        $document->save();

        return $this->uploadImmediately($document);
    }

    public function retryUpload(PemberkasanDocument $document, ?User $user = null): PemberkasanDocument
    {
        if ($document->status === 'archived') {
            throw new RuntimeException('Dokumen ini sudah diarsipkan dan tidak dapat diunggah ulang.');
        }

        $uploadPath = $document->replacement_local_path ?: $document->local_path;

        if (! $uploadPath || ! Storage::disk('local')->exists($uploadPath)) {
            throw new RuntimeException('File lokal dokumen tidak ditemukan. Silakan upload ulang file yang baru.');
        }

        if (in_array($document->status, ['pending', 'uploading'], true)) {
            throw new RuntimeException('Dokumen ini masih menunggu atau sedang diproses oleh Google Drive.');
        }

        $document->status = 'pending';
        $document->upload_error = null;
        $document->retry_count++;
        $document->uploaded_by = $user?->id ?? $document->uploaded_by;
        $document->save();

        return $this->uploadImmediately($document);
    }

    public function replaceDocument(
        PemberkasanDocument $document,
        UploadedFile $uploadedFile,
        ?User $user = null,
    ): PemberkasanDocument {
        if ($document->status === 'archived') {
            throw new RuntimeException('Dokumen ini sudah diarsipkan dan tidak dapat diganti.');
        }

        if (in_array($document->status, ['pending', 'uploading'], true)) {
            throw new RuntimeException('Dokumen sedang menunggu atau diproses. Tunggu sampai proses selesai sebelum mengganti file.');
        }

        $replacementPath = $this->storeLocalFile($uploadedFile);
        $previousReplacementPath = $document->replacement_local_path;

        $document->replacement_local_path = $replacementPath;
        $document->original_name = $uploadedFile->getClientOriginalName();
        $document->mime_type = $uploadedFile->getMimeType() ?: 'application/octet-stream';
        $document->file_size = $uploadedFile->getSize();
        $document->status = 'pending';
        $document->upload_error = null;
        $document->uploaded_by = $user?->id ?? $document->uploaded_by;
        $document->save();

        if (
            $previousReplacementPath &&
            $previousReplacementPath !== $replacementPath &&
            Storage::disk('local')->exists($previousReplacementPath)
        ) {
            Storage::disk('local')->delete($previousReplacementPath);
        }

        return $this->uploadImmediately($document);
    }

    private function uploadImmediately(PemberkasanDocument $document): PemberkasanDocument
    {
        try {
            $this->uploadStoredDocument($document->id);
        } catch (\Throwable $exception) {
            $document->refresh();

            if ($document->status !== 'archived') {
                $document->status = 'failed';
                $document->save();
            }

            throw new RuntimeException(
                $document->upload_error ?: $exception->getMessage(),
                0,
                $exception,
            );
        }

        return $document->fresh();
    }

    public function deleteDocument(PemberkasanDocument $document): void
    {
        if ($document->status === 'archived') {
            throw new RuntimeException('Dokumen arsip dipertahankan sebagai riwayat dan tidak dapat dihapus dari aplikasi.');
        }

        if (in_array($document->status, ['pending', 'uploading'], true)) {
            throw new RuntimeException('Dokumen sedang menunggu atau diproses. Tunggu sampai proses selesai sebelum menghapusnya.');
        }

        if ($document->drive_file_id) {
            $this->googleDriveService->deleteFile($document->drive_file_id);
        }

        foreach ([$document->local_path, $document->replacement_local_path] as $localPath) {
            if ($localPath && Storage::disk('local')->exists($localPath)) {
                Storage::disk('local')->delete($localPath);
            }
        }

        $document->delete();
    }

    public function deleteBookingDocuments(Booking $booking): void
    {
        $documents = PemberkasanDocument::query()
            ->where('booking_id', $booking->id)
            ->orderBy('id')
            ->get();

        if ($documents->contains(fn (PemberkasanDocument $document): bool => in_array($document->status, ['pending', 'uploading'], true))) {
            throw new RuntimeException('Booking tidak dapat dihapus selama dokumennya masih menunggu atau sedang diunggah ke Google Drive.');
        }

        foreach ($documents as $document) {
            if ($document->drive_file_id) {
                $this->googleDriveService->deleteFileIfExists($document->drive_file_id);
            }
        }

        foreach ($documents as $document) {
            foreach ([$document->local_path, $document->replacement_local_path] as $localPath) {
                if ($localPath && Storage::disk('local')->exists($localPath)) {
                    Storage::disk('local')->delete($localPath);
                }
            }
        }
    }

    public function uploadStoredDocument(int $documentId): void
    {
        $document = PemberkasanDocument::query()
            ->with(['booking.customer', 'booking.lot.block.property'])
            ->findOrFail($documentId);

        if ($document->status === 'archived') {
            return;
        }

        $uploadPath = $document->replacement_local_path ?: $document->local_path;

        if (! $uploadPath || ! Storage::disk('local')->exists($uploadPath)) {
            throw new RuntimeException('File lokal dokumen tidak ditemukan. Silakan upload ulang file yang baru.');
        }

        $document->status = 'uploading';
        $document->upload_error = null;
        $document->save();

        $booking = $document->booking;
        try {
            $documentFolder = $this->ensureDocumentFolder($booking, $document->document_type);
            $driveFileName = $this->buildDocumentFileName(
                $document->document_type,
                $booking->customer?->name ?? 'Customer',
                $document->original_name,
            );
            $localFilePath = Storage::disk('local')->path($uploadPath);
            $driveResult = $document->drive_file_id
                ? $this->googleDriveService->replaceFile(
                    $document->drive_file_id,
                    $localFilePath,
                    $documentFolder['id'],
                    $driveFileName,
                    $document->mime_type,
                )
                : $this->googleDriveService->uploadFile(
                    $localFilePath,
                    $driveFileName,
                    $documentFolder['id'],
                    $document->mime_type,
                );
        } catch (\Throwable $exception) {
            $document->refresh();

            if ($document->status !== 'archived') {
                $document->status = 'pending';
                $document->upload_error = $this->googleDriveService->formatUploadError($exception);
                $document->save();
            }

            throw $exception;
        }

        $document->refresh();

        if ($document->status === 'archived') {
            $this->googleDriveService->deleteFile($driveResult['id']);

            return;
        }

        $previousLocalPath = $document->local_path;
        $document->local_path = $uploadPath;
        $document->replacement_local_path = null;
        $document->drive_file_id = $driveResult['id'];
        $document->drive_folder_id = $documentFolder['id'];
        $document->drive_url = $driveResult['url'] ?? $this->googleDriveService->getFileUrl($driveResult['id']);
        $document->status = 'uploaded';
        $document->uploaded_at = now();
        $document->upload_error = null;
        $document->save();

        if (
            $previousLocalPath &&
            $previousLocalPath !== $uploadPath &&
            Storage::disk('local')->exists($previousLocalPath)
        ) {
            Storage::disk('local')->delete($previousLocalPath);
        }
    }

    /**
     * @param  array<int, string>  $documentTypes
     * @return array<int, PemberkasanDocument>
     */
    public function syncCustomerDocuments(Booking $booking, array $documentTypes, bool $replaceExisting = false): array
    {
        $documentMap = [
            'ktp_file' => ['KTP', 'KTP'],
            'kk_file' => ['KK', 'KK'],
            'npwp_file' => ['NPWP', 'NPWP'],
            'booking_form_file' => ['Surat Pemesanan', 'Dokumen Pemesanan'],
        ];

        $results = [];

        foreach ($documentMap as $field => [$documentType, $customerFileLabel]) {
            if (! in_array($customerFileLabel, $documentTypes, true)) {
                continue;
            }

            $existingDocument = PemberkasanDocument::query()
                ->where('booking_id', $booking->id)
                ->where('document_category', $this->requiredCategoryFor($documentType) ?? 'standard')
                ->where('document_type', $documentType)
                ->first();

            if ($existingDocument && ! $replaceExisting) {
                continue;
            }

            $path = $booking->customer?->{$field};

            if (! $path || ! Storage::disk('public')->exists($path)) {
                continue;
            }

            $uploadedFile = new UploadedFile(
                Storage::disk('public')->path($path),
                basename($path),
                mime_content_type(Storage::disk('public')->path($path)) ?: 'application/octet-stream',
                null,
                true,
            );

            $results[] = $this->uploadForBooking(
                $booking,
                $uploadedFile,
                $documentType,
                auth()->user(),
                $this->requiredCategoryFor($documentType) ?? 'standard',
            );
        }

        return $results;
    }

    private function resolveDocument(
        Booking $booking,
        string $documentType,
        string $documentCategory,
    ): PemberkasanDocument {
        return PemberkasanDocument::query()
            ->where('booking_id', $booking->id)
            ->where('document_category', $documentCategory)
            ->where('document_type', $documentType)
            ->firstOrNew();
    }

    private function archiveCancelledBookingDocumentsForLot(Booking $booking): void
    {
        if (! $booking->lot_id) {
            return;
        }

        $documents = PemberkasanDocument::query()
            ->where('booking_id', '!=', $booking->id)
            ->where('status', '!=', 'archived')
            ->whereHas('booking', fn ($query) => $query
                ->where('lot_id', $booking->lot_id)
                ->where('status', 'cancelled'))
            ->orderBy('id')
            ->get();

        foreach ($documents as $document) {
            if ($document->drive_file_id) {
                $this->googleDriveService->deleteFile($document->drive_file_id);
            }

            $document->status = 'archived';
            $document->save();
        }
    }

    private function storeLocalFile(UploadedFile $uploadedFile): string
    {
        $extension = strtolower($uploadedFile->getClientOriginalExtension() ?: 'bin');
        $path = $uploadedFile->storeAs('pemberkasan/documents', Str::uuid().'.'.$extension, 'local');

        if (! is_string($path)) {
            throw new RuntimeException('Dokumen tidak dapat disimpan ke penyimpanan lokal.');
        }

        return $path;
    }

    private function ensureDocumentFolder(Booking $booking, string $documentType): array
    {
        $rootFolderId = $this->googleDriveService->configuredRootFolderId();
        $rootFolder = $rootFolderId
            ? ['id' => $rootFolderId, 'name' => config('services.google_drive.root_folder_name', 'MULIA PROPERTY')]
            : $this->googleDriveService->findOrCreateFolder(config('services.google_drive.root_folder_name', 'MULIA PROPERTY'));

        $propertyName = $booking->lot?->block?->property?->name ?? 'Perumahan';
        $propertyFolder = $this->googleDriveService->findOrCreateFolder($propertyName, $rootFolder['id']);

        $blockName = $booking->lot?->block?->name ?? 'Block';
        $lotNumber = $booking->lot?->lot_number ?? 'Kavling';
        $blockFolder = $this->googleDriveService->findOrCreateFolder($blockName, $propertyFolder['id']);
        $lotFolderName = str_starts_with($lotNumber, $blockName.'-')
            ? $lotNumber
            : $blockName.'-'.$lotNumber;
        $lotFolder = $this->googleDriveService->findOrCreateFolder($lotFolderName, $blockFolder['id']);

        $customerName = $booking->customer?->name ?? 'Customer';
        $customerFolder = $this->googleDriveService->findOrCreateFolder($customerName, $lotFolder['id']);
        $categoryName = $this->documentCategory($documentType);

        return $this->googleDriveService->findOrCreateFolder($categoryName, $customerFolder['id']);
    }

    private function documentCategory(string $documentType): string
    {
        $normalizedType = Str::lower(trim($documentType));

        return match (true) {
            str_starts_with($normalizedType, 'ktp') => 'KTP',
            $normalizedType === 'kk' || str_contains($normalizedType, 'kartu keluarga') => 'KK',
            $normalizedType === 'npwp' => 'NPWP',
            $normalizedType === 'sp3' => 'SP3',
            in_array($normalizedType, ['dokumen pemesanan', 'surat pemesanan'], true) => 'DOKUMEN PEMESANAN',
            in_array($normalizedType, ['spr', 'shgb', 'pbg'], true) => 'DOKUMEN PROPERTI',
            str_contains($normalizedType, 'akad') => 'DOKUMEN AKAD',
            default => 'DOKUMEN LAINNYA',
        };
    }

    private function requiredCategoryFor(string $documentType): ?string
    {
        if (
            in_array($documentType, self::BI_CHECKING_DOCUMENT_TYPES, true) ||
            in_array($documentType, self::LEGACY_BI_CHECKING_DOCUMENT_TYPES, true)
        ) {
            return 'standard';
        }

        if (
            in_array($documentType, self::ADDITIONAL_MARKETING_DOCUMENT_TYPES, true) ||
            in_array($documentType, self::PROPERTY_DOCUMENT_TYPES, true) ||
            in_array($documentType, self::LEGACY_ADDITIONAL_DOCUMENT_TYPES, true)
        ) {
            return 'other';
        }

        return null;
    }

    private function buildDocumentFileName(string $documentType, string $customerName, string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: 'pdf');

        return $this->buildGoogleDriveFileName($documentType, $customerName).'.'.$extension;
    }

    private function buildGoogleDriveFileName(string $documentType, string $customerName): string
    {
        $documentTypeLabel = match (Str::lower(trim($documentType))) {
            'ktp' => 'KTP',
            'kk' => 'KK',
            'npwp' => 'NPWP',
            'sp3' => 'SP3',
            default => $documentType,
        };

        $cleanDocumentType = Str::of($documentTypeLabel)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9]+/', ' ')
            ->trim()
            ->replaceMatches('/\s+/', ' ')
            ->title()
            ->toString();

        $cleanCustomer = Str::of($customerName)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9]+/', ' ')
            ->trim()
            ->replaceMatches('/\s+/', ' ')
            ->title()
            ->toString();

        $documentLabel = $cleanDocumentType !== '' ? $cleanDocumentType : 'Dokumen';
        $customerLabel = $cleanCustomer !== '' ? $cleanCustomer : 'Customer';

        return $documentLabel.'_'.$customerLabel;
    }
}
