<?php

namespace App\Services;

use App\Models\GoogleDriveToken;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use RuntimeException;

class GoogleDriveService
{
    private ?Client $client = null;

    private ?Drive $drive = null;

    private ?string $sharedDriveId = null;

    private bool $sharedDriveResolved = false;

    public function authorizationUrl(string $state): string
    {
        $client = $this->oauthClient();
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setLoginHint($this->targetAccountEmail());
        $client->setState($state);

        return $client->createAuthUrl();
    }

    public function storeAuthorizationCode(string $authorizationCode): void
    {
        $client = $this->oauthClient();
        $token = $client->fetchAccessTokenWithAuthCode($authorizationCode);

        if (isset($token['error'])) {
            throw new RuntimeException('Google OAuth token exchange failed: '.($token['error_description'] ?? $token['error']));
        }

        if (! is_string($token['id_token'] ?? null)) {
            throw new RuntimeException('Google did not return a verifiable account identity. Connect the target Google account again.');
        }

        $identity = $client->verifyIdToken($token['id_token']);
        $accountEmail = is_array($identity) && ($identity['email_verified'] ?? false) === true
            ? ($identity['email'] ?? null)
            : null;

        if (! is_string($accountEmail) || ! $this->isTargetAccountEmail($accountEmail)) {
            throw new RuntimeException('Connect Google Drive using the configured account: '.$this->targetAccountEmail());
        }

        $storedToken = GoogleDriveToken::query()->find(1);

        if (
            ! isset($token['refresh_token'])
            && $storedToken?->account_email
            && $this->isTargetAccountEmail($storedToken->account_email)
            && is_array($storedToken->token)
        ) {
            $token['refresh_token'] = $storedToken->token['refresh_token'] ?? null;
        }

        if (! is_string($token['refresh_token'] ?? null) || $token['refresh_token'] === '') {
            throw new RuntimeException('Google did not provide a refresh token. Revoke the app access in Google Account settings and connect again.');
        }

        $storedToken ??= new GoogleDriveToken;
        $storedToken->id = 1;
        $storedToken->token = $token;
        $storedToken->account_email = $accountEmail;
        $storedToken->save();
    }

    public function isTargetAccountEmail(string $email): bool
    {
        return mb_strtolower(trim($email)) === mb_strtolower($this->targetAccountEmail());
    }

    public function findFolder(string $name, ?string $parentId = null): ?array
    {
        $query = "name = '".str_replace("'", "\\'", $name)."' and mimeType = 'application/vnd.google-apps.folder' and trashed = false";

        if ($parentId !== null && $parentId !== '') {
            $query .= " and '".$parentId."' in parents";
        }

        $response = $this->drive()->files->listFiles(array_merge([
            'q' => $query,
            'spaces' => 'drive',
            'fields' => 'files(id,name,mimeType,webViewLink)',
            'pageSize' => 10,
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ], $this->sharedDriveListOptions()));

        $folder = $response->getFiles()[0] ?? null;

        if (! $folder) {
            return null;
        }

        return [
            'id' => $folder->getId(),
            'name' => $folder->getName(),
            'mimeType' => $folder->getMimeType(),
            'url' => $this->getFileUrl($folder->getId()),
        ];
    }

    public function createFolder(string $name, ?string $parentId = null): array
    {
        $metadata = new DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);

        if ($parentId !== null && $parentId !== '') {
            $metadata->setParents([$parentId]);
        }

        $folder = $this->drive()->files->create($metadata, [
            'fields' => 'id,name,mimeType,webViewLink',
            'supportsAllDrives' => true,
        ]);

