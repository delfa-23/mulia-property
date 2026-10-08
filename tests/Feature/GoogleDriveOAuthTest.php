<?php

use App\Models\Customer;
use App\Models\Division;
use App\Models\GoogleDriveToken;
use App\Models\User;
use App\Services\GoogleDriveService;
use Google\Service\Drive;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;

beforeEach(function () {
    config([
        'services.google_drive.client_id' => 'test-client-id.apps.googleusercontent.com',
        'services.google_drive.client_secret' => 'test-client-secret',
        'services.google_drive.redirect_uri' => 'https://example.test/google-drive/callback',
        'services.google_drive.account_email' => 'Muliapropertyofficial@gmail.com',
    ]);
});

it('redirects unauthenticated users to login before starting Google authorization', function () {
    $this->get(route('google.drive.redirect'))
        ->assertRedirect(route('login'));
});

it('forbids non-admin users from managing the Google Drive connection', function () {
    $user = User::factory()->create([
        'role' => 'staff_pemberkasan',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('google.drive.redirect'))
        ->assertForbidden();
});

it('allows admins to view Google Drive document statuses', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.google-drive.documents.index'))
        ->assertOk()
        ->assertSee('Google Drive Documents')
        ->assertSee('Hubungkan Google Drive');
});

it('forbids users without a pemberkasan division assignment from viewing the global Google Drive document list', function () {
    $user = User::factory()->create([
        'role' => 'staff_pemberkasan',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.google-drive.documents.index'))
        ->assertForbidden();
});

it('allows assigned pemberkasan users to access Google Drive document pages', function () {
    $division = Division::create([
        'name' => 'Pemberkasan',
        'slug' => 'pemberkasan',
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'role' => 'staff_pemberkasan',
        'division_id' => $division->id,
        'email_verified_at' => now(),
    ]);
    $customer = Customer::create(['name' => 'Budi Santoso']);

    $this->actingAs($user)
        ->get(route('admin.google-drive.documents.index'))
        ->assertOk()
        ->assertSee('Google Drive Documents')
        ->assertDontSee('Hubungkan Google Drive');

    $this->get(route('admin.google-drive.documents.customer', $customer))
        ->assertOk()
        ->assertSee('Dokumen Budi Santoso')
        ->assertDontSee('Hubungkan Google Drive');
});

it('redirects admins to Google authorization with a session-bound state', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $this->mock(GoogleDriveService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('authorizationUrl')
            ->once()
            ->withArgs(fn (string $state): bool => strlen($state) === 64)
            ->andReturn('https://accounts.google.com/o/oauth2/auth');
    });

    $this->actingAs($user)
        ->get(route('google.drive.redirect'))
        ->assertRedirect('https://accounts.google.com/o/oauth2/auth')
        ->assertSessionHas('google_drive.oauth_state');
});

it('rejects an OAuth callback with an invalid state', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $this->mock(GoogleDriveService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('storeAuthorizationCode');
    });

    $this->actingAs($user)
        ->withSession(['google_drive.oauth_state' => 'expected-state'])
        ->get(route('google.drive.callback', [
            'state' => 'attacker-state',
            'code' => 'authorization-code',
        ]))
        ->assertForbidden();
});

it('stores the authorization token after a valid OAuth callback', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $this->mock(GoogleDriveService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('storeAuthorizationCode')
            ->once()
            ->with('authorization-code');
    });

    $this->actingAs($user)
        ->withSession(['google_drive.oauth_state' => 'expected-state'])
        ->get(route('google.drive.callback', [
            'state' => 'expected-state',
            'code' => 'authorization-code',
        ]))
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('success', 'Google Drive connected successfully.');
});

it('keeps an existing connection when Google authorization is cancelled', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);
    $token = new GoogleDriveToken;
    $token->id = 1;
    $token->token = [
        'access_token' => 'existing-access-token',
        'refresh_token' => 'existing-refresh-token',
    ];
    $token->save();

    $this->mock(GoogleDriveService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('storeAuthorizationCode');
    });

    $this->actingAs($user)
        ->withSession(['google_drive.oauth_state' => 'expected-state'])
        ->get(route('google.drive.callback', [
            'state' => 'expected-state',
            'error' => 'access_denied',
        ]))
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('error', 'Google Drive authorization was cancelled.');

    expect($token->fresh()->token['refresh_token'])->toBe('existing-refresh-token');
});

it('encrypts Google access and refresh tokens in the database', function () {
    $token = new GoogleDriveToken;
    $token->id = 1;
    $token->token = [
        'access_token' => 'sensitive-access-token',
        'refresh_token' => 'sensitive-refresh-token',
    ];
    $token->save();

    $storedValue = DB::table('google_drive_tokens')->where('id', 1)->value('token');

    expect($storedValue)
        ->not->toContain('sensitive-access-token')
        ->not->toContain('sensitive-refresh-token');

    expect($token->fresh()->token['refresh_token'])->toBe('sensitive-refresh-token');
});

it('prefills the configured account and offline access for Google authorization', function () {
    $authorizationUrl = app(GoogleDriveService::class)->authorizationUrl('oauth-state');
    parse_str((string) parse_url($authorizationUrl, PHP_URL_QUERY), $parameters);

    expect($parameters['login_hint'])->toBe('Muliapropertyofficial@gmail.com')
        ->and($parameters['prompt'])->toBe('consent')
        ->and($parameters['access_type'])->toBe('offline')
        ->and($parameters['state'])->toBe('oauth-state')
        ->and($parameters['scope'])->toContain('openid')
        ->and($parameters['scope'])->toContain('email')
        ->and($parameters['scope'])->toContain(Drive::DRIVE);
});

it('only accepts the configured Google Drive account email', function () {
    $service = app(GoogleDriveService::class);

    expect($service->isTargetAccountEmail('muliapropertyofficial@gmail.com'))->toBeTrue()
        ->and($service->isTargetAccountEmail('other-account@gmail.com'))->toBeFalse();
});

it('accepts a Google Drive folder ID or folder URL as the root folder setting', function (string $configuredValue, string $expectedFolderId) {
    config(['services.google_drive.root_folder_id' => $configuredValue]);

    expect(app(GoogleDriveService::class)->configuredRootFolderId())
        ->toBe($expectedFolderId);
})->with([
    'folder ID' => ['1YrW_qdeGfE973EHPzaBf8MEy9qT72Vx2', '1YrW_qdeGfE973EHPzaBf8MEy9qT72Vx2'],
    'folder URL' => [
        'https://drive.google.com/drive/folders/1YrW_qdeGfE973EHPzaBf8MEy9qT72Vx2?usp=sharing',
        '1YrW_qdeGfE973EHPzaBf8MEy9qT72Vx2',
    ],
    'account-scoped folder URL' => [
        'https://drive.google.com/drive/u/0/folders/1YrW_qdeGfE973EHPzaBf8MEy9qT72Vx2',
        '1YrW_qdeGfE973EHPzaBf8MEy9qT72Vx2',
    ],
]);

it('rejects a non-folder URL as the Google Drive root folder setting', function () {
    config(['services.google_drive.root_folder_id' => 'https://example.com/folders/root-id']);

    expect(fn () => app(GoogleDriveService::class)->configuredRootFolderId())
        ->toThrow(RuntimeException::class);
});
