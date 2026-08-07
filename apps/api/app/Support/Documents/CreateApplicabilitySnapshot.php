<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Enums\DocumentApplicabilityScope;
use App\Exceptions\DocumentGovernanceException;
use App\Models\Document;
use App\Models\DocumentApplicabilitySnapshot;
use App\Models\OrganisationalLocation;

final class CreateApplicabilitySnapshot
{
    /** @param array<int, OrganisationalLocation> $locations */
    public function create(Document $document, array $locations): DocumentApplicabilitySnapshot
    {
        $locationIds = collect($locations)->map(function (OrganisationalLocation $location) use ($document): int {
            if ($location->workspace_id !== $document->workspace_id) {
                throw new DocumentGovernanceException('Applicability locations must belong to the document workspace.');
            }

            return $location->id;
        })->unique()->values();

        $snapshot = new DocumentApplicabilitySnapshot([
            'scope' => $locationIds->isEmpty()
                ? DocumentApplicabilityScope::Universal
                : DocumentApplicabilityScope::Specific,
        ]);
        $snapshot->workspace_id = $document->workspace_id;
        $snapshot->document_id = $document->id;
        $snapshot->save();

        if ($locationIds->isNotEmpty()) {
            $snapshot->locations()->attach($locationIds->mapWithKeys(
                fn (int $id): array => [$id => ['workspace_id' => $document->workspace_id]],
            ));
        }

        $snapshot->sealed_at = now();
        $snapshot->save();

        return $snapshot->load('locations');
    }
}
