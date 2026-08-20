<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\IngestionWorkerAuthenticationException;
use App\Services\Ingestion\IngestionWorkerRequestAuthenticator;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateIngestionWorker
{
    public function __construct(
        private readonly IngestionWorkerRequestAuthenticator $authenticator,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(
        Request $request,
        Closure $next,
        string $purpose,
    ): Response {
        try {
            $this->authenticator->verify($request, $purpose);
        } catch (IngestionWorkerAuthenticationException $exception) {
            $keyId = $request->header(
                IngestionWorkerRequestAuthenticator::KEY_ID_HEADER,
            );
            $eventId = $request->header(
                IngestionWorkerRequestAuthenticator::EVENT_ID_HEADER,
            );

            Log::warning('Ingestion worker authentication rejected.', [
                'event_name' => 'ingestion.authentication.rejected.v1',
                'key_id' => is_string($keyId)
                    && preg_match('/^[A-Za-z0-9._-]{1,64}$/', $keyId) === 1
                        ? $keyId
                        : null,
                'event_id' => is_string($eventId)
                    && preg_match('/^[0-9a-f-]{36}$/', $eventId) === 1
                        ? $eventId
                        : null,
                'verification_outcome' => $exception->reason,
            ]);

            return new JsonResponse([
                'message' => 'The ingestion worker request is not authenticated.',
                'error' => [
                    'code' => 'worker_authentication_failed',
                ],
            ], 401);
        }

        return $next($request);
    }
}
