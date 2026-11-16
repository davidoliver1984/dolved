<?php

declare(strict_types=1);

namespace App\Actions\Generation;

use App\Actions\Retrieval\RetrieveWorkspaceEvidence;
use App\Enums\GenerationOutcome;
use App\Enums\GenerationSide;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Enums\RetrievalOutcome;
use App\Exceptions\GenerationException;
use App\Models\Message;
use App\Queries\Retrieval\BuildAuthorisedKnowledgeScope;
use App\Services\Conversation\ConversationHistoryBuilder;
use App\Services\Generation\CurrentPolicyPool;
use App\Services\Generation\GenerationClient;
use App\Services\Generation\GenerationFingerprint;
use App\Services\Generation\GenerationProfileFactory;
use App\Services\Retrieval\RetrievalClient;
use App\Support\Generation\AnswerPartResult;
use App\Support\Generation\GenerationRequest;
use App\Support\Generation\GenerationResult;
use App\Support\Retrieval\ApplicabilityScopeFact;
use App\Support\Retrieval\RetrievalResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Compose and save once; orchestration supplies its existing run-lock boundary. */
final readonly class AnswerCurrentPolicyMessage
{
    public function __construct(private BuildAuthorisedKnowledgeScope $authorise,
        private GenerationProfileFactory $profiles, private ConversationHistoryBuilder $history,
        private RetrievalClient $planner, private RetrieveWorkspaceEvidence $retrieval,
        private CurrentPolicyPool $pool, private GenerationClient $generation,
        private GenerationFingerprint $fingerprints, private PersistGeneratedAnswer $persistence) {}

    public function handle(Message $userMessage, array $pin, CarbonImmutable $at, ?callable $observe = null,
        ?callable $stage = null, ?callable $beforePersist = null, ?callable $afterPersist = null, ?int $runId = null): ?Message
    {
        $this->profiles->validateCurrentPolicyPin($pin);
        $userMessage = $userMessage->fresh(['conversation.workspace', 'createdBy']);
        if ($userMessage->role !== MessageRole::User || $userMessage->createdBy === null
            || ($this->profiles->needsPin($userMessage->conversation->workspace->public_id)['product_profile'] ?? null) !== 'current-policy-pool-v1') {
            throw new GenerationException('Current-policy profile is not enabled for this message.');
        }
        if ($userMessage->conversation->messages()->where('in_reply_to_message_id', $userMessage->id)->exists()) {
            throw new GenerationException('This message already has a saved response.');
        }
        $scope = $this->authorise->handle($userMessage->createdBy, $userMessage->conversation->workspace->public_id);
        $turns = array_map(fn ($t) => ['turn_id' => 'user-'.$t['user_ordinal'], 'text' => $t['user_message']], $this->history->forMessage($userMessage));
        $turns[] = ['turn_id' => 'user-'.$userMessage->ordinal, 'text' => $userMessage->display_text];
        $plan = $this->planner->planNeeds($scope->workspace, $turns, $at, $pin);
        $observe?->__invoke('needs_plan', $plan->value);
        $stage?->__invoke('retrieving');
        $results = $plan->value['decision'] === 'plan'
            ? $this->retrieval->handleCurrentPolicy($scope, $plan, $pin['candidate_k'], $at, $observe) : [];
        $pool = $this->pool->assemble($scope, $plan, $results, $at);
        $capacity = $pool['capacity'];
        if ($capacity !== null) {
            // Selection remains unchanged in the diagnostic record. No reselection or retry.
            $pool['sources'] = $pool['evidence'] = $pool['requests'] = [];
            foreach ($plan->executionNeeds() as $need) {
                $lane = $results[$need['need_id']];
                if ($lane instanceof RetrievalResult && $lane->outcome === RetrievalOutcome::EvidenceFound) {
                    $pool['dispositions'][] = $this->unhandled($need, 'capacity', 'The selected source context exceeds this answer’s capacity. No passages were truncated or substituted.');
                }
            }
        }
        if ($plan->value['decision'] === 'clarify') {
            $pool['dispositions'][] = ['version' => 'current-policy-disposition-v2', 'need_id' => null,
                'status' => 'ambiguity', 'reason' => 'planner_clarification',
                'affected_spans' => [['turn_id' => end($turns)['turn_id'], 'quote' => $userMessage->display_text]],
                'message' => $plan->value['clarification']['question']];
        }
        $gaps = CurrentPolicyPool::disclosures($pool['dispositions']);
        $requestId = (string) Str::uuid();
        $receipt = ['status' => $capacity === null ? 'not_requested' : 'capacity', 'capacity' => $capacity, 'usage' => ['request_count' => 0, 'retry_count' => 0]];
        $parts = [];
        if ($pool['sources'] !== []) {
            $stage?->__invoke('generating');
            $receipt = $this->generation->currentPolicy($scope->workspace, [
                'contract_version' => 'current-policy-answer-v1', 'request_id' => $requestId,
                'workspace_id' => $scope->workspace->public_id, 'question' => $userMessage->display_text,
                'admitted_requests' => $pool['requests'], 'limitations' => $gaps, 'sources' => $pool['sources']]);
            if ($receipt['status'] === 'completed') {
                $parts = array_map(fn ($p) => new AnswerPartResult($p['text'], $p['evidence_ids']), $receipt['result']['answer_parts']);
                $gaps = [...$gaps, ...$receipt['result']['unsupported_aspects']];
            } else {
                foreach ($plan->executionNeeds() as $need) {
                    $lane = $results[$need['need_id']];
                    if (! $lane instanceof RetrievalResult || $lane->outcome !== RetrievalOutcome::EvidenceFound) {
                        continue;
                    }
                    $pool['dispositions'][] = $this->unhandled($need, $receipt['status'], $receipt['status'] === 'capacity'
                        ? 'The complete generation request exceeds the answer capacity; nothing was truncated.'
                        : 'The answer could not be generated safely. This is not a finding that the documents lack the information.');
                }
                $gaps = CurrentPolicyPool::disclosures($pool['dispositions']);
            }
        }
        $outcome = $parts === [] && $pool['facts'] === [] ? GenerationOutcome::InsufficientEvidence
            : ($gaps === [] ? GenerationOutcome::Answered : GenerationOutcome::Qualified);
        // Compose from typed fields, not the legacy renderer's scope/no-evidence shortcuts.
        $text = implode("\n\n", [
            ...array_map(fn ($fact) => (new ApplicabilityScopeFact($fact))->renderedText(), $pool['facts']),
            ...array_map(fn ($part) => $part->text, $parts),
            ...($gaps === [] ? [] : ["Unanswered or unconfirmed parts\n\n".implode("\n", array_map(fn ($gap) => '- '.$gap, $gaps))]),
        ]);
        if (trim($text) === '') {
            throw new GenerationException('Current-policy result lost its explicit disposition.');
        }
        $request = new GenerationRequest($requestId, $scope->workspace->public_id, $userMessage->display_text,
            $pool['evidence'], 'current-policy-context-v1', 60000, [GenerationSide::Primary]);
        $trace = array_map(fn ($r) => $r instanceof RetrievalResult ? $r->toArray() : ['scope_fact' => $r->value], $results);
        $result = new GenerationResult($outcome, $parts, $gaps, $outcome === GenerationOutcome::InsufficientEvidence ? 'current_policy_unanswered' : null,
            $receipt['usage'], scopeFacts: $pool['facts'], temporalContext: ['current_policy' => ['version' => 'current-policy-context-v1',
                'dispositions' => $pool['dispositions'], 'planner' => $plan->value, 'planner_usage' => $plan->usage->toArray(),
                'retrieval' => $trace, 'generation' => $receipt, 'pool' => array_map(function ($s) {
                    unset($s['text']);

                    return $s;
                }, $pool['sources'])]]);
        $fingerprint = $this->fingerprints->make((string) config('generation.provider'), (string) config('generation.model'),
            'current-policy-answer-v1', 'current-policy-pool-generation-v1', 'current-policy-responses-v1', ['profile' => $pin]);
        $unhandled = array_values(array_filter($pool['dispositions'], fn ($d) => $d['status'] !== 'admitted'));
        $messageKind = $outcome !== GenerationOutcome::InsufficientEvidence ? MessageKind::GroundedAnswer
            : ($unhandled !== [] && array_all($unhandled, fn ($d) => $d['status'] === 'ambiguity') ? MessageKind::Clarification : MessageKind::NoAnswer);

        return DB::transaction(function () use ($userMessage, $scope, $plan, $results, $at, $text, $messageKind, $request, $result, $fingerprint, $beforePersist, $afterPersist, $runId) {
            if ($beforePersist !== null && $beforePersist() === false) {
                return null;
            }
            $fresh = $this->authorise->handle($scope->user, $scope->workspace->public_id);
            if ($fresh->activeCorpusGeneration?->public_id !== $scope->activeCorpusGeneration?->public_id) {
                throw new GenerationException('Corpus generation changed before persistence.');
            }
            foreach ($results as $id => $lane) {
                if ($lane instanceof RetrievalResult && $lane->candidates !== []) {
                    $this->retrieval->recheckCurrentPolicy($fresh, $plan->retrievalPlan($id), $lane->candidates, $at);
                }
            }
            $conversation = $userMessage->conversation()->lockForUpdate()->firstOrFail();
            if ($conversation->messages()->where('in_reply_to_message_id', $userMessage->id)->exists()) {
                throw new GenerationException('This message already has a saved response.');
            }
            $message = Message::create(['public_id' => (string) Str::uuid(), 'workspace_id' => $scope->workspace->id,
                'conversation_id' => $conversation->id, 'ordinal' => $conversation->messages()->max('ordinal') + 1,
                'role' => MessageRole::Assistant, 'kind' => $messageKind,
                'display_text' => $text, 'in_reply_to_message_id' => $userMessage->id, 'created_by_user_id' => null]);
            $this->persistence->handle($fresh, $userMessage->display_text, $request->requestId, $request, $result, $fingerprint, $message->id, $runId);
            $message->load('generatedAnswer.answerParts.evidenceSnapshots');
            $afterPersist?->__invoke($message);

            return $message;
        });
    }

    private function unhandled(array $need, string $status, string $message): array
    {
        return ['version' => 'current-policy-disposition-v2', 'need_id' => $need['need_id'], 'status' => $status,
            'reason' => $status, 'affected_spans' => $need['requested_information'] === 'mixed' ? $need['current_spans'] : [$need['question_span']], 'message' => $message];
    }
}
