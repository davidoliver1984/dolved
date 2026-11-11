<?php

declare(strict_types=1);

namespace App\Actions\Generation;

use App\Enums\GenerationDeliveryMode;
use App\Enums\GenerationOutcome;
use App\Enums\RetrievalOutcome;
use App\Exceptions\GenerationException;
use App\Exceptions\GenerationProviderException;
use App\Exceptions\GenerationServiceAuthenticationException;
use App\Models\GeneratedAnswer;
use App\Services\Generation\AssembleGenerationRequest;
use App\Services\Generation\GenerationClient;
use App\Services\Generation\GenerationFingerprint;
use App\Services\Generation\ValidateAnswerPartCandidate;
use App\Services\Generation\ValidateGenerationResult;
use App\Support\Generation\AspectCoverage;
use App\Support\Generation\GenerationAssemblyInput;
use App\Support\Generation\GenerationProfile;
use App\Support\Generation\GenerationRequest;
use App\Support\Generation\GenerationResult;
use App\Support\Generation\GenerationValidationDiagnostics;
use App\Support\Generation\PreparedGroundedAnswer;
use App\Support\Generation\ValidationAttemptUsage;
use App\Support\Retrieval\RetrievalResult;
use Illuminate\Support\Str;

final readonly class GenerateGroundedAnswer
{
    public function __construct(
        private AssembleGenerationRequest $assembler,
        private GenerationClient $client,
        private ValidateGenerationResult $validator,
        private ValidateAnswerPartCandidate $candidateValidator,
        private GenerationFingerprint $fingerprints,
        private PersistGeneratedAnswer $persistence,
    ) {}

    public function handle(GenerationAssemblyInput $input, GenerationProfile $profile): GeneratedAnswer
    {
        $prepared = $this->prepare($input, $profile);

        return $this->persistence->handle(
            $input->authorisedScope,
            $input->question,
            $prepared->correlationId,
            $prepared->request,
            $prepared->result,
            $prepared->fingerprint,
        );
    }

    /** @param null|callable(string): void $observeStage @param null|callable(\App\Support\Generation\AnswerPartResult, GenerationRequest): void $observeCandidate */
    public function prepare(GenerationAssemblyInput $input, GenerationProfile $profile, ?callable $observeStage = null, ?callable $observeCandidate = null): PreparedGroundedAnswer
    {
        if (array_filter($input->retrievalResult->evidenceNeeds, fn ($need) => $need['operation'] === 'scope_enquiry') !== []) {
            return $this->prepareScopeAnswer($input, $profile, $observeStage, $observeCandidate);
        }
        $observeStage?->__invoke('preparing_evidence');
        $request = $this->assembler->handle($input);
        $initialRequestId = $request->requestId;
        if ($request->evidenceNeeds !== [] && ($profile->contractVersion !== 'generation-result-v3'
            || $profile->promptVersion !== 'grounded-generation-needs-v9'
            || $profile->adapterVersion !== 'openai-responses-needs-v4'
            || $profile->deliveryMode !== GenerationDeliveryMode::CompleteResultOnly)) {
            throw new GenerationException('Needs generation requires the pinned v2 complete-result profile.');
        }
        $observeStage?->__invoke('generating');
        $receipts = [];
        $completedEntailmentRejections = 0;
        for ($validationAttempt = 1; $validationAttempt <= 2; $validationAttempt++) {
            $payload = null;
            try {
                $payload = $profile->deliveryMode === GenerationDeliveryMode::StreamingParts
                    ? $this->client->stream(
                        $input->authorisedScope->workspace,
                        $request,
                        function (array $candidate) use ($request, $observeCandidate): void {
                            $observeCandidate?->__invoke($this->candidateValidator->handle($request, $candidate), $request);
                        },
                    )
                    : $this->client->generate($input->authorisedScope->workspace, $request);
                $result = $request->evidenceNeeds === [] ? $this->validator->handle($request, $payload)
                    : $this->validator->handleNeeds($request, array_column($request->evidenceNeeds, 'need_id'), $payload);

                $receipt = ValidationAttemptUsage::receipt($result->usage);
                $receipt['outcome'] = 'accepted';
                $receipt['request_id'] = $request->requestId;
                $receipt['retry_of_request_id'] = $validationAttempt === 2 ? $initialRequestId : null;
                $receipts[] = $receipt;
                $result = new GenerationResult($result->outcome, $result->answerParts,
                    $result->unsupportedAspects, $result->insufficiencyReason,
                    array_replace($result->usage ?? [], ValidationAttemptUsage::combine($receipts)),
                    $result->aspectLinks, $result->aspectResults);
                break;
            } catch (GenerationException $exception) {
                $providerFailure = $exception instanceof GenerationProviderException;
                $diagnostic = $providerFailure ? $exception->safeDiagnostics()
                    : GenerationValidationDiagnostics::local($exception);
                $receipt = ValidationAttemptUsage::receipt($providerFailure
                    ? $exception->providerUsage : (is_array($payload) ? ($payload['usage'] ?? null) : null));
                if ($providerFailure) {
                    $receipt['request_count'] = is_int($receipt['request_count'] ?? null)
                        ? $receipt['request_count'] : $exception->attemptCount;
                    $receipt['latency_ms'] ??= $exception->latencyMs;
                }
                if ($exception instanceof GenerationServiceAuthenticationException) {
                    // Authentication rejected before any provider attempt.
                    $receipt = ValidationAttemptUsage::receipt(['request_count' => 0, 'retry_count' => 0,
                        'input_tokens' => 0, 'output_tokens' => 0, 'cached_input_tokens' => 0,
                        'cost_usd' => 0, 'latency_ms' => 0]);
                }
                $receipt['outcome'] = 'rejected';
                $receipt['request_id'] = $request->requestId;
                $receipt['retry_of_request_id'] = $validationAttempt === 2 ? $initialRequestId : null;
                $receipt['validation'] = array_intersect_key($diagnostic, array_flip(['category', 'field', 'code', 'layer', 'http_status', 'ownership']));
                $receipts[] = $receipt;
                $exception->validationAttempts = $receipts;
                if ($providerFailure && $exception->category === 'contract_validation_failure'
                    && $exception->returnedOutput && in_array($diagnostic['code'] ?? null,
                        ['entailment_unsupported', 'entailment_uncertain'], true)) {
                    $completedEntailmentRejections++;
                }
                $eligible = $request->evidenceNeeds !== [] && $profile->deliveryMode === GenerationDeliveryMode::CompleteResultOnly
                    && ($providerFailure
                        ? ($exception->category === 'contract_validation_failure' && $exception->returnedOutput && ($diagnostic['code'] ?? null) !== null)
                        : (is_array($payload) && ($diagnostic['code'] ?? 'unclassified_validation') !== 'unclassified_validation'));
                if ($validationAttempt === 1 && $eligible) {
                    // A rejected completed output needs a new sample, not a replay.
                    // Preserve the exact assembled input; change only rc1 identity.
                    $request = new GenerationRequest(
                        (string) Str::uuid(), $request->workspacePublicId, $request->question,
                        $request->evidence, $request->contextPolicyVersion, $request->maxContextCharacters,
                        $request->requiredSides, $request->requestedEvidenceType, $request->evidenceNeeds,
                    );

                    continue;
                }
                if ($validationAttempt === 2 && $completedEntailmentRejections === 2
                    && $request->evidenceNeeds !== []) {
                    // Both provider outputs were returned, but neither passed entailment review.
                    // Retain the paid attempts and publish no model-authored claim.
                    $gaps = array_column($request->evidenceNeeds, 'aspect');
                    $links = array_map(static fn (int $index, array $need): array => [
                        'need_id' => $need['need_id'], 'answer_part_indices' => [],
                        'unsupported_aspect_indices' => [$index],
                    ], array_keys($request->evidenceNeeds), $request->evidenceNeeds);
                    $reason = "I couldn't find enough available evidence to answer that question.";
                    $usage = [...ValidationAttemptUsage::combine($receipts),
                        'controlled_fallback' => ['category' => 'contract_validation_failure',
                            'code' => $diagnostic['code'], 'layer' => 'python_generation_adapter']];
                    $base = new GenerationResult(GenerationOutcome::InsufficientEvidence, [], $gaps, $reason, $usage);
                    $result = new GenerationResult($base->outcome, $base->answerParts, $base->unsupportedAspects,
                        $base->insufficiencyReason, $base->usage, $links,
                        AspectCoverage::derive($base, array_column($request->evidenceNeeds, 'need_id'), $links));
                    break;
                }
                throw $exception;
            }
        }
        $observeStage?->__invoke('validating');
        $fingerprint = $this->fingerprints->make(
            $profile->provider,
            $profile->model,
            $profile->contractVersion,
            $profile->promptVersion,
            $profile->adapterVersion,
            $profile->qualityAffectingConfiguration,
        );
        $correlationId = (string) ($input->correlationLineage['correlation_id'] ?? $initialRequestId);

        return new PreparedGroundedAnswer($request, $result, $fingerprint, $correlationId);
    }

    private function prepareScopeAnswer(GenerationAssemblyInput $input, GenerationProfile $profile, ?callable $observeStage, ?callable $observeCandidate): PreparedGroundedAnswer
    {
        if ($profile->contractVersion !== 'generation-result-v3'
            || $profile->promptVersion !== 'grounded-generation-needs-v9'
            || $profile->adapterVersion !== 'openai-responses-needs-v4'
            || $profile->deliveryMode !== GenerationDeliveryMode::CompleteResultOnly) {
            throw new GenerationException('Scope composition requires the pinned complete-result needs profile.');
        }
        $facts = [];
        $operational = [];
        foreach ($input->retrievalResult->evidenceNeeds as $need) {
            if ($need['operation'] !== 'scope_enquiry') {
                $operational[] = $need;

                continue;
            }
            if (! isset($need['scope_fact']) || $need['scope_fact']['need_id'] !== $need['need_id']) {
                throw new GenerationException('A scope enquiry requires its resolved scope fact.');
            }
            $facts[] = $need['scope_fact'];
        }
        $candidates = $input->retrievalResult->candidates;
        foreach ($candidates as $candidate) {
            if (! in_array($candidate['need_id'], array_column($operational, 'need_id'), true)) {
                throw new GenerationException('Scope enquiry cannot supply operational chunks.');
            }
        }
        if ($operational !== [] && $candidates !== []) {
            // Do not send the compound question or scope fact to the operational model.
            // Each aspect is the planner's already-grounded standalone request.
            $question = implode("\n", array_column($operational, 'aspect'));
            $retrieval = new RetrievalResult(RetrievalOutcome::EvidenceFound,
                $candidates, evidenceNeeds: $operational);
            $prepared = $this->prepare(new GenerationAssemblyInput($question, $retrieval,
                $input->resolvedTemporalAuthority, $input->resolvedApplicabilityLocation,
                $input->authorisedScope, $input->correlationLineage), $profile, $observeStage, $observeCandidate);
            $request = $prepared->request;
            $base = $prepared->result;
            $fingerprint = $prepared->fingerprint;
            $correlation = $prepared->correlationId;
        } else {
            $request = new GenerationRequest((string) Str::uuid(), $input->authorisedScope->workspace->public_id,
                $input->question, [], 'scope-fact-renderer-v2', 0, []);
            $gaps = array_column($operational, 'aspect');
            $base = new GenerationResult(GenerationOutcome::InsufficientEvidence, [], $gaps,
                $gaps === [] ? null : 'No operational evidence was available.',
                ['execution' => 'local', 'cost_basis' => 'zero_cost_local',
                    'request_count' => 0, 'retry_count' => 0, 'input_tokens' => 0, 'output_tokens' => 0,
                    'latency_ms' => 0, 'cost_usd' => 0]);
            $fingerprint = $this->fingerprints->make($profile->provider, $profile->model,
                $profile->contractVersion, $profile->promptVersion, $profile->adapterVersion,
                $profile->qualityAffectingConfiguration);
            $correlation = $input->correlationLineage['correlation_id'] ?? $request->requestId;
        }
        $fingerprint = $this->fingerprints->make($profile->provider, $profile->model,
            'scope-composed-answer-v2', $profile->promptVersion, $profile->adapterVersion,
            [...$profile->qualityAffectingConfiguration, 'scope_renderer' => 'scope-fact-renderer-v2']);
        $outcome = $base->unsupportedAspects === [] ? GenerationOutcome::Answered : GenerationOutcome::Qualified;
        $result = new GenerationResult($outcome, $base->answerParts, $base->unsupportedAspects, null,
            $base->usage, $base->aspectLinks, $base->aspectResults, $facts, $operational === [] ? null : $base);

        return new PreparedGroundedAnswer($request, $result, $fingerprint, $correlation);
    }
}
