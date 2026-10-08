<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function toResponse($request): Response
    {
        $redirect = route($request->user()->dashboardRouteName());

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 200)
            : redirect($redirect);
    }
}
