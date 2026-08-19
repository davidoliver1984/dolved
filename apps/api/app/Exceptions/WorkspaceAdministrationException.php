<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class WorkspaceAdministrationException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $httpStatus,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function conflict(string $code, string $message): self
    {
        return new self($code, 409, $message);
    }

    public static function unavailable(): self
    {
        return new self('invitation_unavailable', 404, 'The invitation is unavailable or cannot be accepted.');
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
