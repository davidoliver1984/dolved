<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Ingestion\IngestionWorkerRequestAuthenticator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use JsonException;
use Tests\TestCase;

final class WorkerSigningContractTest extends TestCase
{
    /** @throws JsonException */
    public function test_laravel_verifier_accepts_every_shared_worker_signature(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(
                dirname(__DIR__, 4).'/contracts/http/ingestion-worker/v1/signing-vectors.json',
            ),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        config([
            'ingestion.worker_auth.keys' => [
                $fixture['key_id'] => $fixture['secret_base64'],
            ],
            'ingestion.worker_auth.max_clock_skew_seconds' => 1,
        ]);
        CarbonImmutable::setTestNow(
            CarbonImmutable::createFromTimestampUTC($fixture['vectors'][0]['timestamp']),
        );

        try {
            foreach ($fixture['vectors'] as $vector) {
                $request = Request::create(
                    $vector['path'],
                    $vector['method'],
                    server: $this->transformHeadersToServerVars([
                        'Content-Type' => 'application/json',
                        IngestionWorkerRequestAuthenticator::KEY_ID_HEADER => $fixture['key_id'],
                        IngestionWorkerRequestAuthenticator::TIMESTAMP_HEADER => (string) $vector['timestamp'],
                        IngestionWorkerRequestAuthenticator::EVENT_ID_HEADER => $vector['event_id'],
                        IngestionWorkerRequestAuthenticator::PURPOSE_HEADER => $vector['purpose'],
                        IngestionWorkerRequestAuthenticator::SIGNATURE_HEADER => $vector['signature'],
                    ]),
                    content: $vector['body'],
                );
                $route = new Route('POST', $vector['path'], fn (): null => null);
                $route->bind($request);
                $route->setParameter('eventId', $vector['event_id']);
                $request->setRouteResolver(fn (): Route => $route);

                app(IngestionWorkerRequestAuthenticator::class)->verify(
                    $request,
                    $vector['purpose'],
                );
                $this->addToAssertionCount(1);
            }
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
