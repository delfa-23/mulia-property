<?php

namespace App\Http\Controllers;

use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Throwable;

class GoogleDriveController extends Controller
{
    public function redirect(Request $request, GoogleDriveService $googleDriveService): RedirectResponse
    {
        $state = Str::random(64);
        $request->session()->put('google_drive.oauth_state', $state);

        return redirect()->away($googleDriveService->authorizationUrl($state));
    }

    public function callback(Request $request, GoogleDriveService $googleDriveService): RedirectResponse
    {
        $expectedState = $request->session()->pull('google_drive.oauth_state');
        $providedState = $request->query('state');

        abort_unless(
            is_string($expectedState)
                && is_string($providedState)
                && hash_equals($expectedState, $providedState),
            403,
        );

        if ($request->query('error')) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Google Drive authorization was cancelled.');
        }

        $authorizationCode = $request->string('code')->toString();

        abort_if($authorizationCode === '', 400, 'Google Drive authorization code is missing.');

        try {
            $googleDriveService->storeAuthorizationCode($authorizationCode);
        } catch (Throwable $exception) {
            Log::error('Google Drive OAuth authorization failed', [
                'error' => $exception->getMessage(),
            ]);

            return redirect()->route('admin.dashboard')
                ->with('error', 'Google Drive could not be connected. Check the application log for details.');
        }

        return redirect()->route('admin.dashboard')
            ->with('success', 'Google Drive connected successfully.');
    }
}
