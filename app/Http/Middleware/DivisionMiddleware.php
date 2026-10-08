<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DivisionMiddleware
{
    public function handle(
        Request $request,
        Closure $next,
        string $division
    ): Response {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        // Admin dapat mengakses semua divisi
        if ($user->role === 'admin') {
            return $next($request);
        }

        abort_unless($user->isAssignedToDivision($division), 403);

        return $next($request);
    }
}
