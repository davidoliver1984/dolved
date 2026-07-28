<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

class CorrelationId
{
    public static function resolve(?string $candidate): string
    {
        if (
            is_string($candidate)
            && Str::isUuid($candidate)
        ) {
            return strtolower($candidate);
        }

        return (string) Str::uuid();
    }
}
