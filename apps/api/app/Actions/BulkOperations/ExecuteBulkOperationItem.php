<?php

declare(strict_types=1);

namespace App\Actions\BulkOperations;

use App\Actions\Documents\ApproveDocumentVersion;
use App\Actions\Documents\CreateApplicabilityOnlySuccessor;
use App\Actions\Documents\ExecuteDocumentGovernanceCommand;
use App\Actions\Documents\SyncDocumentFamilyTags;
use App\Actions\Documents\UpdateDocumentFamilyMetadata;
use App\Actions\Imports\ReserveImportPromotion;
use App\Enums\BulkAttemptStatus;
use App\Enums\BulkAttemptSuccessKind;
use App\Enums\BulkItemStatus;
use App\Enums\BulkOperationType;
use App\Enums\BulkSubordinateIdentityKind;
use App\Enums\BulkSubordinateKind;
use App\Enums\BulkTargetReferenceStatus;
use App\Enums\PromotionOperationKind;
use App\Jobs\AdvanceBulkApplicabilitySuccessor;
use App\Jobs\AdvanceImportPromotion;
use App\Models\BulkOperationItem;
use App\Models\BulkOperationItemAttempt;
use App\Models\BulkOperationItemSubordinateTransition;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFamily;
use App\Models\DocumentTag;
use App\Models\ImportItem;
use App\Models\OrganisationalLocation;
use App\Models\User;
use App\Services\Documents\StructuredExtractionCanonicaliser;
use App\Support\Documents\LockDocumentFamilyLineage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

