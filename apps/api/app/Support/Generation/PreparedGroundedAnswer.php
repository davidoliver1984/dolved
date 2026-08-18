<?php

declare(strict_types=1);

namespace App\Support\Generation;

final readonly class PreparedGroundedAnswer
{
    /** @param array{fingerprint_scheme_version: int, generation_fingerprint: string, snapshot: array<string, mixed>} $fingerprint */
    public function __construct(
        public GenerationRequest $request,
        public GenerationResult $result,
        public array $fingerprint,
        public string $correlationId,
    ) {}
}
