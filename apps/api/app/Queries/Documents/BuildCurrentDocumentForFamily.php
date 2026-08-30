<?php

declare(strict_types=1);

namespace App\Queries\Documents;

use App\Enums\DocumentGovernanceStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class BuildCurrentDocumentForFamily
{
    /** @return Builder<Document> */
    public function handle(string $familyColumn = 'document_families.id'): Builder
    {
        $authorityStart = DB::getDriverName() === 'pgsql'
            ? 'GREATEST(effective_from, approved_at)'
            : 'CASE WHEN effective_from > approved_at THEN effective_from ELSE approved_at END';
        $successorAuthorityStart = DB::getDriverName() === 'pgsql'
            ? 'GREATEST(successors.effective_from, successors.approved_at)'
            : 'CASE WHEN successors.effective_from > successors.approved_at THEN successors.effective_from ELSE successors.approved_at END';
        $candidateAuthorityStart = DB::getDriverName() === 'pgsql'
            ? 'GREATEST(documents.effective_from, documents.approved_at)'
            : 'CASE WHEN documents.effective_from > documents.approved_at THEN documents.effective_from ELSE documents.approved_at END';
        $now = now();

        return Document::query()->select('id')
            ->whereColumn('document_family_id', $familyColumn)
            ->where('status', DocumentStatus::Indexed->value)
            ->where('governance_status', DocumentGovernanceStatus::Approved->value)
            ->whereNotNull('approved_at')
            ->whereRaw("{$authorityStart} <= ?", [$now])
            ->where(fn (Builder $query): Builder => $query->whereNull('withdrawn_at')->orWhere('withdrawn_at', '>', $now))
            ->whereNotExists(function ($successors) use ($candidateAuthorityStart, $now, $successorAuthorityStart): void {
                $successors->selectRaw('1')->from('documents as successors')
                    ->whereColumn('successors.document_family_id', 'documents.document_family_id')
                    ->whereIn('successors.governance_status', [DocumentGovernanceStatus::Approved->value, DocumentGovernanceStatus::Withdrawn->value])
                    ->whereNotNull('successors.approved_at')
                    ->whereRaw("{$successorAuthorityStart} <= ?", [$now])
                    ->whereRaw("{$successorAuthorityStart} > {$candidateAuthorityStart}")
                    ->where(fn ($attained) => $attained->whereNull('successors.withdrawn_at')->orWhereRaw("successors.withdrawn_at >= {$successorAuthorityStart}"));
            })
            ->orderByRaw("{$authorityStart} DESC")
            ->orderByDesc('id')
            ->limit(1);
    }
}
