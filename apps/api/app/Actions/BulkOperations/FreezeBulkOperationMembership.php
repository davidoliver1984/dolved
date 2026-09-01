<?php

declare(strict_types=1);

namespace App\Actions\BulkOperations;

use App\Enums\BulkItemStatus;
use App\Enums\BulkOperationStatus;
use App\Enums\BulkOperationType;
use App\Enums\BulkSelectionMode;
use App\Exceptions\BulkOperationException;
use App\Models\BulkOperation;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\ImportItem;
use App\Models\Workspace;
use App\Queries\Documents\BuildDocumentFamilyLibraryQuery;
use App\Services\Documents\StructuredExtractionCanonicaliser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class FreezeBulkOperationMembership
{
    public function __construct(
        private ClassifyBulkTarget $classify,
        private StructuredExtractionCanonicaliser $canonical,
        private BuildDocumentFamilyLibraryQuery $libraryQuery,
    ) {}

    /**
     * @param  list<string>  $targetPublicIds
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $payload
     */
    public function handle(
        BulkOperation $operation,
        Workspace $workspace,
        array $targetPublicIds,
        array $filters,
        array $payload,
    ): BulkOperation {
        return DB::transaction(function () use ($operation, $workspace, $targetPublicIds, $filters, $payload): BulkOperation {
            $operation = BulkOperation::query()->lockForUpdate()->findOrFail($operation->id);
            if ($operation->status !== BulkOperationStatus::PreparingMembership) {
                return $operation->load('items');
            }

            $targets = $this->targets($operation, $workspace, $targetPublicIds, $filters);
            $maximum = (int) config('bulk_operations.max_targets');
            if ($targets->count() > $maximum) {
                throw BulkOperationException::selectionTooLarge($maximum);
            }

            $manifest = [];
            $rows = [];
            foreach ($targets->values() as $index => $target) {
                $classification = $this->classify->handle($operation->operation_type, $target, $workspace, $payload);
                $kind = $operation->operation_type->targetKind();
                $ordinal = $index + 1;
                $manifest[] = [
                    'ordinal' => $ordinal,
                    'target_kind' => $kind->value,
                    'target_public_id' => $target->public_id,
                    'eligibility_status' => $classification['eligibility']->value,
                    'exclusion_reason' => $classification['reason']?->value,
                    'expected_state_snapshot' => $classification['snapshot'],
                ];
                $rows[] = [
                    'bulk_operation_id' => $operation->id,
                    'workspace_id' => $workspace->id,
                    'operation_type' => $operation->operation_type->value,
                    'ordinal' => $ordinal,
                    'target_family_id' => $target instanceof DocumentFamily ? $target->id : null,
                    'target_document_id' => $target instanceof Document ? $target->id : null,
                    'target_import_item_id' => $target instanceof ImportItem ? $target->id : null,
                    'target_kind' => $kind->value,
                    'target_public_id' => $target->public_id,
                    'target_display_label' => mb_substr($this->label($target), 0, 255),
                    'expected_state_snapshot' => json_encode($classification['snapshot'], JSON_THROW_ON_ERROR),
                    'eligibility_status' => $classification['eligibility']->value,
                    'exclusion_reason' => $classification['reason']?->value,
                    'execution_status' => $classification['eligibility']->value === 'eligible'
                        ? BulkItemStatus::Eligible->value
                        : BulkItemStatus::Excluded->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($rows !== []) {
                DB::table('bulk_operation_items')->insert($rows);
            }
            $operation->forceFill([
                'membership_digest' => $this->canonical->manifestDigest($manifest),
                'status' => BulkOperationStatus::AwaitingConfirmation,
            ])->save();

            return $operation->refresh()->load('items');
        }, 3);
    }

    /**
     * @param  list<string>  $targetPublicIds
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Document|DocumentFamily|ImportItem>
     */
    private function targets(BulkOperation $operation, Workspace $workspace, array $targetPublicIds, array $filters): Collection
    {
        $type = $operation->operation_type;
        $query = match ($type->targetKind()->value) {
            'version' => Document::query()->where('workspace_id', $workspace->id),
            'import_item' => ImportItem::query()->where('workspace_id', $workspace->id),
            default => DocumentFamily::query()->where('workspace_id', $workspace->id)->whereNull('tombstoned_at'),
        };

        if ($operation->selection_mode === BulkSelectionMode::CurrentPage) {
            $identities = array_values(array_unique($targetPublicIds));
            $targets = $query->whereIn('public_id', $identities)
                ->orderBy('public_id')->get();
            if ($targets->count() !== count($identities)) {
                throw BulkOperationException::targetNotFound();
            }

            return $targets;
        }

        if ($type === BulkOperationType::Promotion) {
            $query->when($filters['batch_public_id'] ?? null, fn (Builder $query, string $id) => $query->whereHas('batch', fn (Builder $batch) => $batch->where('public_id', $id)))
                ->when($filters['preflight_status'] ?? null, fn (Builder $query, string $status) => $query->where('preflight_status', $status))
                ->when($filters['match_status'] ?? null, fn (Builder $query, string $status) => $query->where('match_status', $status));

            return $query->orderBy('public_id')->limit((int) config('bulk_operations.max_targets') + 1)->get();
        }

        if ($type === BulkOperationType::Approval) {
            $families = $this->familyQuery($workspace, $filters)->select('document_families.id');
            $query->whereIn('document_family_id', $families)
                ->whereRaw('documents.id = (SELECT MAX(candidate.id) FROM documents candidate WHERE candidate.document_family_id = documents.document_family_id)');

            return $query->orderBy('public_id')->limit((int) config('bulk_operations.max_targets') + 1)->get();
        }

        return $this->familyQuery($workspace, $filters)->orderBy('document_families.public_id')
            ->limit((int) config('bulk_operations.max_targets') + 1)->get();
    }

    /** @param array<string, mixed> $filters */
    private function familyQuery(Workspace $workspace, array $filters): Builder
    {
        return $this->libraryQuery->handle($workspace, $filters);
    }

    private function label(Document|DocumentFamily|ImportItem $target): string
    {
        return match (true) {
            $target instanceof DocumentFamily => $target->name,
            $target instanceof Document => $target->source_filename,
            default => $target->source_filename,
        };
    }
}
