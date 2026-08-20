<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireEnabledAccount
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->disabled_at !== null) {
            if ($request->hasSession()) {
                $request->session()->invalidate();
            }

            return new JsonResponse([
                'message' => 'This account is disabled.',
                'error' => ['code' => 'account_disabled'],
            ], 403);
        }

        return $next($request);
    }
}
