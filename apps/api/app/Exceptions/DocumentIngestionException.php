<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\DocumentStatus;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class DocumentIngestionException extends RuntimeException
{
    public static function invalidState(DocumentStatus $status): self
    {
        return new self(sprintf(
            'A document in the %s state cannot be requested for ingestion.',
            $status->value,
        ));
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 409);
    }

    public function report(): void {}
}
