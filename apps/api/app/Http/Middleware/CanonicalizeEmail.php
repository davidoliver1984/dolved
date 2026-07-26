<?php

namespace App\Http\Middleware;

use App\Support\CanonicalEmail;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanonicalizeEmail
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('email') && is_string($request->input('email'))) {
            $request->merge([
                'email' => CanonicalEmail::from($request->string('email')->toString()),
            ]);
        }

        return $next($request);
    }
}
