<?php

declare(strict_types=1);

namespace App\Queries\Documents;

use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentFamily;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PaginateDocumentLibrary
{
    public function __construct(
        private BuildCurrentDocumentForFamily $currentDocument,
        private BuildDocumentFamilyLibraryQuery $libraryQuery,
    ) {}

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

        $query = $this->libraryQuery->handle($workspace, $filters)
            ->select('document_families.*')
            ->selectRaw('COALESCE(document_family_activity_summary.last_meaningful_update, document_families.created_at) AS last_meaningful_update')
            ->selectSub($current, 'current_document_id')
            ->selectSub($scheduled, 'scheduled_effective_from')
            ->selectSub($pendingStatus, 'pending_status')
            ->selectSub(Document::query()->selectRaw('COUNT(*)')->whereColumn('document_family_id', 'document_families.id'), 'version_count')
            ->selectSub(Document::query()->selectRaw('COUNT(*)')->whereColumn('document_family_id', 'document_families.id')->where('governance_status', DocumentGovernanceStatus::Draft->value), 'draft_count')
            ->leftJoin('document_family_activity_summary', 'document_family_activity_summary.family_id', '=', 'document_families.id')
            ->with(['category', 'owner', 'tags']);

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
