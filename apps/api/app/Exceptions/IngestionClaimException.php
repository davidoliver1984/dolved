<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class IngestionClaimException extends RuntimeException
{
    private function __construct(
        public readonly string $errorCode,
        public readonly int $httpStatus,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function invalidContract(): self
    {
        return new self(
            'invalid_event_contract',
            422,
            'The ingestion event does not match the supported contract.',
        );
    }

    public static function eventIdentityMismatch(): self
    {
        return new self(
            'event_identity_mismatch',
            409,
            'The ingestion event identifiers do not agree.',
        );
    }

    public static function eventIdentityReused(): self
    {
        return new self(
            'event_identity_reused',
            409,
            'The ingestion event identifier was already used by another event.',
        );
    }

    public static function unknownDocument(): self
    {
        return new self(
            'unknown_document',
            404,
            'The ingestion event does not identify an available document.',
        );
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => [
                'code' => $this->errorCode,
            ],
        ], $this->httpStatus);
    }

    public function report(): void {}
}
