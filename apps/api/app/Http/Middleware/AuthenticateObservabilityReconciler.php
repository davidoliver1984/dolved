<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ObservabilityReconciliationException;
use App\Services\Platform\ObservabilityReconciliationAuthenticator;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthenticateObservabilityReconciler
{
    public function __construct(private ObservabilityReconciliationAuthenticator $authenticator) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $purpose): Response
    {
        try {
            $this->authenticator->verify($request, $purpose);
        } catch (ObservabilityReconciliationException $exception) {
            Log::warning('Observability reconciliation authentication rejected.', [
                'event_name' => 'observability.reconciliation.authentication_rejected.v1',
                'verification_outcome' => $exception->reason,
            ]);

            return new JsonResponse(['message' => 'The reconciliation request is not authenticated.'], 401);
        }

        return $next($request);
    }
}
