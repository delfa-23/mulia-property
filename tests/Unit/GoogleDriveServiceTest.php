<?php

use App\Services\GoogleDriveService;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Resource\Files;
use Google\Service\Exception;

test('replacing a Google Drive file restores it from trash', function () {
    $filePath = tempnam(sys_get_temp_dir(), 'drive-update-');
    file_put_contents($filePath, 'replacement content');

    try {
        $files = Mockery::mock(Files::class);
        $files->shouldReceive('update')
            ->once()
            ->withArgs(fn (string $fileId, DriveFile $metadata, array $options): bool => $fileId === 'drive-file-123'
                && $metadata->getTrashed() === false
                && $options['supportsAllDrives'] === true)
            ->andReturn(new DriveFile([
                'id' => 'drive-file-123',
                'name' => 'KTP_Customer Baru.pdf',
                'mimeType' => 'application/pdf',
            ]));

        $drive = new Drive(new Client);
        $drive->files = $files;

        $service = new GoogleDriveService;
        $driveProperty = new ReflectionProperty(GoogleDriveService::class, 'drive');
        $driveProperty->setValue($service, $drive);

        $result = $service->updateFile(
            'drive-file-123',
            $filePath,
            'KTP_Customer Baru.pdf',
            'application/pdf',
        );
    } finally {
        unlink($filePath);
    }

    expect($result['id'])->toBe('drive-file-123')
        ->and($result['name'])->toBe('KTP_Customer Baru.pdf');
});

test('replacing a permanently deleted Google Drive file uploads a new file', function () {
    $filePath = tempnam(sys_get_temp_dir(), 'drive-replace-');
    file_put_contents($filePath, 'replacement content');

    try {
        $files = Mockery::mock(Files::class);
        $files->shouldReceive('update')
            ->once()
            ->andThrow(new Exception('File not found', 404));
        $files->shouldReceive('create')
            ->once()
            ->withArgs(fn (DriveFile $metadata, array $options): bool => $metadata->getName() === 'KTP_Customer Baru.pdf'
                && $metadata->getParents() === ['document-folder']
                && $options['supportsAllDrives'] === true)
            ->andReturn(new DriveFile([
                'id' => 'new-drive-file-456',
                'name' => 'KTP_Customer Baru.pdf',
                'mimeType' => 'application/pdf',
            ]));

        $drive = new Drive(new Client);
        $drive->files = $files;

        $service = new GoogleDriveService;
        $driveProperty = new ReflectionProperty(GoogleDriveService::class, 'drive');
        $driveProperty->setValue($service, $drive);

        $result = $service->replaceFile(
            'deleted-drive-file-123',
            $filePath,
            'document-folder',
            'KTP_Customer Baru.pdf',
            'application/pdf',
        );
    } finally {
        unlink($filePath);
    }

    expect($result['id'])->toBe('new-drive-file-456')
        ->and($result['name'])->toBe('KTP_Customer Baru.pdf');
});

test('replacing a Google Drive file does not upload a new file for other errors', function () {
    $filePath = tempnam(sys_get_temp_dir(), 'drive-replace-');
    file_put_contents($filePath, 'replacement content');

    try {
        $files = Mockery::mock(Files::class);
        $files->shouldReceive('update')
            ->once()
            ->andThrow(new Exception('Permission denied', 403));
        $files->shouldNotReceive('create');

        $drive = new Drive(new Client);
        $drive->files = $files;

        $service = new GoogleDriveService;
        $driveProperty = new ReflectionProperty(GoogleDriveService::class, 'drive');
        $driveProperty->setValue($service, $drive);

        expect(fn () => $service->replaceFile(
            'drive-file-123',
            $filePath,
            'document-folder',
            'KTP_Customer Baru.pdf',
            'application/pdf',
        ))->toThrow(Exception::class, 'Permission denied');
    } finally {
        unlink($filePath);
    }
});

test('deleting an already missing Google Drive file is treated as already deleted', function () {
    $files = Mockery::mock(Files::class);
    $files->shouldReceive('delete')
        ->once()
        ->with('deleted-drive-file-123', ['supportsAllDrives' => true])
        ->andThrow(new Exception('File not found', 404));

    $drive = new Drive(new Client);
    $drive->files = $files;

    $service = new GoogleDriveService;
    $driveProperty = new ReflectionProperty(GoogleDriveService::class, 'drive');
    $driveProperty->setValue($service, $drive);

    expect($service->deleteFileIfExists('deleted-drive-file-123'))->toBeFalse();
});

test('deleting a Google Drive file rethrows errors other than not found', function () {
    $files = Mockery::mock(Files::class);
    $files->shouldReceive('delete')
        ->once()
        ->andThrow(new Exception('Permission denied', 403));

    $drive = new Drive(new Client);
    $drive->files = $files;

    $service = new GoogleDriveService;
    $driveProperty = new ReflectionProperty(GoogleDriveService::class, 'drive');
    $driveProperty->setValue($service, $drive);

    expect(fn () => $service->deleteFileIfExists('drive-file-123'))
        ->toThrow(Exception::class, 'Permission denied');
});
