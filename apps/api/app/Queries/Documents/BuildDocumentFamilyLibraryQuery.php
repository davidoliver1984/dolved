<?php

declare(strict_types=1);

namespace App\Queries\Documents;

use App\Enums\DocumentFamilyDeletionStatus;
use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class BuildDocumentFamilyLibraryQuery
{
    public function __construct(private BuildCurrentDocumentForFamily $currentDocument) {}

    /** @param array<string, mixed> $filters */
    public function handle(Workspace $workspace, array $filters): Builder
    {
        $current = $this->currentDocument->handle();
        $authorityStart = DB::getDriverName() === 'pgsql'
            ? 'GREATEST(effective_from, approved_at)'
            : 'CASE WHEN effective_from > approved_at THEN effective_from ELSE approved_at END';
        $scheduled = Document::query()->select('effective_from')
            ->whereColumn('document_family_id', 'document_families.id')
            ->where('governance_status', DocumentGovernanceStatus::Approved->value)
            ->whereNotNull('approved_at')->whereRaw("{$authorityStart} > ?", [now()])->limit(1);
        $pending = Document::query()->select('id')
            ->whereColumn('document_family_id', 'document_families.id')
            ->whereIn('status', [
                DocumentStatus::Uploading->value, DocumentStatus::Uploaded->value,
                DocumentStatus::Queued->value, DocumentStatus::Processing->value,
                DocumentStatus::Failed->value,
            ])->limit(1);
        $draft = Document::query()->select('id')
            ->whereColumn('document_family_id', 'document_families.id')
            ->where('governance_status', DocumentGovernanceStatus::Draft->value)->limit(1);

        $query = DocumentFamily::query()
            ->where('document_families.workspace_id', $workspace->id)
            ->whereNull('document_families.tombstoned_at')
            ->whereDoesntHave('deletionOperations', fn (Builder $delete): Builder => $delete->whereIn('status', [
                DocumentFamilyDeletionStatus::Pending->value,
                DocumentFamilyDeletionStatus::Processing->value,
                DocumentFamilyDeletionStatus::PartiallyFailed->value,
            ]))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $needle = '%'.mb_strtolower(mb_substr(trim($search), 0, 200)).'%';
                $query->where(fn (Builder $match): Builder => $match
                    ->whereRaw('LOWER(document_families.name) LIKE ?', [$needle])
                    ->orWhereExists(fn ($documents) => $documents->selectRaw('1')->from('documents')
                        ->whereColumn('documents.document_family_id', 'document_families.id')
                        ->whereRaw('LOWER(documents.source_filename) LIKE ?', [$needle])));
            })
            ->when($filters['category'] ?? null, fn (Builder $query, string $id): Builder => $query->whereHas('category', fn (Builder $category): Builder => $category->where('public_id', $id)))
            ->when($filters['owner'] ?? null, fn (Builder $query, string $id): Builder => $query->whereHas('owner', fn (Builder $owner): Builder => $owner->where('public_id', $id)))
            ->when($filters['applicability'] ?? null, function (Builder $query, string $id): void {
                $query->whereExists(fn ($locations) => $locations->selectRaw('1')->from('documents')
                    ->join('document_applicability_snapshots', 'document_applicability_snapshots.document_id', '=', 'documents.id')
                    ->join('document_applicability_locations', 'document_applicability_locations.document_applicability_snapshot_id', '=', 'document_applicability_snapshots.id')
                    ->join('organisational_locations', 'organisational_locations.id', '=', 'document_applicability_locations.organisational_location_id')
                    ->whereColumn('documents.document_family_id', 'document_families.id')
                    ->where('organisational_locations.public_id', $id));
            })
            ->when($filters['review_status'] ?? null, function (Builder $query, string $state): void {
                match ($state) {
                    'unassigned' => $query->whereNull('owner_user_id'),
                    'overdue' => $query->whereDate('review_due_date', '<', today()),
                    default => $query->whereBetween('review_due_date', [today(), today()->addDays(30)]),
                };
            });

        if ($filters['searchable'] ?? false) {
            $query->whereExists(clone $current);
        }
        if (($filters['status'] ?? null) !== null) {
            $query->whereIn('document_families.id', Document::query()->select('document_family_id')->where('status', $filters['status']));
        }
        if (! ($filters['historical'] ?? false)) {
            $query->where(fn (Builder $visible) => $visible->whereExists(clone $current)
                ->orWhereExists(clone $scheduled)->orWhereExists(clone $draft)->orWhereExists(clone $pending));
        }

        return $query;
    }
}
