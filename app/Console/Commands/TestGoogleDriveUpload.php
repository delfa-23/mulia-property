<?php

namespace App\Console\Commands;

use App\Services\GoogleDriveService;
use Illuminate\Console\Command;

class TestGoogleDriveUpload extends Command
{
    protected $signature = 'google-drive:test-upload {--delete-after : Delete the test file after upload}';

    protected $description = 'Create a test folder and upload a temporary file to Google Drive';

    public function handle(GoogleDriveService $googleDriveService): int
    {
        try {
            $rootFolderId = $googleDriveService->configuredRootFolderId();
            $rootFolder = $rootFolderId
                ? ['id' => $rootFolderId]
                : $googleDriveService->findOrCreateFolder(config('services.google_drive.root_folder_name', 'MULIA PROPERTY'));

            $folderName = 'Drive Upload Test - '.now()->format('YmdHis');
            $folder = $googleDriveService->findOrCreateFolder($folderName, $rootFolder['id']);

            $tempFile = tempnam(sys_get_temp_dir(), 'gdrive-test');
            file_put_contents($tempFile, 'Google Drive upload test');

            $uploaded = $googleDriveService->uploadFile($tempFile, 'drive-upload-test.txt', $folder['id'], 'text/plain');

            $this->info('Folder ID : '.$folder['id']);
            $this->info('Folder URL: '.$folder['url'] ?? '');
            $this->info('File ID  : '.$uploaded['id']);
            $this->info('File URL : '.$uploaded['url']);

            if ($this->option('delete-after')) {
                $googleDriveService->deleteFile($uploaded['id']);
                $this->info('Test file deleted successfully.');
            }

            unlink($tempFile);

            return self::SUCCESS;
        } catch (\Throwable $throwable) {
            $this->error('Google Drive upload test failed: '.$throwable->getMessage());

            return self::FAILURE;
        }
    }
}
