<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse;

class ApiLoginResponse implements LoginResponse
{
    public function toResponse($request)
    {
        $intended = $request->hasSession()
            ? $request->session()->pull('url.intended')
            : null;

        return response()->json([
            'data' => [
                'user' => $request->user(),
                'redirect_to' => $this->verificationRedirect($intended),
            ],
        ]);
    }

    private function verificationRedirect(mixed $intended): ?string
    {
        if (! is_string($intended)) {
            return null;
        }
        $application = parse_url((string) config('app.url'));
        $target = parse_url($intended);
        if ($application === false || $target === false
            || ($target['scheme'] ?? null) !== ($application['scheme'] ?? null)
            || ($target['host'] ?? null) !== ($application['host'] ?? null)
            || ($target['port'] ?? null) !== ($application['port'] ?? null)
            || ! str_starts_with((string) ($target['path'] ?? ''), '/api/auth/email/verify/')) {
            return null;
        }

        return $intended;
    }
}
