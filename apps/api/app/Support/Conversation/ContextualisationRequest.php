<?php

declare(strict_types=1);

namespace App\Support\Conversation;

final readonly class ContextualisationRequest
{
    /** @param list<array{user_message: string, assistant_message: string, assistant_kind: string, user_ordinal: int, assistant_ordinal: int}> $history */
    public function __construct(
        public string $requestId,
        public string $workspaceId,
        public string $currentMessage,
        public array $history,
        public string $contextPolicyVersion,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'contract_version' => 1,
            'request_id' => $this->requestId,
            'workspace_id' => $this->workspaceId,
            'current_message' => $this->currentMessage,
            'history' => $this->history,
            'context_policy_version' => $this->contextPolicyVersion,
        ];
    }
}
