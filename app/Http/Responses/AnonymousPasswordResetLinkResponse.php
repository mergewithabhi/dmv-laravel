<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;

class AnonymousPasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse
{
    public function toResponse($request)
    {
        $message = trans('passwords.sent');

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message], 200)
            : back()->withInput($request->only('email'))->with('status', $message);
    }
}
