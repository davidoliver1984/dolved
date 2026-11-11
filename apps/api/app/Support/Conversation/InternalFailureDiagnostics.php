<?php

declare(strict_types=1);

namespace App\Support\Conversation;

use Illuminate\Support\Str;
use Throwable;

final class InternalFailureDiagnostics
{
    /** @return array{exception_class: string, message: string} */
    public static function from(Throwable $exception): array
    {
        $message = preg_replace(
            '/(password|secret|token|credential|access[_ -]?key)\s*[=:]\s*\S+/i',
            '$1=[redacted]',
            $exception->getMessage(),
        ) ?? 'Exception message unavailable.';
        $message = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $message)
            ?? 'Exception message unavailable.';
        $message = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '[redacted-email]', $message)
            ?? 'Exception message unavailable.';

        return [
            'exception_class' => Str::limit($exception::class, 200, ''),
            'message' => Str::limit(trim($message), 600, ''),
        ];
    }
}
