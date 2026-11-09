<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ExpireBookings
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Cache::add('booking-expiry-request-last-run', true, 55)) {
            Artisan::call('bookings:expire');
        }

        return $next($request);
    }
}
