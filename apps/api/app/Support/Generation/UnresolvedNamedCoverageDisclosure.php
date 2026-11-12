<?php

declare(strict_types=1);

namespace App\Support\Generation;

/** Disclosure only: unresolved named applicability must not block retrieval. */
final class UnresolvedNamedCoverageDisclosure
{
    /** @param list<array<string, mixed>> $evidenceNeeds */
    public static function append(string $text, array $evidenceNeeds): string
    {
        foreach ($evidenceNeeds as $need) {
            $location = $need['resolved_applicability_location'] ?? null;
            $classifications = $need['applicability_reference_classifications'] ?? [];
            // Only positive application-owned non-site matches suppress the notice.
            // Empty/old metadata and mixed unresolved references keep the safety net.
            if ($classifications !== [] && is_array($classifications)
                && array_all($classifications, fn ($item): bool => is_array($item)
                    && in_array($item['kind'] ?? null, ['organisation', 'document'], true))) {
                continue;
            }
            if (($need['operation'] ?? null) === 'scope_enquiry'
                || ($need['applicability']['kind'] ?? null) !== 'named'
                || ($need['retrieval_outcome'] ?? null) !== 'evidence_found'
                || ! is_array($location)
                || ! array_key_exists('resolved_location_public_id', $location)
                || $location['resolved_location_public_id'] !== null) {
                continue;
            }

            return $text."\n\nLocation coverage not established\n\nThe named location in your question could not be matched to a registered site. The guidance above is general; its applicability to that location has not been established.";
        }

        return $text;
    }
}
