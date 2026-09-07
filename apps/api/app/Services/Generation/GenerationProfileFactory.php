<?php

declare(strict_types=1);

namespace App\Services\Generation;

use App\Enums\GenerationDeliveryMode;
use App\Exceptions\GenerationException;
use App\Support\Generation\GenerationProfile;

final class GenerationProfileFactory
{
    public function needsPin(string $workspacePublicId): ?array
    {
        if (! in_array($workspacePublicId, config('conversation.needs_workspace_ids', []), true)) {
            return null;
        }

        return $this->needsIdentity();
    }

    public function pinnedNeeds(array $pin): GenerationProfile
    {
        if ($this->canonical($pin) !== $this->canonical($this->needsIdentity())) {
            throw new GenerationException('The pinned needs execution profile no longer matches deployment configuration.');
        }

        return $this->configuredNeeds();
    }

    private function needsIdentity(): array
    {
        return ['execution_version' => 'bounded-needs-slice-v12',
            'planner_contract' => 'evidence-needs-plan-v2',
            'planner_provider' => config('retrieval.planner.provider'),
            'planner_model' => config('retrieval.planner.model'),
            'planner_prompt' => 'bounded-evidence-needs-v12',
            'planner_adapter' => 'structured-chat-needs-v5',
            'generation' => json_decode(json_encode($this->configuredNeeds(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR),
            'generation_context_policy' => 'whole-needs-evidence-v1',
            'generation_max_context_characters' => config('generation.max_context_characters'),
            'context_policy' => config('conversation.context_policy_version'),
            'context_turn_limit' => config('conversation.context_turn_limit'),
            'context_max_characters' => config('conversation.context_max_characters'),
            'candidate_k' => config('conversation.candidate_k')];
    }

    private function canonical(array $value): array
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->canonical($item);
            }
        }
        unset($item);
        ksort($value);

        return $value;
    }

    public function configuredNeeds(): GenerationProfile
    {
        $base = $this->configured();

        return new GenerationProfile($base->provider, $base->model, 'generation-result-v2',
            'grounded-generation-needs-v5', 'openai-responses-needs-v2',
            GenerationDeliveryMode::CompleteResultOnly, $base->qualityAffectingConfiguration);
    }

    public function configured(): GenerationProfile
    {
        /** @var array<string, mixed> $configuration */
        $configuration = config('generation.quality_affecting_configuration');

        return new GenerationProfile(
            (string) config('generation.provider'),
            (string) config('generation.model'),
            (string) config('generation.contract_version'),
            (string) config('generation.prompt_version'),
            (string) config('generation.adapter_version'),
            GenerationDeliveryMode::from((string) config('generation.delivery_mode')),
            $configuration,
        );
    }
}
