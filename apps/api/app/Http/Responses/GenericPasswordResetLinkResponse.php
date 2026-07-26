<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

class GenericPasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public function __construct(string $status)
    {
        // The broker result is deliberately not exposed to prevent account enumeration.
    }

    public function toResponse($request)
    {
        return response()->json([
            'message' => 'If an account exists for that email, a reset link has been sent.',
        ]);
    }
}
