<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

final class LegacyUploadCutoverException extends RuntimeException
{
    public static function routeClosed(): self
    {
        return new self('The legacy upload route is closed. Use the import workflow.');
    }

    public static function markerConflict(): self
    {
        return new self('The legacy upload identity could not be verified.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 410);
    }

    public function report(): void {}
}
