<?php

namespace App\Jobs;

use App\Models\PemberkasanDocument;
use App\Services\PemberkasanDocumentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class UploadGoogleDriveDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 90];

    public int $timeout = 60;

    public function __construct(public int $documentId) {}

    public function handle(PemberkasanDocumentService $documentService): void
    {
        $documentService->uploadStoredDocument($this->documentId);
    }

    public function failed(?Throwable $exception): void
    {
        $document = PemberkasanDocument::query()->find($this->documentId);

        if (! $document) {
            return;
        }

        if ($document->status === 'uploaded') {
            return;
        }

        $document->status = 'failed';
        $document->upload_error ??= $exception?->getMessage() ?? 'Google Drive upload failed after all retry attempts.';
        $document->save();

        Log::error('Google Drive upload job failed permanently', [
            'document_id' => $document->id,
            'max_attempts' => $this->tries,
            'error' => $document->upload_error,
        ]);
    }
}
