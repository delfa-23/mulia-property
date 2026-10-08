<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    public function toResponse($request): Response
    {
        $redirect = route($request->user()->dashboardRouteName());

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => $redirect], 200)
            : redirect($redirect);
    }
}
