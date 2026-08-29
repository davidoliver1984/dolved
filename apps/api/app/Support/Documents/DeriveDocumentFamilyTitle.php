<?php

declare(strict_types=1);

namespace App\Support\Documents;

final class DeriveDocumentFamilyTitle
{
    public static function fromSourceFilename(string $sourceFilename): string
    {
        $basename = basename(str_replace('\\', '/', trim($sourceFilename)));
        $extension = pathinfo($basename, PATHINFO_EXTENSION);
        $title = $extension === ''
            ? $basename
            : mb_substr($basename, 0, -1 * (mb_strlen($extension) + 1));
        $title = preg_replace('/[_-]+/u', ' ', $title) ?? $title;
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;
        $title = trim($title);

        return $title === '' ? $basename : $title;
    }
}