final readonly class ExecuteBulkOperationItem
{
    public function __construct(
        private ClassifyBulkTarget $classify,
        private StructuredExtractionCanonicaliser $canonical,
        private ApproveDocumentVersion $approve,
        private ExecuteDocumentGovernanceCommand $governanceCommand,
        private UpdateDocumentFamilyMetadata $metadata,
        private SyncDocumentFamilyTags $tags,
        private ReserveImportPromotion $promotion,
        private CreateApplicabilityOnlySuccessor $applicability,
        private FinalizeBulkOperationAttempt $finalize,
        private LockDocumentFamilyLineage $lockLineage,
    ) {}

    public function handle(BulkOperationItemAttempt $attempt): BulkOperationItem
    {
        try {
            $terminalAttempt = DB::transaction(fn (): BulkOperationItemAttempt => $this->mutate($attempt), 3);
        } catch (Throwable $error) {
            $terminalAttempt = $this->recordFailure($attempt, $error);
        }

        $item = $terminalAttempt->item()->firstOrFail();
        if ($item->execution_status === BulkItemStatus::WaitingOnSubordinate) {
            return $item;
        }

        return $this->finalize->handle($terminalAttempt);
    }

    private function mutate(BulkOperationItemAttempt $attempt): BulkOperationItemAttempt
    {
        $item = BulkOperationItem::query()->with('operation.workspace')
            ->findOrFail($attempt->bulk_operation_item_id);
        $operation = $item->operation;
        if ($item->target_reference_status !== BulkTargetReferenceStatus::Live) {
            $lockedAttempt = BulkOperationItemAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $this->assertFence($lockedAttempt, $attempt);

            return $this->notApplied($lockedAttempt, 'target_no_longer_exists');
        }
        $target = $this->lockTarget($item);
        $lockedAttempt = BulkOperationItemAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
        $this->assertFence($lockedAttempt, $attempt);
        $actor = User::query()->find($operation->actor_user_id);
        if (! $actor instanceof User || $actor->disabled_at !== null
            || ! Gate::forUser($actor)->allows('manageDocumentGovernance', $operation->workspace)) {
            return $this->notApplied($lockedAttempt, 'authorization_changed');
        }
        $payload = json_decode($operation->canonical_payload, true, flags: JSON_THROW_ON_ERROR);
        $current = $this->classify->handle($operation->operation_type, $target, $operation->workspace, $payload);
        if (! hash_equals(
            hash('sha256', $this->canonical->canonicalValueBytes($item->expected_state_snapshot)),
            hash('sha256', $this->canonical->canonicalValueBytes($current['snapshot'])),
        )) {
            return $this->notApplied($lockedAttempt, 'expected_state_mismatch');
        }

        return match ($operation->operation_type) {
            BulkOperationType::Approval => $this->completeLocal($lockedAttempt, fn () => $this->approve($target, $actor, $lockedAttempt)),
            BulkOperationType::OwnerAssignment => $this->completeLocal($lockedAttempt, fn () => $this->assignOwner($target, $actor, $payload)),
            BulkOperationType::CategoryAssignment => $this->completeLocal($lockedAttempt, fn () => $this->assignCategory($target, $actor, $payload)),
            BulkOperationType::TagChange => $this->completeLocal($lockedAttempt, fn () => $this->changeTags($target, $actor, $payload)),
            BulkOperationType::ReviewDateAssignment => $this->completeLocal($lockedAttempt, fn () => $this->assignReviewDate($target, $actor, $payload)),
            BulkOperationType::Promotion => $this->initiatePromotion($item, $lockedAttempt, $target, $actor),
            BulkOperationType::ApplicabilityChange => $this->initiateApplicability($item, $lockedAttempt, $target, $actor, $payload),
        };
    }

    private function assertFence(BulkOperationItemAttempt $locked, BulkOperationItemAttempt $worker): void
    {
        if ($locked->status !== BulkAttemptStatus::Open || $locked->attempt_token !== $worker->attempt_token
            || $locked->generation !== $worker->generation || $locked->lease_expires_at->isPast()) {
            throw new \RuntimeException('bulk_attempt_fence_rejected');
        }
    }

    private function target(BulkOperationItem $item): Document|DocumentFamily|ImportItem
    {
        return match ($item->operation_type) {
            BulkOperationType::Approval => Document::query()->findOrFail($item->target_document_id),
            BulkOperationType::Promotion => ImportItem::query()->findOrFail($item->target_import_item_id),
            default => DocumentFamily::query()->findOrFail($item->target_family_id),
        };
    }

    private function lockTarget(BulkOperationItem $item): Document|DocumentFamily|ImportItem
    {
        $target = $this->target($item);

        return match ($item->operation_type) {
            BulkOperationType::Approval => $this->lockLineage->handle($target)[1],
            BulkOperationType::Promotion => ImportItem::query()->lockForUpdate()->findOrFail($target->id),
            BulkOperationType::ApplicabilityChange => $this->lockApplicabilityTarget($target, $item),
            default => DocumentFamily::query()->lockForUpdate()->findOrFail($target->id),
        };
    }

    private function lockApplicabilityTarget(DocumentFamily $family, BulkOperationItem $item): DocumentFamily
    {
        $predecessorId = $item->expected_state_snapshot['current_document_public_id'] ?? null;
        $predecessor = Document::query()->where('public_id', $predecessorId)->firstOrFail();
        $this->lockLineage->handle($predecessor);

        return DocumentFamily::query()->findOrFail($family->id);
    }

    private function approve(Document $target, User $actor, BulkOperationItemAttempt $attempt): Document
    {
        [$result] = $this->governanceCommand->handle(
            $target,
            $actor,
            'approve',
            $attempt->invocation_idempotency_key,
            [],
            fn (): Document => $this->approve->handle($target, $actor),
        );

        return $result;
    }

    private function completeLocal(BulkOperationItemAttempt $attempt, callable $mutation): BulkOperationItemAttempt
    {
        $result = $mutation();
        $attempt->forceFill([
            'status' => BulkAttemptStatus::Succeeded,
            'success_kind' => BulkAttemptSuccessKind::DatabaseLocal,
            'result_digest' => hash('sha256', $this->canonical->canonicalValueBytes([
                'class' => $result::class,
                'id' => $result->getKey(),
                'updated_at' => $result->updated_at?->toISOString(),
            ])),
            'completed_at' => now(),
        ])->save();

        return $attempt;
    }

    private function notApplied(BulkOperationItemAttempt $attempt, string $reason): BulkOperationItemAttempt
    {
        $attempt->forceFill([
            'status' => BulkAttemptStatus::NotApplied,
            'not_applied_reason' => $reason,
            'completed_at' => now(),
        ])->save();

        return $attempt;
    }

    private function assignOwner(DocumentFamily $family, User $actor, array $payload): DocumentFamily
    {
        $owner = User::query()->where('public_id', $payload['owner_user_public_id'])->firstOrFail();

        return $this->metadata->handle($family, $actor, $family->description, $family->category, $owner, $family->review_due_date?->toDateString());
    }

    private function assignCategory(DocumentFamily $family, User $actor, array $payload): DocumentFamily
    {
        $category = isset($payload['category_public_id'])
            ? DocumentCategory::query()->where('workspace_id', $family->workspace_id)->where('public_id', $payload['category_public_id'])->firstOrFail()
            : null;

        return $this->metadata->handle($family, $actor, $family->description, $category, $family->owner()->firstOrFail(), $family->review_due_date?->toDateString());
    }

    private function changeTags(DocumentFamily $family, User $actor, array $payload): DocumentFamily
    {
        $current = $family->tags()->pluck('public_id');
        $requested = DocumentTag::query()->where('workspace_id', $family->workspace_id)
            ->whereIn('public_id', $payload['tag_public_ids'])->pluck('public_id');
        $result = match ($payload['mode']) {
            'add' => $current->merge($requested)->unique()->values(),
            'remove' => $current->diff($requested)->values(),
            default => $requested,
        };

        return $this->tags->handle($family, $actor, $result->all());
    }

    private function assignReviewDate(DocumentFamily $family, User $actor, array $payload): DocumentFamily
    {
        return $this->metadata->handle(
            $family,
            $actor,
            $family->description,
            $family->category,
            $family->owner()->firstOrFail(),
            $payload['review_due_date'] ?? null,
        );
    }

    private function initiatePromotion(BulkOperationItem $item, BulkOperationItemAttempt $attempt, ImportItem $target, User $actor): BulkOperationItemAttempt
    {
        $subordinate = $this->promotion->handle($target, $actor, PromotionOperationKind::Promote, $attempt->invocation_idempotency_key);
        $this->recordSubordinate($item, $attempt, BulkSubordinateKind::PromotionAttempt, BulkSubordinateIdentityKind::PublicId, $subordinate->public_id);
        AdvanceImportPromotion::dispatch($subordinate->id);

        return $attempt;
    }

    private function initiateApplicability(BulkOperationItem $item, BulkOperationItemAttempt $attempt, DocumentFamily $family, User $actor, array $payload): BulkOperationItemAttempt
    {
        $predecessorId = $item->expected_state_snapshot['current_document_public_id'] ?? null;
        $predecessor = Document::query()->where('public_id', $predecessorId)->firstOrFail();
        $locations = OrganisationalLocation::query()->where('workspace_id', $family->workspace_id)
            ->whereIn('public_id', $payload['location_public_ids'])->get()->all();
        [$target, $clone, $leaseToken] = $this->applicability->prepare(
            $predecessor,
            $actor,
            CarbonImmutable::now(),
            $locations,
            $attempt->invocation_idempotency_key,
        );
        $fallbackEventId = (string) Str::uuid();
        if ($clone === null) {
            $this->recordSubordinate($item, $attempt, BulkSubordinateKind::FullIngestionFallback, BulkSubordinateIdentityKind::EventId, $fallbackEventId);
        } else {
            $this->recordSubordinate($item, $attempt, BulkSubordinateKind::ContentCloneOperation, BulkSubordinateIdentityKind::PublicId, $clone->public_id);
        }
        AdvanceBulkApplicabilitySuccessor::dispatch(
            $predecessor->id,
            $target->id,
            $clone?->id,
            $leaseToken,
            $attempt->invocation_idempotency_key,
            $fallbackEventId,
        );

        return $attempt;
    }

    private function recordSubordinate(BulkOperationItem $item, BulkOperationItemAttempt $attempt, BulkSubordinateKind $kind, BulkSubordinateIdentityKind $identityKind, string $identity): void
    {
        $item = BulkOperationItem::query()->lockForUpdate()->findOrFail($item->id);
        $digest = hash('sha256', $this->canonical->canonicalValueBytes([
            'kind' => $kind->value, 'identity_kind' => $identityKind->value, 'identity' => $identity,
        ]));
        $attempt->forceFill([
            'status' => BulkAttemptStatus::Succeeded,
            'success_kind' => BulkAttemptSuccessKind::SubordinateInitiated,
            'result_digest' => $digest,
            'result_subordinate_kind' => $kind,
            'result_identity_kind' => $identityKind,
            'result_identity_value' => $identity,
            'completed_at' => now(),
        ])->save();
        BulkOperationItemSubordinateTransition::query()->create([
            'bulk_operation_item_id' => $item->id,
            'workspace_id' => $item->workspace_id,
            'ordinal' => 1,
            'transition_key' => $digest,
            'subordinate_kind' => $kind,
            'subordinate_identity_kind' => $identityKind,
            'subordinate_identity_value' => $identity,
            'transition_category' => 'initiated',
            'recorded_at' => now(),
            'correlation_identity' => $attempt->invocation_idempotency_key,
            'mapped_state_digest' => null,
        ]);
        $item->forceFill([
            'execution_status' => BulkItemStatus::WaitingOnSubordinate,
            'started_at' => $attempt->started_at,
            'subordinate_kind' => $kind,
            'subordinate_identity_kind' => $identityKind,
            'subordinate_identity_value' => $identity,
            'subordinate_awaited_since' => now(),
            'incorporated_attempt_generation' => $attempt->generation,
        ])->save();
    }

    private function recordFailure(BulkOperationItemAttempt $attempt, Throwable $error): BulkOperationItemAttempt
    {
        return DB::transaction(function () use ($attempt, $error): BulkOperationItemAttempt {
            $locked = BulkOperationItemAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($locked->status !== BulkAttemptStatus::Open) {
                return $locked;
            }
            $permanent = str_contains($error->getMessage(), 'authorization');
            $locked->forceFill([
                'status' => $permanent ? BulkAttemptStatus::FailedPermanent : BulkAttemptStatus::FailedRetryable,
                'failure_category' => $permanent ? 'authorization_insufficient' : 'execution_failed',
                'completed_at' => now(),
            ])->save();

            return $locked;
        }, 3);
    }
}
