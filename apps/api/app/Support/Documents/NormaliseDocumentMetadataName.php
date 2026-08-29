<?php

declare(strict_types=1);

namespace App\Support\Documents;

use Normalizer;

final class NormaliseDocumentMetadataName
{
    public static function handle(string $value): string
    {
        $normalised = Normalizer::normalize($value, Normalizer::FORM_C);
        $normalised = is_string($normalised) ? $normalised : $value;
        $normalised = preg_replace('/\s+/u', ' ', trim($normalised)) ?? trim($normalised);

        return mb_strtolower($normalised, 'UTF-8');
    }
}
