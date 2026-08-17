<?php

declare(strict_types=1);

namespace App\Support\Generation;

use App\Enums\GenerationSide;

final readonly class GenerationRequest
{
    /** @param list<GenerationEvidence> $evidence @param list<GenerationSide> $requiredSides */
    public function __construct(
        public string $requestId,
        public string $workspacePublicId,
        public string $question,
        public array $evidence,
        public string $contextPolicyVersion,
        public int $maxContextCharacters,
        public array $requiredSides,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'contract_version' => 1,
            'request_id' => $this->requestId,
            'workspace_id' => $this->workspacePublicId,
            'question' => $this->question,
            'evidence' => array_map(fn (GenerationEvidence $item): array => $item->toArray(), $this->evidence),
            'constraints' => [
                'context_policy_version' => $this->contextPolicyVersion,
                'max_context_characters' => $this->maxContextCharacters,
                'required_sides' => array_map(fn (GenerationSide $side): string => $side->value, $this->requiredSides),
            ],
        ];
    }
}
