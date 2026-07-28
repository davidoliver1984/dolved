<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class DocumentUploadException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
    ) {
        parent::__construct($message);
    }

    public static function missingObject(): self
    {
        return new self(
            'The uploaded object could not be verified.',
            409,
        );
    }

    public static function sizeMismatch(): self
    {
        return new self(
            'The uploaded object size does not match the authorised upload.',
            409,
        );
    }

    public static function invalidState(): self
    {
        return new self(
            'The document is not awaiting upload completion.',
            409,
        );
    }

    public static function storageUnavailable(): self
    {
        return new self(
            'Object storage is temporarily unavailable.',
            503,
        );
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], $this->statusCode);
    }

    public function report(): void {}
}
