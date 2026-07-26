<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\VerifyEmailResponse;

class VerifyEmailRedirectResponse implements VerifyEmailResponse
{
    public function toResponse($request)
    {
        return redirect()->away(
            rtrim(config('app.frontend_url'), '/').'/verify-email/result?status=verified'
        );
    }
}
