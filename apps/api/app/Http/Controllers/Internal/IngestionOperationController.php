<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Actions\Ingestion\AuthoriseIngestionPublication;
use App\Actions\Ingestion\CancelIngestionAttempt;
use App\Actions\Ingestion\CompleteIngestionAttempt;
use App\Actions\Ingestion\FailIngestionAttempt;
use App\Actions\Ingestion\RenewIngestionLease;
use App\Actions\Ingestion\ResumeIngestionAttempt;
use App\Actions\Ingestion\SealIngestionChunks;
use App\Actions\Ingestion\SubmitIngestionChunks;
use App\Http\Controllers\Controller;
use App\Http\Requests\IngestionOperationRequest;
use Illuminate\Http\JsonResponse;

class IngestionOperationController extends Controller
{
    public function renew(IngestionOperationRequest $request, string $eventId, RenewIngestionLease $action): JsonResponse
    {
        $attempt = $action->handle($eventId, $request->validated());

        return response()->json(['data' => ['outcome' => 'renewed', 'lease_expires_at' => $attempt->lease_expires_at?->toIso8601String()]]);
    }

    public function submit(IngestionOperationRequest $request, string $eventId, SubmitIngestionChunks $action): JsonResponse
    {
        return response()->json(['data' => ['outcome' => 'accepted', 'persisted_chunk_count' => $action->handle($eventId, $request->validated())]]);
    }

    public function seal(IngestionOperationRequest $request, string $eventId, SealIngestionChunks $action): JsonResponse
    {
        $attempt = $action->handle($eventId, $request->validated());

        return response()->json(['data' => ['outcome' => 'sealed', 'expected_chunk_count' => $attempt->expected_chunk_count, 'chunk_manifest_digest' => $attempt->chunk_manifest_digest]]);
    }

    public function resume(IngestionOperationRequest $request, string $eventId, ResumeIngestionAttempt $action): JsonResponse
    {
        return response()->json(['data' => $action->handle($eventId, $request->validated())]);
    }

    public function authorise(IngestionOperationRequest $request, string $eventId, AuthoriseIngestionPublication $action): JsonResponse
    {
        $attempt = $action->handle($eventId, $request->validated());

        return response()->json(['data' => ['outcome' => 'authorised', 'evidence' => $attempt->publication_evidence]]);
    }

    public function complete(IngestionOperationRequest $request, string $eventId, CompleteIngestionAttempt $action): JsonResponse
    {
        $attempt = $action->handle($eventId, $request->validated());

        return response()->json(['data' => ['outcome' => 'indexed', 'document_status' => $attempt->document->status->value]]);
    }

    public function fail(IngestionOperationRequest $request, string $eventId, FailIngestionAttempt $action): JsonResponse
    {
        $attempt = $action->handle($eventId, $request->validated());

        return response()->json(['data' => ['outcome' => 'failed', 'document_status' => $attempt->document->status->value]]);
    }

    public function cancel(IngestionOperationRequest $request, string $eventId, CancelIngestionAttempt $action): JsonResponse
    {
        $attempt = $action->handle($eventId, $request->validated());

        return response()->json(['data' => ['outcome' => 'cancelled', 'attempt_status' => $attempt->status->value]]);
    }
}
