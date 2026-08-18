<?php

declare(strict_types=1);

namespace App\Actions\Generation;

use App\Exceptions\GenerationException;
use App\Models\AnswerPart;
use App\Models\EvidenceSnapshot;
use App\Models\GeneratedAnswer;
use App\Support\Generation\GenerationRequest;
use App\Support\Generation\GenerationResult;
use App\Support\Retrieval\AuthorisedKnowledgeScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PersistGeneratedAnswer
{
    /** @param array{fingerprint_scheme_version: int, generation_fingerprint: string, snapshot: array<string, mixed>} $fingerprint */
    public function handle(AuthorisedKnowledgeScope $authorised, string $question, string $correlationId, GenerationRequest $request, GenerationResult $result, array $fingerprint, ?int $assistantMessageId = null, ?int $generationRunId = null): GeneratedAnswer
    {
        return DB::transaction(function () use ($authorised, $question, $correlationId, $request, $result, $fingerprint, $assistantMessageId, $generationRunId): GeneratedAnswer {
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
                'latency_ms' => $result->usage['latency_ms'] ?? null,
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
                $byHandle[$evidence->evidenceId] = EvidenceSnapshot::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'workspace_id' => $authorised->workspace->id,
                    'generated_answer_id' => $answer->id,
                    'evidence_handle' => $evidence->evidenceId,
                    'document_chunk_id' => $evidence->documentChunkId,
                    'document_id' => $evidence->documentId,
                    'ingestion_event_claim_id' => $evidence->ingestionEventClaimId,
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
}
