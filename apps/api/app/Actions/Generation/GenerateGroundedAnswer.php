<?php

declare(strict_types=1);

namespace App\Actions\Generation;

use App\Enums\GenerationDeliveryMode;
use App\Models\GeneratedAnswer;
use App\Services\Generation\AssembleGenerationRequest;
use App\Services\Generation\GenerationClient;
use App\Services\Generation\GenerationFingerprint;
use App\Services\Generation\ValidateAnswerPartCandidate;
use App\Services\Generation\ValidateGenerationResult;
use App\Support\Generation\GenerationAssemblyInput;
use App\Support\Generation\GenerationProfile;
use App\Support\Generation\PreparedGroundedAnswer;

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
        $observeStage?->__invoke('generating');
        $payload = $profile->deliveryMode === GenerationDeliveryMode::StreamingParts
            ? $this->client->stream(
                $input->authorisedScope->workspace,
                $request,
                function (array $candidate) use ($request, $observeCandidate): void {
                    $observeCandidate?->__invoke($this->candidateValidator->handle($request, $candidate), $request);
                },
            )
            : $this->client->generate($input->authorisedScope->workspace, $request);
        $observeStage?->__invoke('validating');
        $result = $this->validator->handle($request, $payload);
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
