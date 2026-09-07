<?php

declare(strict_types=1);

namespace App\Actions\Generation;

use App\Enums\GenerationDeliveryMode;
use App\Exceptions\GenerationException;
use App\Exceptions\GenerationProviderException;
use App\Models\GeneratedAnswer;
use App\Services\Generation\AssembleGenerationRequest;
use App\Services\Generation\GenerationClient;
use App\Services\Generation\GenerationFingerprint;
use App\Services\Generation\ValidateAnswerPartCandidate;
use App\Services\Generation\ValidateGenerationResult;
use App\Support\Generation\GenerationAssemblyInput;
use App\Support\Generation\GenerationProfile;
use App\Support\Generation\GenerationResult;
use App\Support\Generation\GenerationValidationDiagnostics;
use App\Support\Generation\PreparedGroundedAnswer;
use App\Support\Generation\ValidationAttemptUsage;

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
        $observeStage?->__invoke('preparing_evidence');
        $request = $this->assembler->handle($input);
        if ($request->evidenceNeeds !== [] && ($profile->contractVersion !== 'generation-result-v2'
            || $profile->promptVersion !== 'grounded-generation-needs-v5'
            || $profile->adapterVersion !== 'openai-responses-needs-v2'
            || $profile->deliveryMode !== GenerationDeliveryMode::CompleteResultOnly)) {
            throw new GenerationException('Needs generation requires the pinned v2 complete-result profile.');
        }
        $observeStage?->__invoke('generating');
        $receipts = [];
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
                    $receipt['request_count'] = $exception->attemptCount;
                    $receipt['latency_ms'] ??= $exception->latencyMs;
                }
                $receipt['outcome'] = 'rejected';
                $receipt['validation'] = array_intersect_key($diagnostic, array_flip(['category', 'field', 'code', 'layer']));
                $receipts[] = $receipt;
                $exception->validationAttempts = $receipts;
                $eligible = $request->evidenceNeeds !== [] && $profile->deliveryMode === GenerationDeliveryMode::CompleteResultOnly
                    && ($providerFailure
                        ? ($exception->category === 'contract_validation_failure' && $exception->returnedOutput && ($diagnostic['code'] ?? null) !== null)
                        : (is_array($payload) && ($diagnostic['code'] ?? 'unclassified_validation') !== 'unclassified_validation'));
                if ($validationAttempt === 1 && $eligible) {
                    continue;
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
        $correlationId = (string) ($input->correlationLineage['correlation_id'] ?? $request->requestId);

        return new PreparedGroundedAnswer($request, $result, $fingerprint, $correlationId);
    }
}
