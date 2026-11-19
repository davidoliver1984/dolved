<?php

declare(strict_types=1);

namespace App\Support\Dify;

final readonly class DifyChatResult
{
    /** @param list<array<string, mixed>> $retrievedSources @param array<string, mixed> $usage */
    public function __construct(
        public string $answer,
        public string $conversationId,
        public string $messageId,
        public ?string $taskId,
        public array $retrievedSources,
        public array $usage,
    ) {}
}
