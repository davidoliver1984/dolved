<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Actions\Ingestion\ClaimDocumentIngestion;
use App\Enums\IngestionClaimOutcome;
use App\Exceptions\IngestionClaimException;
use App\Exceptions\InvalidIngestionEvent;
use App\Http\Controllers\Controller;
use App\Services\Ingestion\DocumentIngestionContractValidator;
use App\Services\Ingestion\IngestionWorkerRequestAuthenticator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JsonException;

class DocumentIngestionClaimController extends Controller
{
    public function store(
        Request $request,
        string $eventId,
        DocumentIngestionContractValidator $validator,
        ClaimDocumentIngestion $claimIngestion,
    ): JsonResponse {
        try {
            $event = json_decode(
                $request->getContent(),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            throw IngestionClaimException::invalidContract();
        }

        try {
            if (! is_array($event)) {
                throw new InvalidIngestionEvent(
                    'The ingestion event must be an object.',
                );
            }

            $validator->validate($event);
        } catch (InvalidIngestionEvent) {
            throw IngestionClaimException::invalidContract();
        }

        if (
            ($event['event_id'] ?? null) !== $eventId
            || $request->header(
                IngestionWorkerRequestAuthenticator::EVENT_ID_HEADER,
            ) !== $eventId
        ) {
            throw IngestionClaimException::eventIdentityMismatch();
        }

        $result = $claimIngestion->handle(
            $event,
            hash('sha256', $request->getContent()),
        );

        Log::info('Document ingestion claim resolved.', [
            'event_id' => $event['event_id'],
            'correlation_id' => $event['correlation_id'],
            'workspace_id' => $event['workspace_id'],
            'document_id' => $event['document_id'],
            'claim_outcome' => $result->outcome->value,
            'document_status' => $result->documentStatus?->value,
        ]);

        return response()->json([
            'data' => [
                'outcome' => $result->outcome->value,
                'document_status' => $result->documentStatus?->value,
            ],
        ], match ($result->outcome) {
            IngestionClaimOutcome::Claimed,
            IngestionClaimOutcome::AlreadyClaimed => 200,
            IngestionClaimOutcome::StaleEvent,
            IngestionClaimOutcome::IneligibleState => 409,
        });
    }
}
