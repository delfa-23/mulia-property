<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        $redirect = route($request->user()->dashboardRouteName());

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 200)
            : redirect($redirect);
    }
}
