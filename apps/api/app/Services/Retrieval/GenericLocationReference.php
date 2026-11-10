<?php

declare(strict_types=1);

namespace App\Services\Retrieval;

/** Closed, whole-phrase-only shadow classification; never an eligibility decision. */
final class GenericLocationReference
{
    public const VERSION = 'generic-location-reference-v1';

    private const PHRASES = [
        'we',
        'us',
        'the organisation',
        'the team',
        'the service',
        'the department',
        'the site',
    ];

    public static function matches(string $reference): bool
    {
        return in_array(mb_strtolower(trim($reference), 'UTF-8'), self::PHRASES, true);
    }

    public static function groundedInQuestion(string $reference, string $question): bool
    {
        return self::matches($reference)
            && preg_match('/(?<![\pL\pN])'.preg_quote(trim($reference), '/').'(?![\pL\pN])/u', $question) === 1;
    }
}
