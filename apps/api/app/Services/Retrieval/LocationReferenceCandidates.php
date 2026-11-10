<?php

declare(strict_types=1);

namespace App\Services\Retrieval;

use App\Models\OrganisationalLocation;
use Illuminate\Support\Collection;

final readonly class LocationReferenceCandidates
{
    public function stem(OrganisationalLocation $location): ?string
    {
        $name = mb_strtolower(trim($location->name));
        $descriptor = mb_strtolower(trim($location->reference_descriptor ?? ''));
        // Recheck even if data bypassed model validation. Never infer a designation.
        if ($descriptor === '' || ! str_ends_with($name, ' '.$descriptor)) {
            return null;
        }

        return substr($name, 0, -strlen(' '.$descriptor));
    }

    /** @return Collection<int, OrganisationalLocation> */
    public function shortened(int $workspaceId, string $reference): Collection
    {
        return OrganisationalLocation::where('workspace_id', $workspaceId)->get()
            ->filter(fn (OrganisationalLocation $location): bool => $this->stem($location) === $reference)->values();
    }

}
