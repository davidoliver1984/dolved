<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Actions\Documents\ChangeDocumentFamilyOwner;
use App\Actions\Documents\CreateDocumentVersion;
use App\Actions\Documents\RequestDocumentIngestion;
use App\Enums\ChecksumVerificationStatus;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentGovernanceEventKey;
use App\Enums\DocumentStatus;
use App\Enums\PromotionAttemptStatus;
use App\Exceptions\ImportPromotionException;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFamily;
use App\Models\DocumentTag;
use App\Models\ImportItem;
use App\Models\OrganisationalLocation;
use App\Models\PromotionAttempt;
use App\Models\User;
use App\Support\Documents\RecordDocumentGovernanceEvent;
use App\Support\Documents\SafeDocumentSourceUrl;
use App\Support\Imports\WorkspaceChecksumLock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class FinalizeImportPromotion
{
    public function __construct(
        private WorkspaceChecksumLock $checksumLocks,
        private CreateDocumentVersion $createVersion,
        private RequestDocumentIngestion $ingestion,
        private RecordDocumentGovernanceEvent $events,
        private ChangeDocumentFamilyOwner $ownerChange,
    ) {}

    public function handle(PromotionAttempt $attempt, string $leaseToken, int $leaseGeneration): PromotionAttempt
    {
        $identity = PromotionAttempt::query()->with('item')->findOrFail($attempt->id);
        $checksum = (string) $identity->item->source_checksum_sha256;

        return DB::transaction(function () use ($attempt, $leaseToken, $leaseGeneration, $checksum): PromotionAttempt {
            $this->checksumLocks->acquire($attempt->workspace_id, $checksum);
            $locked = PromotionAttempt::query()->with(['item.batch', 'decisionSnapshot', 'actor'])->lockForUpdate()->findOrFail($attempt->id);
            if (! $this->leaseIsCurrent($locked, $leaseToken, $leaseGeneration)) {
                throw ImportPromotionException::conflict('stale_promotion_lease');
            }
            $item = ImportItem::query()->lockForUpdate()->findOrFail($locked->import_item_id);
            if ($item->current_decision_snapshot_id !== $locked->decision_snapshot_id) {
                return $this->conflict($locked, 'decision_changed');
            }
            if (! $locked->actor instanceof User
                || ! $locked->actor->workspaceMemberships()->where('workspace_id', $locked->workspace_id)->exists()) {
                return $this->conflict($locked, 'authorization_changed');
            }
            if (Document::query()
                ->where('workspace_id', $locked->workspace_id)
                ->where('source_checksum_sha256', $checksum)
                ->where('checksum_verification_status', ChecksumVerificationStatus::Verified->value)
                ->whereIn('status', [
                    DocumentStatus::Uploaded->value,
                    DocumentStatus::Queued->value,
                    DocumentStatus::Processing->value,
                    DocumentStatus::Indexed->value,
                    DocumentStatus::Failed->value,
                ])->exists()) {
                return $this->conflict($locked, 'duplicate');
            }

            $definition = json_decode($locked->decisionSnapshot->canonical_definition, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($definition)) {
                throw ImportPromotionException::invalid('invalid_decision_snapshot');
            }
            try {
                [$family, $predecessor] = $this->resolveFamily($item, $locked->actor, $definition);
            } catch (ImportPromotionException $exception) {
                if ($exception->reason === 'invalidated_predecessor') {
                    return $this->conflict($locked, 'invalidated_predecessor');
                }
                throw $exception;
            }
            $this->applyFamilyMetadata($family, $locked->actor, $definition, $locked);
            $locations = $this->resolveLocations($item, $definition);
            $document = $this->createDocument($item, $locked, $family, $predecessor, $definition, $locations);

            $locked->status = PromotionAttemptStatus::Committed;
            $locked->terminal_reason = null;
            $locked->committed_document_id = $document->id;
            $locked->lease_token_hash = null;
            $locked->lease_expires_at = null;
            $locked->save();
            $this->ingestion->handle($document, (string) Str::uuid());
            $this->events->record(
                $locked->workspace()->firstOrFail(),
                DocumentGovernanceEventKey::PromotionCompleted,
                $locked->public_id,
                $locked->public_id,
                [
                    'initiating_user_public_id' => $locked->actor?->public_id,
                    'target_kind' => 'document',
                    'target_public_id' => $document->public_id,
                    'target_display_label' => mb_substr($document->source_filename, 0, 255),
                ],
            );

            return $locked->refresh()->load('committedDocument');
        });
    }

    private function leaseIsCurrent(PromotionAttempt $attempt, string $token, int $generation): bool
    {
        return $attempt->status === PromotionAttemptStatus::SourceVerified
            && $attempt->lease_generation === $generation
            && $attempt->lease_token_hash !== null
            && hash_equals($attempt->lease_token_hash, hash('sha256', $token))
            && $attempt->lease_expires_at?->isFuture()
            && $attempt->cancellation_requested_at === null
            && $attempt->item->batch->retention_expires_at->isFuture();
    }

    private function conflict(PromotionAttempt $attempt, string $reason): PromotionAttempt
    {
        $attempt->status = PromotionAttemptStatus::Conflict;
        $attempt->terminal_reason = $reason;
        $attempt->lease_token_hash = null;
        $attempt->lease_expires_at = null;
        $attempt->save();
        $attempt->loadMissing('actor');
        $this->events->record(
            $attempt->workspace()->firstOrFail(),
            DocumentGovernanceEventKey::PromotionFailed,
            $attempt->public_id,
            $attempt->public_id,
            [
                'initiating_user_public_id' => $attempt->actor?->public_id,
                'target_kind' => 'import_item',
                'target_public_id' => $attempt->item()->value('public_id'),
                'target_display_label' => 'Import promotion',
            ],
        );

        return $attempt;
    }

    /** @param array<string, mixed> $definition @return array{DocumentFamily, ?Document} */
    private function resolveFamily(ImportItem $item, User $actor, array $definition): array
    {
        $familyDecision = $definition['family'];
        if ($familyDecision['mode'] === 'new') {
            $family = new DocumentFamily(['name' => trim((string) $familyDecision['title']), 'owner_user_id' => $actor->id]);
            $family->public_id = (string) Str::uuid();
            $family->workspace_id = $item->workspace_id;
            $family->save();

            return [$family, null];
        }
        $family = DocumentFamily::query()
            ->where('workspace_id', $item->workspace_id)
            ->where('public_id', $familyDecision['family_public_id'])
            ->whereNull('tombstoned_at')
            ->lockForUpdate()->first();
        if ($family === null) {
            throw ImportPromotionException::conflict('invalidated_predecessor');
        }
        $predecessor = Document::query()->where('document_family_id', $family->id)
            ->whereDoesntHave('successor')->orderByDesc('effective_from')->lockForUpdate()->first();
        if ($predecessor === null) {
            throw ImportPromotionException::conflict('invalidated_predecessor');
        }

        return [$family, $predecessor];
    }

    /** @param array<string, mixed> $definition */
    private function applyFamilyMetadata(DocumentFamily $family, User $actor, array $definition, PromotionAttempt $attempt): void
    {
        $metadata = $definition['metadata'];
        $owner = User::query()->where('public_id', $metadata['owner_user_public_id'])
            ->whereNull('disabled_at')->whereHas('workspaceMemberships', fn ($query) => $query->where('workspace_id', $family->workspace_id))->first();
        if ($owner === null) {
            throw ImportPromotionException::conflict('metadata_invalid');
        }
        $category = $metadata['category_public_id'] === null ? null : DocumentCategory::query()
            ->where('workspace_id', $family->workspace_id)->where('public_id', $metadata['category_public_id'])
            ->where('status', DocumentCategoryStatus::Active->value)->first();
        if ($metadata['category_public_id'] !== null && $category === null) {
            throw ImportPromotionException::conflict('metadata_invalid');
        }
        if ($family->owner_user_id !== $owner->id) {
            $family = $this->ownerChange->handle(
                $family,
                $actor,
                $owner,
                (int) $family->owner_assignment_generation,
                (int) $family->owner_user_id,
                $attempt->public_id,
            )['family'];
        }
        $family->description = $metadata['description'];
        $family->category_id = $category?->id;
        $family->review_due_date = $metadata['review_due_date'];
        $family->save();
        $tags = DocumentTag::query()->where('workspace_id', $family->workspace_id)
            ->whereIn('public_id', $metadata['tag_public_ids'])->get();
        if ($tags->count() !== count(array_unique($metadata['tag_public_ids']))) {
            throw ImportPromotionException::conflict('metadata_invalid');
        }
        $family->tags()->sync($tags->mapWithKeys(fn (DocumentTag $tag): array => [$tag->id => ['workspace_id' => $family->workspace_id]])->all());
    }

    /** @param array<string, mixed> $definition */
    private function createDocument(ImportItem $item, PromotionAttempt $attempt, DocumentFamily $family, ?Document $predecessor, array $definition, array $locations): Document
    {
        $metadata = $definition['metadata'];
        if ($metadata['source_url'] !== null && ! SafeDocumentSourceUrl::accepts($metadata['source_url'])) {
            throw ImportPromotionException::conflict('metadata_invalid');
        }
        $effectiveFrom = CarbonImmutable::parse($definition['effective_from']);
        if ($predecessor !== null && $effectiveFrom->lte($predecessor->effective_from)) {
            throw ImportPromotionException::conflict('invalidated_predecessor');
        }
        $evidence = $attempt->checksum_evidence;
        if (! is_array($evidence) || ($evidence['proof'] ?? null) !== 's3_version_id' || ! is_string($evidence['version_id'] ?? null)) {
            throw ImportPromotionException::conflict('immutable_storage_proof_unavailable');
        }

        return $this->createVersion->handleVerifiedPromotion(
            family: $family,
            predecessor: $predecessor,
            creator: $attempt->actor,
            sourceFilename: $item->source_filename,
            publisherLabel: $metadata['publisher_label'],
            sourceUrl: $metadata['source_url'],
            mediaType: $item->media_type,
            sizeBytes: $item->size_bytes,
            effectiveFrom: $effectiveFrom,
            storageKey: $attempt->reserved_object_key,
            storageVersionId: $evidence['version_id'],
            sourceChecksumSha256: $item->source_checksum_sha256,
            applicabilityLocations: $locations,
        );
    }

    /** @param array<string, mixed> $definition @return array<int, OrganisationalLocation> */
    private function resolveLocations(ImportItem $item, array $definition): array
    {
        $ids = array_values(array_unique($definition['applicability']['location_public_ids']));
        $locations = OrganisationalLocation::query()->where('workspace_id', $item->workspace_id)->whereIn('public_id', $ids)->get();
        if ($locations->count() !== count($ids)) {
            throw ImportPromotionException::conflict('applicability_invalid');
        }

        return $locations->all();
    }
}
