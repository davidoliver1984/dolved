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
        if (in_array($workspacePublicId, config('conversation.current_policy_workspace_ids', []), true)) {
            return $this->currentPolicyPin();
        }
        if (! in_array($workspacePublicId, config('conversation.needs_workspace_ids', []), true)) {
            return null;
        }

        return $this->needsIdentity();
    }

    public function pinnedNeeds(array $pin): GenerationProfile
    {
        if (($pin['product_profile'] ?? null) === 'current-policy-pool-v1') {
            $this->validateCurrentPolicyPin($pin);
            throw new GenerationException('Current-policy execution is not enabled until its product pipeline is complete.');
        }
        if ($this->canonical($pin) !== $this->canonical($this->needsIdentity())) {
            throw new GenerationException('The pinned needs execution profile no longer matches deployment configuration.');
        }

        return $this->configuredNeeds();
    }

    private function needsIdentity(): array
    {
        return ['execution_version' => 'bounded-needs-slice-v24',
            'coverage_selection' => 'grounded-coverage-selection-v1',
            'support_review_contract' => 'need-source-support-v2',
            'support_review_output_tokens' => 17408,
            'scope_renderer' => 'scope-fact-renderer-v2',
            'scope_answer_contract' => 'scope-composed-answer-v2',
            'planner_contract' => 'evidence-needs-plan-v4',
            'planner_provider' => config('retrieval.planner.provider'),
            'planner_model' => config('retrieval.planner.model'),
            'planner_prompt' => 'bounded-evidence-needs-v27',
            'planner_adapter' => 'structured-chat-needs-v12',
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

        return new GenerationProfile($base->provider, $base->model, 'generation-result-v3',
            'grounded-generation-needs-v15', 'openai-responses-needs-v7',
            GenerationDeliveryMode::CompleteResultOnly, $base->qualityAffectingConfiguration);
    }

    public function currentPolicyPin(): array
    {
        return ['product_profile' => 'current-policy-pool-v1',
            'execution_version' => 'current-policy-stop3-v1',
            'generation_contract' => 'current-policy-answer-v1',
            'generation_prompt' => 'current-policy-pool-generation-v1',
            'generation_adapter' => 'current-policy-responses-v2',
            'generation_provider' => config('generation.provider'),
            'generation_model' => config('generation.model'),
            'generation_output_tokens' => 4096,
            'generation_max_requests' => 1,
            'generation_max_retries' => 0,
            'generation_reasoning_effort' => config('generation.quality_affecting_configuration.reasoning_effort'),
            'generation_source_characters' => 60000,
            'generation_request_units' => 120000,
            'generation_framing_units' => 4096,
            'planner_contract' => 'current-policy-needs-v1',
            'planner_prompt' => 'current-policy-scope-v1',
            'planner_adapter' => 'structured-current-policy-v3',
            'planner_provider' => config('retrieval.planner.provider'),
            'planner_model' => config('retrieval.planner.model'),
            'context_policy' => config('conversation.context_policy_version'),
            'context_turn_limit' => config('conversation.context_turn_limit'),
            'context_max_characters' => config('conversation.context_max_characters'),
            'candidate_k' => config('conversation.candidate_k'),
            'scope_disposition' => 'current-policy-disposition-v2'];
    }

    public function validateCurrentPolicyPin(array $pin): void
    {
        if ($this->canonical($pin) !== $this->canonical($this->currentPolicyPin())) {
            throw new GenerationException('The pinned current-policy profile no longer matches deployment configuration.');
        }
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
