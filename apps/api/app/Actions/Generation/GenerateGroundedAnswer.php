<?php

declare(strict_types=1);

namespace App\Actions\Generation;

use App\Models\GeneratedAnswer;
use App\Services\Generation\AssembleGenerationRequest;
use App\Services\Generation\GenerationClient;
use App\Services\Generation\GenerationFingerprint;
use App\Services\Generation\ValidateGenerationResult;
use App\Support\Generation\GenerationAssemblyInput;
use App\Support\Generation\GenerationProfile;

final readonly class GenerateGroundedAnswer
{
    public function __construct(
        private AssembleGenerationRequest $assembler,
        private GenerationClient $client,
        private ValidateGenerationResult $validator,
        private GenerationFingerprint $fingerprints,
        private PersistGeneratedAnswer $persistence,
    ) {}

    public function handle(GenerationAssemblyInput $input, GenerationProfile $profile): GeneratedAnswer
    {
        $request = $this->assembler->handle($input);
        $payload = $this->client->generate($input->authorisedScope->workspace, $request);
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

        return $this->persistence->handle(
            $input->authorisedScope,
            $input->question,
            $correlationId,
            $request,
            $result,
            $fingerprint,
        );
    }
}
