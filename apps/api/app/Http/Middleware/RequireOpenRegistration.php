<?php

namespace App\Http\Middleware;

use App\Enums\WorkspaceInvitationStatus;
use App\Models\WorkspaceInvitation;
use App\Support\CanonicalEmail;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireOpenRegistration
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('fortify.registration_enabled')) {
            $token = $request->string('invitation_token')->value();
            $email = CanonicalEmail::from($request->string('email')->value());
            $invitation = $token === '' ? null : WorkspaceInvitation::query()
                ->where('token_digest', hash('sha256', $token))
                ->where('status', WorkspaceInvitationStatus::Pending)
                ->where('expires_at', '>', now())
                ->first();

            abort_unless(
                $invitation !== null
                && hash_equals(CanonicalEmail::from($invitation->invited_email), $email),
                404
            );
        }

        return $next($request);
    }
}
