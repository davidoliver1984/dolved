<?php

declare(strict_types=1);

namespace App\Queries\Documents;

use App\Enums\DocumentFamilyDeletionStatus;
use App\Models\DocumentFamily;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;

final class GetWorkspaceKnowledgeReadiness
{
    public function __construct(private BuildCurrentDocumentForFamily $currentDocument) {}

    public function count(Workspace $workspace): int
    {
        return $this->query($workspace)->distinct()->count('document_families.id');
    }

    /** @return list<array{family_public_id: string, question: string}> */
    public function starterQuestions(Workspace $workspace, int $limit = 3): array
    {
        return $this->query($workspace)
            ->whereRaw("TRIM(document_families.name) <> ''")
            ->orderBy('document_families.name')
            ->orderBy('document_families.public_id')
            ->limit($limit)
            ->get(['document_families.public_id', 'document_families.name'])
            ->map(fn (DocumentFamily $family): array => [
                'family_public_id' => $family->public_id,
                'question' => "What are the key points in {$family->name}?",
            ])
            ->values()
            ->all();
    }

    /** @return Builder<DocumentFamily> */
    private function query(Workspace $workspace): Builder
    {
        return DocumentFamily::query()
            ->where('document_families.workspace_id', $workspace->id)
            ->whereNull('document_families.tombstoned_at')
            ->whereDoesntHave('deletionOperations', fn (Builder $delete): Builder => $delete->whereIn('status', [
                DocumentFamilyDeletionStatus::Pending->value,
                DocumentFamilyDeletionStatus::Processing->value,
                DocumentFamilyDeletionStatus::PartiallyFailed->value,
            ]))
            ->whereExists($this->currentDocument->handle());
    }
}
