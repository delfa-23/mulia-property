<?php

namespace App\Console\Commands;

use App\Services\GoogleDriveService;
use Illuminate\Console\Command;
use Throwable;

class TestGoogleDrive extends Command
{
    protected $signature = 'google-drive:test';

    protected $description = 'Test connection to Google Drive';

    public function handle(GoogleDriveService $googleDriveService): int
    {
        try {
            $folderId = $googleDriveService->configuredRootFolderId();

            if ($folderId === null) {
                $this->error('GOOGLE_DRIVE_ROOT_FOLDER_ID must be configured.');

                return self::FAILURE;
            }

            $folder = $googleDriveService->getFolderDetails($folderId);

            $this->info('=================================');
            $this->info(' GOOGLE DRIVE CONNECTION SUCCESS ');
            $this->info('=================================');

            $this->line('Folder Name : '.$folder['name']);
            $this->line('Folder ID   : '.$folder['id']);
            $this->line('Mime Type   : '.$folder['mimeType']);
            $this->line('Shared Drive: '.($folder['driveId'] ?: 'Tidak (My Drive)'));

            return self::SUCCESS;

        } catch (Throwable $exception) {
            $this->error('GOOGLE DRIVE CONNECTION FAILED');
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
