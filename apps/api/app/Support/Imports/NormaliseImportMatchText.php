<?php

declare(strict_types=1);

namespace App\Support\Imports;

use InvalidArgumentException;
use Normalizer;

final class NormaliseImportMatchText
{
    public function sourceFilename(string $value): string
    {
        if ($this->containsControlCharacters($value)) {
            throw new InvalidArgumentException('The source filename contains unsupported control characters.');
        }
        $basename = basename(str_replace('\\', '/', $value));

        return $this->normalise((string) pathinfo($basename, PATHINFO_FILENAME));
    }

    public function familyTitle(string $value): string
    {
        if ($this->containsControlCharacters($value)) {
            return '';
        }

        return $this->normalise($value);
    }

    private function normalise(string $value): string
    {
        $normalised = Normalizer::normalize($value, Normalizer::FORM_C);
        $normalised = is_string($normalised) ? $normalised : $value;
        $normalised = mb_convert_case($normalised, MB_CASE_FOLD, 'UTF-8');
        $normalised = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalised) ?? '';
        $normalised = preg_replace('/\s+/u', ' ', trim($normalised)) ?? '';

        return mb_substr(
            $normalised,
            0,
            (int) config('imports.matching.maximum_normalised_characters'),
            'UTF-8',
        );
    }

    private function containsControlCharacters(string $value): bool
    {
        return preg_match('/[\p{Cc}\p{Cf}]/u', $value) === 1;
    }
}
