<?php

declare(strict_types=1);

namespace App\Actions\BulkOperations;

use App\Enums\BulkOperationStatus;
use App\Enums\BulkOperationType;
use App\Enums\BulkSelectionMode;
use App\Exceptions\BulkOperationException;
use App\Models\BulkOperation;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Documents\StructuredExtractionCanonicaliser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateBulkOperation
{
    public function __construct(
        private StructuredExtractionCanonicaliser $canonical,
        private FreezeBulkOperationMembership $freeze,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $targetPublicIds
     */
    public function handle(
        Workspace $workspace,
        User $actor,
        BulkOperationType $type,
        BulkSelectionMode $selectionMode,
        array $payload,
        array $filters,
        array $targetPublicIds,
        string $idempotencyKey,
    ): BulkOperation {
        sort($targetPublicIds, SORT_STRING);
        $canonicalPayload = $this->canonical->canonicalValueBytes($payload);
        $filterExplanation = $this->canonical->canonicalValueBytes($filters);
        $requestDigest = hash('sha256', $this->canonical->canonicalValueBytes([
            'operation_type' => $type->value,
            'selection_mode' => $selectionMode->value,
            'payload' => $payload,
            'filters' => $filters,
            'target_public_ids' => $targetPublicIds,
        ]));

        return DB::transaction(function () use ($workspace, $actor, $type, $selectionMode, $payload, $filters, $targetPublicIds, $idempotencyKey, $canonicalPayload, $filterExplanation, $requestDigest): BulkOperation {
            $existing = BulkOperation::query()
                ->where('workspace_id', $workspace->id)
                ->where('actor_identity', 'user:'.$actor->id)
                ->where('operation_type', $type->value)
                ->where('client_idempotency_key', $idempotencyKey)
                ->lockForUpdate()->first();
            if ($existing !== null) {
                if (! hash_equals($existing->request_digest, $requestDigest)) {
                    throw BulkOperationException::idempotencyConflict();
                }

                return $existing->load('items');
            }

            $operation = BulkOperation::query()->create([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'actor_type' => 'human',
                'actor_user_id' => $actor->id,
                'actor_identity' => 'user:'.$actor->id,
                'operation_type' => $type,
                'status' => BulkOperationStatus::PreparingMembership,
                'canonical_payload' => $canonicalPayload,
                'payload_schema_version' => 1,
                'selection_mode' => $selectionMode,
                'filter_explanation' => $filterExplanation,
                'client_idempotency_key' => $idempotencyKey,
                'request_digest' => $requestDigest,
            ]);

            return $this->freeze->handle($operation, $workspace, $targetPublicIds, $filters, $payload);
        }, 3);
    }
}
