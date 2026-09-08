<?php

declare(strict_types=1);

namespace App\Actions\Generation;

use App\Exceptions\GenerationException;
use App\Models\AnswerPart;
use App\Models\Document;
use App\Models\EvidenceSnapshot;
use App\Models\GeneratedAnswer;
use App\Services\Retrieval\EligibilityResolver;
use App\Support\Generation\AspectCoverage;
use App\Support\Generation\GenerationRequest;
use App\Support\Generation\GenerationResult;
use App\Support\Retrieval\AuthorisedKnowledgeScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PersistGeneratedAnswer
{
    /** @param array{fingerprint_scheme_version: int, generation_fingerprint: string, snapshot: array<string, mixed>} $fingerprint */
    public function handle(AuthorisedKnowledgeScope $authorised, string $question, string $correlationId, GenerationRequest $request, GenerationResult $result, array $fingerprint, ?int $assistantMessageId = null, ?int $generationRunId = null): GeneratedAnswer
    {
        $hasNeeds = $result->aspectLinks !== [];
        $scopeComposed = ($fingerprint['snapshot']['contract_version'] ?? null) === 'scope-composed-answer-v1';
        if ($scopeComposed !== ($result->scopeFacts !== [])) {
            throw new GenerationException('Scope facts require their dedicated composed-answer contract.');
        }
        if (! $scopeComposed && $hasNeeds !== (($fingerprint['snapshot']['contract_version'] ?? null) === 'generation-result-v2')) {
            throw new GenerationException('Generation coverage does not match the pinned contract.');
        }
        $coverageResult = $scopeComposed ? $result->operationalResult : $result;
        if ($hasNeeds && ($coverageResult === null
            || $coverageResult->answerParts !== $result->answerParts
            || $coverageResult->unsupportedAspects !== $result->unsupportedAspects
            || AspectCoverage::derive($coverageResult, array_keys($result->aspectResults), $result->aspectLinks) !== $result->aspectResults)) {
            throw new GenerationException('Generation coverage changed before persistence.');
        }
        if (! $hasNeeds && $result->aspectResults !== []) {
            throw new GenerationException('Legacy generation cannot contain needs coverage.');
        }

        return DB::transaction(function () use ($authorised, $question, $correlationId, $request, $result, $fingerprint, $assistantMessageId, $generationRunId): GeneratedAnswer {
            foreach ($result->scopeFacts as $fact) {
                app(EligibilityResolver::class)->recheckScopeFact($authorised, $fact);
                $document = Document::where('workspace_id', $authorised->workspace->id)
                    ->where('public_id', $fact['document_version_id'])->with('applicabilitySnapshot.locations')->first();
                $snapshot = $document?->applicabilitySnapshot;
                if ($snapshot === null || $snapshot->sealed_at === null
                    || $fact['workspace_id'] !== $authorised->workspace->public_id
                    || $snapshot->id !== $fact['snapshot']['snapshot_id']) {
                    throw new GenerationException('Scope citation source is unavailable.');
                }
                $source = ['snapshot_id' => $snapshot->id, 'document_version_id' => $document->public_id,
                    'scope' => $snapshot->scope->value, 'sealed_at' => $snapshot->sealed_at->toIso8601String(),
                    'location_ids' => $snapshot->locations->pluck('public_id')->sort()->values()->all()];
                if (! hash_equals($fact['snapshot_digest'], hash('sha256', json_encode($source, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)))) {
                    throw new GenerationException('Scope citation source changed before persistence.');
                }
            }
            $answer = GeneratedAnswer::query()->create([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $authorised->workspace->id,
                'created_by_user_id' => $authorised->user->id,
                'assistant_message_id' => $assistantMessageId,
                'generation_run_id' => $generationRunId,
                'correlation_id' => $correlationId,
                'question' => $question,
                'outcome' => $result->outcome,
                'unsupported_aspects' => $result->unsupportedAspects,
                'scope_facts' => $result->scopeFacts === [] ? null : $result->scopeFacts,
                'aspect_links' => $result->aspectLinks === [] ? null : $result->aspectLinks,
                'aspect_results' => $result->aspectResults === [] ? null : $result->aspectResults,
                'insufficiency_reason' => $result->insufficiencyReason,
                'fingerprint_scheme_version' => $fingerprint['fingerprint_scheme_version'],
                'generation_fingerprint' => $fingerprint['generation_fingerprint'],
                'provider' => $fingerprint['snapshot']['provider'],
                'model' => $fingerprint['snapshot']['model'],
                'contract_version' => $fingerprint['snapshot']['contract_version'],
                'prompt_version' => $fingerprint['snapshot']['prompt_version'],
                'adapter_version' => $fingerprint['snapshot']['adapter_version'],
                'generation_configuration' => $fingerprint['snapshot']['quality_affecting_configuration'],
                'usage' => $result->usage,
                'latency_ms' => $this->latencyMilliseconds($result->usage['latency_ms'] ?? null),
                'input_tokens' => $result->usage['input_tokens'] ?? null,
                'output_tokens' => $result->usage['output_tokens'] ?? null,
                'cost_usd' => $result->usage['cost_usd'] ?? null,
            ]);
            $byHandle = [];
            $cited = collect($result->answerParts)->flatMap(fn ($part) => $part->evidenceIds)->unique();
            foreach ($request->evidence as $evidence) {
                if (! $cited->contains($evidence->evidenceId)) {
                    continue;
                }
                $chunk = DB::table('document_chunks')->where('id', $evidence->documentChunkId)->first();
                if ($chunk === null || (int) $chunk->workspace_id !== $authorised->workspace->id
                    || (int) $chunk->document_id !== $evidence->documentId
                    || (int) $chunk->ingestion_event_claim_id !== $evidence->ingestionEventClaimId
                    || ! hash_equals((string) $chunk->text, $evidence->text)) {
                    throw new GenerationException('Evidence changed or escaped the authorised workspace before persistence.');
                }
                $sourceIngestionEventId = DB::table('ingestion_event_claims')
                    ->where('id', $evidence->ingestionEventClaimId)
                    ->value('event_id');
                if (! is_string($sourceIngestionEventId)) {
                    throw new GenerationException('Evidence ingestion lineage is unavailable.');
                }
                $byHandle[$evidence->evidenceId] = EvidenceSnapshot::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'workspace_id' => $authorised->workspace->id,
                    'generated_answer_id' => $answer->id,
                    'evidence_handle' => $evidence->evidenceId,
                    'document_chunk_id' => $evidence->documentChunkId,
                    'document_id' => $evidence->documentId,
                    'ingestion_event_claim_id' => $evidence->ingestionEventClaimId,
                    'source_chunk_public_id' => $chunk->public_id,
                    'source_chunk_ordinal' => $chunk->ordinal,
                    'source_ingestion_event_id' => $sourceIngestionEventId,
                    'source_provenance' => $evidence->sourceProvenance,
                    'cited_text_verbatim' => $evidence->text,
                    'content_digest' => hash('sha256', $evidence->text),
                ]);
            }
            foreach ($result->answerParts as $index => $partResult) {
                $part = AnswerPart::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'workspace_id' => $authorised->workspace->id,
                    'generated_answer_id' => $answer->id,
                    'ordinal' => $index + 1,
                    'text' => $partResult->text,
                ]);
                foreach ($partResult->evidenceIds as $citationIndex => $handle) {
                    if (! isset($byHandle[$handle])) {
                        throw new GenerationException('A cited evidence snapshot is unavailable.');
                    }
                    $part->evidenceSnapshots()->attach($byHandle[$handle]->id, ['ordinal' => $citationIndex + 1]);
                }
            }

            return $answer->load(['answerParts.evidenceSnapshots', 'evidenceSnapshots']);
        });
    }

    private function latencyMilliseconds(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException('Generation latency must be a finite non-negative number.');
        }
        $latency = (float) $value;
        if (! is_finite($latency) || $latency < 0 || $latency > 4_294_967_295) {
            throw new InvalidArgumentException('Generation latency is outside the supported millisecond range.');
        }

        return (int) round($latency, mode: PHP_ROUND_HALF_UP);
    }
}
