<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class IngestionAttemptException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $httpStatus,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function invalid(string $code, string $message, int $status = 409): self
    {
        return new self($code, $status, $message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => ['code' => $this->errorCode],
        ], $this->httpStatus);
    }

    public function report(): void {}
}
