<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureSecureUrl();
    }

    /**
     * Force secure URL generation for proxied HTTPS deployments such as ngrok.
     */
    protected function configureSecureUrl(): void
    {
        $appUrl = rtrim((string) config('app.url', 'http://localhost'), '/');
        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        $forwardedSsl = $_SERVER['HTTP_X_FORWARDED_SSL'] ?? '';
        $httpsHeader = $_SERVER['HTTPS'] ?? '';
        $host = (string) request()->getHost();
        $isSecureRequest = request()->isSecure()
            || strtolower($forwardedProto) === 'https'
            || strtolower($forwardedSsl) === 'on'
            || strtolower($httpsHeader) === 'on'
            || str_contains($host, 'ngrok-free.dev');

        if (str_starts_with($appUrl, 'https://') || $isSecureRequest) {
            $rootUrl = $appUrl !== 'http://localhost' ? $appUrl : request()->getSchemeAndHttpHost();
            URL::forceRootUrl($rootUrl);
            URL::forceScheme('https');
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
