<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Actions\Documents\ClaimDocumentDeletion;
use App\Actions\Documents\CompleteDocumentDeletion;
use App\Actions\Documents\FailDocumentDeletion;
use App\Exceptions\DocumentAdministrationException;
use App\Exceptions\InvalidIngestionEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentDeletionOperationRequest;
use App\Services\Documents\DocumentDeletionContractValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;

class DocumentDeletionOperationController extends Controller
{
    public function claim(
        Request $request,
        string $eventId,
        ClaimDocumentDeletion $action,
        DocumentDeletionContractValidator $validator,
    ): JsonResponse {
        try {
            $event = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw DocumentAdministrationException::conflict('invalid_deletion_contract', 'The deletion event is invalid.');
        }
        if (! is_array($event) || ($event['event_id'] ?? null) !== $eventId) {
            throw DocumentAdministrationException::conflict('invalid_deletion_contract', 'The deletion event is invalid.');
        }
        try {
            $validator->validate($event);
        } catch (InvalidIngestionEvent) {
            throw DocumentAdministrationException::conflict('invalid_deletion_contract', 'The deletion event is invalid.');
        }
        $data = $action->handle($event, hash('sha256', $request->getContent()));

        return response()->json(['data' => $data], $data['outcome'] === 'owned_by_another_worker' ? 423 : 200);
    }

    public function complete(DocumentDeletionOperationRequest $request, string $eventId, CompleteDocumentDeletion $action): JsonResponse
    {
        $operation = $action->handle($eventId, $request->validated());

        return response()->json(['data' => ['outcome' => 'deleted', 'status' => $operation->status->value]]);
    }

    public function fail(DocumentDeletionOperationRequest $request, string $eventId, FailDocumentDeletion $action): JsonResponse
    {
        $operation = $action->handle($eventId, $request->validated());

        return response()->json(['data' => ['outcome' => 'recorded', 'status' => $operation->status->value]]);
    }
}