        return [
            'id' => $folder->getId(),
            'name' => $folder->getName(),
            'mimeType' => $folder->getMimeType(),
            'url' => $this->getFileUrl($folder->getId()),
        ];
    }

    public function findOrCreateFolder(string $name, ?string $parentId = null): array
    {
        $folder = $this->findFolder($name, $parentId);

        return $folder ?? $this->createFolder($name, $parentId);
    }

    public function uploadFile(string $filePath, string $fileName, ?string $folderId = null, ?string $mimeType = null): array
    {
        if (! is_file($filePath)) {
            throw new RuntimeException('File lokal tidak ditemukan: '.$filePath);
        }

        $metadata = new DriveFile([
            'name' => $this->sanitizeFileName($fileName),
            'parents' => $folderId ? [$folderId] : [],
        ]);

        $result = $this->drive()->files->create($metadata, [
            'data' => file_get_contents($filePath),
            'mimeType' => $mimeType ?: mime_content_type($filePath),
            'uploadType' => 'multipart',
            'fields' => 'id,name,mimeType,webViewLink',
            'supportsAllDrives' => true,
        ]);

        return [
            'id' => $result->getId(),
            'name' => $result->getName(),
            'mimeType' => $result->getMimeType(),
            'url' => $this->getFileUrl($result->getId()),
        ];
    }

    public function deleteFile(string $fileId): bool
    {
        $this->drive()->files->delete($fileId, [
            'supportsAllDrives' => true,
        ]);

        return true;
    }

    public function updateFile(string $fileId, string $filePath, ?string $fileName = null, ?string $mimeType = null): array
    {
        if (! is_file($filePath)) {
            throw new RuntimeException('File lokal tidak ditemukan untuk update: '.$filePath);
        }

        $metadata = new DriveFile([
            'name' => $this->sanitizeFileName($fileName ?: basename($filePath)),
        ]);

        $result = $this->drive()->files->update($fileId, $metadata, [
            'data' => file_get_contents($filePath),
            'mimeType' => $mimeType ?: mime_content_type($filePath),
            'uploadType' => 'multipart',
            'fields' => 'id,name,mimeType,webViewLink',
            'supportsAllDrives' => true,
        ]);

        return [
            'id' => $result->getId(),
            'name' => $result->getName(),
            'mimeType' => $result->getMimeType(),
            'url' => $this->getFileUrl($result->getId()),
        ];
    }

    public function getFileUrl(string $fileId): string
    {
        return 'https://drive.google.com/file/d/'.$fileId.'/view';
    }

    /** @return array{id: string, name: string, mimeType: string, driveId: string|null} */
    public function getFolderDetails(string $folderId): array
    {
        $folder = $this->drive()->files->get($folderId, [
            'fields' => 'id,name,mimeType,driveId',
            'supportsAllDrives' => true,
        ]);

        return [
            'id' => $folder->getId(),
            'name' => $folder->getName(),
            'mimeType' => $folder->getMimeType(),
            'driveId' => $folder->getDriveId(),
        ];
    }

    public function formatUploadError(\Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'storageQuotaExceeded') || str_contains($message, 'Service Accounts do not have storage quota')) {
            return 'Upload ke Google Drive gagal karena akun Google yang terhubung tidak memiliki quota penyimpanan. Gunakan Shared Drive atau kosongkan quota akun Google tersebut.';
        }

        if (str_contains($message, 'notFound')) {
            return 'Folder Google Drive yang dipakai tidak ditemukan atau tidak dapat diakses. Pastikan folder root benar dan akun Google yang terhubung memiliki izin akses.';
        }

        return $message;
    }

    private function drive(): Drive
    {
        if (! $this->drive) {
            $this->drive = new Drive($this->client());
        }

        return $this->drive;
    }

    private function client(): Client
    {
        if (! $this->client) {
            $storedToken = GoogleDriveToken::query()->find(1);

            if (! is_array($storedToken?->token)) {
                throw new RuntimeException('Google Drive is not connected. An administrator must authorize the Google account first.');
            }

            if (! $storedToken->account_email || ! $this->isTargetAccountEmail($storedToken->account_email)) {
                throw new RuntimeException('The saved Google Drive connection is not verified for '.$this->targetAccountEmail().'. An administrator must connect the configured account.');
            }

            $client = $this->oauthClient();
            $client->setAccessToken($storedToken->token);

            if ($client->isAccessTokenExpired()) {
                $refreshToken = $client->getRefreshToken();

                if (! is_string($refreshToken) || $refreshToken === '') {
                    throw new RuntimeException('Google Drive authorization has expired. An administrator must reconnect the Google account.');
                }

                $refreshedToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);

                if (isset($refreshedToken['error'])) {
                    throw new RuntimeException('Google OAuth token refresh failed: '.($refreshedToken['error_description'] ?? $refreshedToken['error']));
                }

                $refreshedToken['refresh_token'] ??= $refreshToken;
                $client->setAccessToken($refreshedToken);

                $storedToken->token = $refreshedToken;
                $storedToken->save();
            }

            $this->client = $client;
        }

        return $this->client;
    }

    private function oauthClient(): Client
    {
        $clientId = config('services.google_drive.client_id');
        $clientSecret = config('services.google_drive.client_secret');
        $redirectUri = config('services.google_drive.redirect_uri');

        if (! is_string($clientId) || $clientId === ''
            || ! is_string($clientSecret) || $clientSecret === ''
            || ! is_string($redirectUri) || $redirectUri === '') {
            throw new RuntimeException('Google Drive OAuth client ID, client secret, and redirect URI must be configured.');
        }

        $client = new Client;
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($redirectUri);
        $client->addScope(['openid', 'email', Drive::DRIVE]);
        $client->setApplicationName(config('app.name', 'Mulia Property'));

        return $client;
    }

    private function targetAccountEmail(): string
    {
        $accountEmail = config('services.google_drive.account_email');

        if (! is_string($accountEmail) || filter_var($accountEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('GOOGLE_DRIVE_ACCOUNT_EMAIL must be set to the Google account that owns the uploaded files.');
        }

        return $accountEmail;
    }

    public function configuredRootFolderId(): ?string
    {
        $configuredValue = config('services.google_drive.root_folder_id');

        if (! is_string($configuredValue) || trim($configuredValue) === '') {
            return null;
        }

        $configuredValue = trim($configuredValue);

        if (preg_match('/^[A-Za-z0-9_-]+$/', $configuredValue) === 1) {
            return $configuredValue;
        }

        $urlParts = parse_url($configuredValue);
        $host = is_array($urlParts) ? strtolower($urlParts['host'] ?? '') : '';
        $path = is_array($urlParts) ? ($urlParts['path'] ?? '') : '';

        if (
            is_array($urlParts) &&
            ($urlParts['scheme'] ?? null) === 'https' &&
            $host === 'drive.google.com' &&
            preg_match('#/folders/([A-Za-z0-9_-]+)/?$#', $path, $matches) === 1
        ) {
            return $matches[1];
        }

        throw new RuntimeException('GOOGLE_DRIVE_ROOT_FOLDER_ID must be a Google Drive folder ID or a folder URL.');
    }

    /** @return array<string, mixed> */
    private function sharedDriveListOptions(): array
    {
        $sharedDriveId = $this->resolveSharedDriveId();

        if ($sharedDriveId === null) {
            return [];
        }

        return [
            'corpora' => 'drive',
            'driveId' => $sharedDriveId,
        ];
    }

    private function resolveSharedDriveId(): ?string
    {
        if ($this->sharedDriveResolved) {
            return $this->sharedDriveId;
        }

        $this->sharedDriveResolved = true;
        $configuredDriveId = config('services.google_drive.shared_drive_id');

        if (is_string($configuredDriveId) && $configuredDriveId !== '') {
            return $this->sharedDriveId = $configuredDriveId;
        }

        $rootFolderId = $this->configuredRootFolderId();

        if ($rootFolderId === null) {
            return null;
        }

        $rootFolder = $this->drive()->files->get($rootFolderId, [
            'fields' => 'id,driveId',
            'supportsAllDrives' => true,
        ]);

        $driveId = $rootFolder->getDriveId();

        return $this->sharedDriveId = is_string($driveId) && $driveId !== '' ? $driveId : null;
    }

    private function sanitizeFileName(string $fileName): string
    {
        if ($fileName === '') {
            return 'dokumen.pdf';
        }

        $pathInfo = pathinfo($fileName);
        $baseName = preg_replace('/[^A-Za-z0-9._-]+/', ' ', $pathInfo['filename'] ?? $fileName);
        $extension = strtolower($pathInfo['extension'] ?? 'pdf');

        $sanitized = trim(preg_replace('/\s+/', ' ', (string) $baseName));

        return $sanitized === '' ? 'dokumen' : $sanitized.'.'.$extension;
    }
}
