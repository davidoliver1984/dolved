<?php

declare(strict_types=1);

namespace App\Support\Documents;

final class SafeDocumentSourceUrl
{
    public static function accepts(string $value): bool
    {
        if (mb_strlen($value) > 2048 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1 || str_starts_with($value, 'workspaces/')) {
            return false;
        }

        $parts = parse_url($value);

        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && isset($parts['host'])
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['query'])
            && ! isset($parts['fragment'])
            && preg_match('#^/workspaces/[^/]+/documents/#', (string) ($parts['path'] ?? '')) !== 1;
    }
}
