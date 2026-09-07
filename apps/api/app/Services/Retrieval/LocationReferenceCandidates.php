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

    /** @param list<string> $references */
    public function question(int $workspaceId, array $references): ?string
    {
        $matches = OrganisationalLocation::where('workspace_id', $workspaceId)->with('aliases')->get()
            ->filter(function (OrganisationalLocation $location) use ($references): bool {
                $names = [mb_strtolower(trim($location->name)), $this->stem($location), ...$location->aliases->pluck('normalised_alias')->all()];
                foreach ($references as $reference) {
                    $reference = mb_strtolower(trim($reference));
                    foreach ($names as $name) {
                        if ($name === null || $reference === '') {
                            continue;
                        }
                        // Suggestions only: whole-word prefix or one character edit. No fuzzy resolution.
                        if ($name === $reference || (mb_strlen($reference) >= 4 &&
                            (str_starts_with($name, $reference.' ') || levenshtein($reference, $name) <= 1))) {
                            return true;
                        }
                    }
                }

                return false;
            })->sortBy('name')->values();
        if ($matches->isEmpty()) {
            return null;
        }

        return 'Which organisational location do you mean: '.implode('; ', $matches->map(
            fn (OrganisationalLocation $location): string => $location->name.' ('.$location->kind.')',
        )->all()).'?';
    }
}
