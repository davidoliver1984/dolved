<?php

declare(strict_types=1);

namespace App\Queries\Documents;

use App\Enums\DocumentFamilyDeletionStatus;
use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PaginateDocumentLibrary
{
    public function __construct(private BuildCurrentDocumentForFamily $currentDocument) {}

    /** @param array<string, mixed> $filters */
    public function handle(Workspace $workspace, array $filters): LengthAwarePaginator
    {
        $authorityStart = DB::getDriverName() === 'pgsql'
            ? 'GREATEST(effective_from, approved_at)'
            : 'CASE WHEN effective_from > approved_at THEN effective_from ELSE approved_at END';
        $now = now();
        $current = $this->currentDocument->handle();
        $scheduled = Document::query()->select('effective_from')
            ->whereColumn('document_family_id', 'document_families.id')
            ->where('governance_status', DocumentGovernanceStatus::Approved->value)
            ->whereNotNull('approved_at')
            ->whereRaw("{$authorityStart} > ?", [$now])
            ->orderByRaw("{$authorityStart} ASC")->orderBy('id')->limit(1);
        $pendingStatus = Document::query()->select('status')
            ->whereColumn('document_family_id', 'document_families.id')
            ->whereIn('status', [
                DocumentStatus::Uploading->value,
                DocumentStatus::Uploaded->value,
                DocumentStatus::Queued->value,
                DocumentStatus::Processing->value,
                DocumentStatus::Failed->value,
            ])->orderByDesc('created_at')->orderByDesc('id')->limit(1);
        $draft = Document::query()->select('id')
            ->whereColumn('document_family_id', 'document_families.id')
            ->where('governance_status', DocumentGovernanceStatus::Draft->value)
            ->limit(1);

        $query = DocumentFamily::query()
            ->select('document_families.*')
            ->selectRaw('COALESCE(document_family_activity_summary.last_meaningful_update, document_families.created_at) AS last_meaningful_update')
            ->selectSub($current, 'current_document_id')
            ->selectSub($scheduled, 'scheduled_effective_from')
            ->selectSub($pendingStatus, 'pending_status')
            ->selectSub(Document::query()->selectRaw('COUNT(*)')->whereColumn('document_family_id', 'document_families.id'), 'version_count')
            ->selectSub(Document::query()->selectRaw('COUNT(*)')->whereColumn('document_family_id', 'document_families.id')->where('governance_status', DocumentGovernanceStatus::Draft->value), 'draft_count')
            ->leftJoin('document_family_activity_summary', 'document_family_activity_summary.family_id', '=', 'document_families.id')
            ->where('document_families.workspace_id', $workspace->id)
            ->whereNull('document_families.tombstoned_at')
            ->whereDoesntHave('deletionOperations', fn (Builder $delete): Builder => $delete->whereIn('status', [
                DocumentFamilyDeletionStatus::Pending->value,
                DocumentFamilyDeletionStatus::Processing->value,
                DocumentFamilyDeletionStatus::PartiallyFailed->value,
            ]))
            ->with(['category', 'owner', 'tags'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $needle = '%'.mb_strtolower($search).'%';
                $query->where(fn (Builder $match): Builder => $match
                    ->whereRaw('LOWER(document_families.name) LIKE ?', [$needle])
                    ->orWhereExists(fn ($documents) => $documents->selectRaw('1')->from('documents')
                        ->whereColumn('documents.document_family_id', 'document_families.id')
                        ->whereRaw('LOWER(documents.source_filename) LIKE ?', [$needle])));
            })
            ->when($filters['category'] ?? null, fn (Builder $query, string $id): Builder => $query->whereHas('category', fn (Builder $category): Builder => $category->where('public_id', $id)))
            ->when($filters['owner'] ?? null, fn (Builder $query, string $id): Builder => $query->whereHas('owner', fn (Builder $owner): Builder => $owner->where('public_id', $id)))
            ->when($filters['applicability'] ?? null, function (Builder $query, string $id): void {
                $query->whereExists(fn ($locations) => $locations->selectRaw('1')
                    ->from('documents')
                    ->join('document_applicability_snapshots', 'document_applicability_snapshots.document_id', '=', 'documents.id')
                    ->join('document_applicability_locations', 'document_applicability_locations.document_applicability_snapshot_id', '=', 'document_applicability_snapshots.id')
                    ->join('organisational_locations', 'organisational_locations.id', '=', 'document_applicability_locations.organisational_location_id')
                    ->whereColumn('documents.document_family_id', 'document_families.id')
                    ->where('organisational_locations.public_id', $id));
            })
            ->when($filters['review_status'] ?? null, function (Builder $query, string $state): void {
                if ($state === 'unassigned') {
                    $query->whereNull('owner_user_id');
                } elseif ($state === 'overdue') {
                    $query->whereDate('review_due_date', '<', today());
                } else {
                    $query->whereBetween('review_due_date', [today(), today()->addDays(30)]);
                }
            });

        if ($filters['searchable'] ?? false) {
            $query->whereExists(clone $current);
        }

        if (($filters['status'] ?? null) !== null) {
            $query->whereIn('document_families.id', Document::query()->select('document_family_id')->where('status', $filters['status']));
        }
        if (! ($filters['historical'] ?? false)) {
            $query->where(function (Builder $visible) use ($current, $draft, $pendingStatus, $scheduled): void {
                $visible->whereExists(clone $current)
                    ->orWhereExists(clone $scheduled)
                    ->orWhereExists(clone $draft)
                    ->orWhereExists(clone $pendingStatus);
            });
        }

        $sort = match ($filters['sort']) {
            'title' => 'document_families.name',
            'review_due_date' => 'document_families.review_due_date',
            default => 'last_meaningful_update',
        };
        $direction = $filters['direction'];
        $query->orderBy($sort, $direction)->orderBy('document_families.public_id');
        $page = $query->paginate((int) $filters['per_page'], page: (int) $filters['page']);

        /** @var Collection<int, int> $currentIds */
        $currentIds = collect($page->items())->pluck('current_document_id')->filter()->map(fn ($id): int => (int) $id);
        $currentDocuments = Document::query()->with('activeExtractionProjectionGeneration')->whereKey($currentIds)->get()->keyBy('id');
        collect($page->items())->each(function (DocumentFamily $family) use ($currentDocuments): void {
            $family->setRelation('currentDocument', $currentDocuments->get((int) $family->getAttribute('current_document_id')));
        });

        return $page;
    }
}
