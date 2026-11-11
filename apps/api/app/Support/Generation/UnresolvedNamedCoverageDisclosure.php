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
