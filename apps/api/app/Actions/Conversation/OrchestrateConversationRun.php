<?php

declare(strict_types=1);

namespace App\Actions\Conversation;

use App\Actions\Generation\GenerateGroundedAnswer;
use App\Actions\Generation\PersistGeneratedAnswer;
use App\Actions\Retrieval\RetrieveWorkspaceEvidence;
use App\Enums\ChatStreamEventType;
use App\Enums\ContextualisationStatus;
use App\Enums\GenerationOutcome;
use App\Enums\GenerationRunFailureCode;
use App\Enums\GenerationRunStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Enums\RetrievalOutcome;
use App\Exceptions\GenerationCancelled;
use App\Exceptions\GenerationContextBudgetExceeded;
use App\Exceptions\GenerationException;
use App\Exceptions\GenerationProviderException;
use App\Exceptions\GenerationStreamException;
use App\Models\ContextualisationResultSnapshot;
use App\Models\ControlledAssistantResponse;
use App\Models\Conversation;
use App\Models\GenerationRun;
use App\Models\Message;
use App\Models\RetrievalOutcomeSnapshot;
use App\Queries\Retrieval\BuildAuthorisedKnowledgeScope;
use App\Services\Conversation\ChatDeliveryEventRecorder;
use App\Services\Conversation\ContextualisationClient;
use App\Services\Conversation\ControlledResponseRenderer;
use App\Services\Conversation\ConversationHistoryBuilder;
use App\Services\Generation\GenerationProfileFactory;
use App\Support\Conversation\ContextualisationRequest;
use App\Support\Conversation\ContextualisationResult;
use App\Support\Generation\AnswerPartResult;
use App\Support\Generation\GenerationAssemblyInput;
use App\Support\Generation\GenerationRequest;
use App\Support\Generation\PreparedGroundedAnswer;
use App\Support\Retrieval\AuthorisedKnowledgeScope;
use App\Support\Retrieval\RetrievalResult;
use App\Support\Usage\RecordWorkspaceUsage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class OrchestrateConversationRun
{
    public function __construct(
        private ConversationHistoryBuilder $history,
        private ContextualisationClient $contextualisation,
        private BuildAuthorisedKnowledgeScope $authorisation,
        private RetrieveWorkspaceEvidence $retrieval,
        private ControlledResponseRenderer $renderer,
        private GenerateGroundedAnswer $generation,
        private GenerationProfileFactory $profiles,
        private PersistGeneratedAnswer $persistence,
        private ChatDeliveryEventRecorder $events,
        private RecordWorkspaceUsage $usageRecorder,
    ) {}

    public function handle(int $runId): void
    {
        $stage = GenerationRunStatus::Queued;
        try {
            $run = $this->begin($runId);
            if ($run === null) {
                return;
            }
            $this->progress($run, GenerationRunStatus::Contextualising);
            $stage = GenerationRunStatus::Contextualising;
            $userMessage = $run->userMessage;
            $workspace = $run->conversation->workspace;
            $history = $this->history->forMessage($userMessage);
            $request = new ContextualisationRequest(
                (string) Str::uuid(),
                (string) $workspace->public_id,
                $userMessage->display_text,
                $history,
                (string) config('conversation.context_policy_version'),
            );
            $contextualised = $this->contextualisation->contextualise($workspace, $request);
            $this->persistContextualisation($run, $contextualised);
            if ($contextualised->status === ContextualisationStatus::ClarificationRequired) {
                $this->completeContextualisationClarification($run, $contextualised->clarificationQuestion ?? '');

                return;
            }
            if (! $this->transition($run, GenerationRunStatus::Retrieving)) {
                return;
            }
            $stage = GenerationRunStatus::Retrieving;
            $scope = $this->authorisation->handle($userMessage->createdBy, (string) $workspace->public_id);
            $evaluatedAt = CarbonImmutable::now();
            $retrieval = $this->retrieval->handle(
                $scope,
                $contextualised->resolvedQuery ?? $userMessage->display_text,
                (int) config('conversation.candidate_k'),
            );
            $snapshot = $this->persistRetrievalSnapshot($run, $retrieval, $evaluatedAt);
            if ($retrieval->outcome !== RetrievalOutcome::EvidenceFound) {
                $this->completeControlledRetrieval($run, $retrieval, $snapshot);

                return;
            }
            $input = new GenerationAssemblyInput(
                $contextualised->resolvedQuery ?? $userMessage->display_text,
                $retrieval,
                $retrieval->resolvedTemporalAuthority,
                $retrieval->resolvedApplicabilityLocation,
                $scope,
                ['correlation_id' => $run->correlation_id, 'generation_run_id' => $run->public_id],
            );
            $profile = $this->profiles->configured();
            $run->update([
                'delivery_mode' => $profile->deliveryMode->value,
                'generation_endpoint' => $profile->deliveryMode->value === 'streaming_parts' ? 'generation.stream' : 'generation.answer',
            ]);
            $prepared = $this->generation->prepare(
                $input,
                $profile,
                function (string $next) use ($run, &$stage): void {
                    $status = GenerationRunStatus::from($next);
                    $stage = $status;
                    if (! $this->transition($run, $status)) {
                        throw new GenerationException('Generation was cancelled before completion.');
                    }
                },
                function (AnswerPartResult $candidate, GenerationRequest $request) use ($run): void {
                    $this->recordCandidate($run, $request, $candidate);
                },
            );
            $this->completeAnswer($run, $scope, $input->question, $prepared);
        } catch (GenerationCancelled) {
            $this->fail($runId, GenerationRunFailureCode::InternalFailure);
        } catch (GenerationContextBudgetExceeded $exception) {
            $this->fail($runId, GenerationRunFailureCode::GenerationContextBudgetExceeded);
        } catch (GenerationProviderException $exception) {
            $code = $exception->category === 'timeout'
                ? GenerationRunFailureCode::ProviderResponseTimeout
                : GenerationRunFailureCode::ProviderUnavailable;
            $this->fail($runId, $code, ['generation' => ['attempt_count' => $exception->attemptCount, 'latency_ms' => $exception->latencyMs]]);
        } catch (GenerationStreamException) {
            $this->fail($runId, GenerationRunFailureCode::StreamProtocolFailure);
        } catch (GenerationException $exception) {
            $code = $stage === GenerationRunStatus::Contextualising
                ? GenerationRunFailureCode::ContextualisationFailed
                : GenerationRunFailureCode::GenerationContractInvalid;
            $this->fail($runId, $code);
        } catch (Throwable) {
            $this->fail($runId, GenerationRunFailureCode::InternalFailure);
        }
    }

    private function begin(int $runId): ?GenerationRun
    {
        return DB::transaction(function () use ($runId): ?GenerationRun {
            $run = GenerationRun::query()->with(['conversation.workspace', 'userMessage.createdBy'])->lockForUpdate()->find($runId);
            if (! $run instanceof GenerationRun || $run->status !== GenerationRunStatus::Queued) {
                return null;
            }
            if ($run->conversation->generationRuns()->whereIn('status', array_map(
                fn (GenerationRunStatus $status): string => $status->value,
                $this->activeStatuses(),
            ))->where('id', '!=', $run->id)->exists()) {
                return null;
            }
            $run->update([
                'status' => GenerationRunStatus::Contextualising,
                'started_at' => now(),
                'first_progress_at' => now(),
                'context_policy_version' => config('conversation.context_policy_version'),
            ]);

            return $run->fresh(['conversation.workspace', 'userMessage.createdBy']);
        });
    }

    private function persistContextualisation(GenerationRun $run, ContextualisationResult $result): void
    {
        DB::transaction(function () use ($run, $result): void {
            ContextualisationResultSnapshot::query()->create([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $run->workspace_id,
                'generation_run_id' => $run->id,
                'status' => $result->status,
                'clarification_question' => $result->clarificationQuestion,
                'used_prior_context' => $result->usedPriorContext,
                'interpretation_metadata' => $result->interpretationMetadata,
                'contextualiser_version' => $result->contextualiserVersion,
                'usage' => $result->usage,
                'correlation_id' => $run->correlation_id,
            ]);
            $run->update([
                'contextualiser_version' => $result->contextualiserVersion,
                'resolved_query_digest' => $result->resolvedQuery === null ? null : hash('sha256', $result->resolvedQuery),
                'usage' => ['contextualisation' => $result->usage],
            ]);
            if (is_array($result->usage) && $result->usage !== []) {
                $this->usageRecorder->usage($run->workspace_id, 'generation_run', $run->public_id, [
                    $this->normalisedUsage(
                        $result->usage,
                        'contextualisation',
                        (string) config('conversation.contextualiser.provider'),
                        (string) config('conversation.contextualiser.model'),
                    ),
                ]);
            }
        });
    }

    private function completeContextualisationClarification(GenerationRun $run, string $question): void
    {
        DB::transaction(function () use ($run, $question): void {
            $locked = $this->lockCompletableRun($run);
            if ($locked === null) {
                return;
            }
            $message = $this->assistantMessage($locked, MessageKind::Clarification, $question);
            ControlledAssistantResponse::query()->create([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $locked->workspace_id,
                'message_id' => $message->id,
                'generation_run_id' => $locked->id,
                'retrieval_outcome_snapshot_id' => null,
                'response_kind' => MessageKind::Clarification,
                'renderer_version' => $locked->contextualiser_version,
                'rendered_text' => $question,
            ]);
            $locked->update(['status' => GenerationRunStatus::ClarificationRequired, 'assistant_message_id' => $message->id, 'completed_at' => now()]);
            $this->usageRecorder->activity($locked->workspace_id, 'run_outcome', $locked->public_id, GenerationRunStatus::ClarificationRequired->value);
            $this->events->record($locked, ChatStreamEventType::ClarificationRequired, ['message_id' => $message->public_id, 'text' => $question]);
        });
    }

    private function persistRetrievalSnapshot(GenerationRun $run, RetrievalResult $result, CarbonImmutable $evaluatedAt): RetrievalOutcomeSnapshot
    {
        return DB::transaction(function () use ($run, $result, $evaluatedAt): RetrievalOutcomeSnapshot {
            $snapshot = RetrievalOutcomeSnapshot::query()->create([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $run->workspace_id,
                'generation_run_id' => $run->id,
                'outcome' => $result->outcome,
                'clarification_source' => $result->clarificationSource,
                'clarification_reason' => $result->reason,
                'resolved_temporal_authority' => $result->resolvedTemporalAuthority,
                'resolved_applicability_location' => $result->resolvedApplicabilityLocation,
                'lineage' => $result->lineage,
                'evaluated_at' => $evaluatedAt,
                'candidate_count' => count($result->candidates),
                'evidence_count' => $result->outcome === RetrievalOutcome::EvidenceFound ? count($result->candidates) : 0,
                'comparison_state' => null,
                'safe_metadata' => null,
                'renderer_version' => config('conversation.controlled_renderer_version'),
                'retryable' => $result->outcome === RetrievalOutcome::RetrievalFailed,
                'correlation_id' => $run->correlation_id,
            ]);
            $run->update(['retrieval_outcome' => $result->outcome]);
            if ($result->usage !== []) {
                $this->usageRecorder->usage($run->workspace_id, 'generation_run', $run->public_id, $result->usage);
            }

            return $snapshot;
        });
    }

    private function completeControlledRetrieval(GenerationRun $run, RetrievalResult $result, RetrievalOutcomeSnapshot $snapshot): void
    {
        if ($result->outcome === RetrievalOutcome::TemporalScopeUnresolved) {
            $this->fail($run->id, GenerationRunFailureCode::RetrievalScopeUnresolved);

            return;
        }
        if ($result->outcome === RetrievalOutcome::RetrievalFailed) {
            $this->fail($run->id, GenerationRunFailureCode::RetrievalFailed);

            return;
        }
        $rendered = $result->outcome === RetrievalOutcome::ClarificationRequired
            ? $this->renderer->clarification($result->clarificationSource ?? throw new GenerationException('A clarification source is required.'), $result->reason ?? '')
            : $this->renderer->noAnswer($result->outcome === RetrievalOutcome::ComparisonScopeIncomplete);
        $terminal = $result->outcome === RetrievalOutcome::ClarificationRequired
            ? GenerationRunStatus::ClarificationRequired
            : GenerationRunStatus::RetrievalNoAnswer;
        DB::transaction(function () use ($run, $rendered, $terminal, $snapshot): void {
            $locked = $this->lockCompletableRun($run);
            if ($locked === null) {
                return;
            }
            $message = $this->assistantMessage($locked, $rendered['kind'], $rendered['text']);
            ControlledAssistantResponse::query()->create([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $locked->workspace_id,
                'message_id' => $message->id,
                'generation_run_id' => $locked->id,
                'retrieval_outcome_snapshot_id' => $snapshot->id,
                'response_kind' => $rendered['kind'],
                'renderer_version' => $rendered['renderer_version'],
                'rendered_text' => $rendered['text'],
            ]);
            $locked->update(['status' => $terminal, 'assistant_message_id' => $message->id, 'completed_at' => now()]);
            $this->usageRecorder->activity($locked->workspace_id, 'run_outcome', $locked->public_id, $terminal->value);
            $type = $terminal === GenerationRunStatus::ClarificationRequired ? ChatStreamEventType::ClarificationRequired : ChatStreamEventType::AnswerCompleted;
            $this->events->record($locked, $type, ['message_id' => $message->public_id, 'kind' => $rendered['kind']->value, 'text' => $rendered['text']]);
        });
    }

    private function completeAnswer(GenerationRun $run, AuthorisedKnowledgeScope $scope, string $question, PreparedGroundedAnswer $prepared): void
    {
        DB::transaction(function () use ($run, $scope, $question, $prepared): void {
            $locked = $this->lockCompletableRun($run);
            if ($locked === null) {
                return;
            }
            $text = $prepared->result->outcome === GenerationOutcome::InsufficientEvidence
                ? (string) $prepared->result->insufficiencyReason
                : $prepared->result->renderedText();
            $message = $this->assistantMessage($locked, MessageKind::GroundedAnswer, $text);
            $answer = $this->persistence->handle(
                $scope,
                $question,
                $prepared->correlationId,
                $prepared->request,
                $prepared->result,
                $prepared->fingerprint,
                $message->id,
                $locked->id,
            );
            $locked->update([
                'status' => GenerationRunStatus::Completed,
                'assistant_message_id' => $message->id,
                'generation_fingerprint' => $answer->generation_fingerprint,
                'usage' => [...($locked->usage ?? []), 'generation' => $answer->usage],
                'completed_at' => now(),
            ]);
            $generationUsage = $answer->usage;
            if (is_array($generationUsage) && $generationUsage !== []) {
                $this->usageRecorder->usage($locked->workspace_id, 'generation_run', $locked->public_id, [
                    $this->normalisedUsage($generationUsage, 'generation', $answer->provider, $answer->model),
                ]);
            }
            $this->usageRecorder->activity($locked->workspace_id, 'run_outcome', $locked->public_id, GenerationRunStatus::Completed->value);
            $this->events->record($locked, ChatStreamEventType::AnswerCompleted, [
                'message_id' => $message->public_id,
                'answer_id' => $answer->public_id,
                'outcome' => $answer->outcome->value,
                'text' => $message->display_text,
                'unsupported_aspects' => $answer->unsupported_aspects,
                'insufficiency_reason' => $answer->insufficiency_reason,
                'parts' => $answer->answerParts->map(fn ($part): array => [
                    'id' => $part->public_id,
                    'text' => $part->text,
                    'citations' => $part->evidenceSnapshots->map(fn ($snapshot): array => [
                        'id' => $snapshot->public_id,
                        'provisional_reference' => $this->citationReference($run, $snapshot->evidence_handle),
                    ])->all(),
                ])->all(),
            ]);
        });
    }

    private function assistantMessage(GenerationRun $run, MessageKind $kind, string $text): Message
    {
        $conversation = Conversation::query()->lockForUpdate()->findOrFail($run->conversation_id);
        $ordinal = ((int) $conversation->messages()->max('ordinal')) + 1;

        return Message::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $run->workspace_id,
            'conversation_id' => $run->conversation_id,
            'ordinal' => $ordinal,
            'role' => MessageRole::Assistant,
            'kind' => $kind,
            'display_text' => $text,
            'created_by_user_id' => null,
            'in_reply_to_message_id' => $run->user_message_id,
            'submission_idempotency_key' => null,
        ]);
    }

    private function transition(GenerationRun $run, GenerationRunStatus $status): bool
    {
        $transitioned = DB::transaction(function () use ($run, $status): bool {
            $locked = GenerationRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($locked->status === GenerationRunStatus::CancellationRequested) {
                $locked->update(['status' => GenerationRunStatus::Cancelled, 'cancellation_acknowledged_at' => now(), 'completed_at' => now()]);
                $this->usageRecorder->activity($locked->workspace_id, 'run_outcome', $locked->public_id, GenerationRunStatus::Cancelled->value);

                return false;
            }
            if ($locked->status->isTerminal()) {
                return false;
            }
            $locked->update(['status' => $status]);

            return true;
        });
        if ($transitioned) {
            $this->progress($run, $status);
        } elseif ($run->fresh()->status === GenerationRunStatus::Cancelled
            && ! $run->deliveryEvents()->where('event_type', ChatStreamEventType::RunCancelled->value)->exists()) {
            $this->events->record($run, ChatStreamEventType::RunCancelled, []);
        }

        return $transitioned;
    }

    private function lockCompletableRun(GenerationRun $run): ?GenerationRun
    {
        $locked = GenerationRun::query()->lockForUpdate()->findOrFail($run->id);
        $conversation = Conversation::query()->lockForUpdate()->findOrFail($run->conversation_id);
        if ($locked->status === GenerationRunStatus::CancellationRequested || in_array($conversation->status->value, ['deleting', 'deleted'], true)) {
            $locked->update(['status' => GenerationRunStatus::Cancelled, 'cancellation_acknowledged_at' => now(), 'completed_at' => now()]);
            $this->usageRecorder->activity($locked->workspace_id, 'run_outcome', $locked->public_id, GenerationRunStatus::Cancelled->value);
            $this->events->record($locked, ChatStreamEventType::RunCancelled, []);

            return null;
        }
        if ($locked->status->isTerminal()) {
            return null;
        }

        return $locked;
    }

    /** @param array<string, mixed>|null $usage */
    public function fail(int $runId, GenerationRunFailureCode $code, ?array $usage = null): void
    {
        $failed = DB::transaction(function () use ($runId, $code, $usage): ?GenerationRun {
            $run = GenerationRun::query()->lockForUpdate()->find($runId);
            if (! $run instanceof GenerationRun || $run->status->isTerminal()) {
                return null;
            }
            if ($run->status === GenerationRunStatus::CancellationRequested) {
                $run->update(['status' => GenerationRunStatus::Cancelled, 'cancellation_acknowledged_at' => now(), 'completed_at' => now()]);
                $this->usageRecorder->activity($run->workspace_id, 'run_outcome', $run->public_id, GenerationRunStatus::Cancelled->value);

                return $run;
            }
            $run->update(['status' => GenerationRunStatus::Failed, 'failure_code' => $code, 'usage' => $usage ?? $run->usage, 'completed_at' => now()]);
            $this->usageRecorder->activity($run->workspace_id, 'run_outcome', $run->public_id, GenerationRunStatus::Failed->value);

            return $run;
        });
        if ($failed instanceof GenerationRun) {
            $type = $failed->status === GenerationRunStatus::Cancelled ? ChatStreamEventType::RunCancelled : ChatStreamEventType::RunFailed;
            $payload = $type === ChatStreamEventType::RunFailed ? ['failure_code' => $code->value, 'retryable' => true] : [];
            $this->events->record($failed, $type, $payload);
        }
    }

    private function progress(GenerationRun $run, GenerationRunStatus $status): void
    {
        $this->events->record($run, ChatStreamEventType::RunProgress, ['stage' => $status->value, 'display_key' => 'conversation.progress.'.$status->value]);
    }

    private function recordCandidate(GenerationRun $run, GenerationRequest $request, AnswerPartResult $candidate): void
    {
        if ($run->fresh()->status === GenerationRunStatus::CancellationRequested) {
            throw new GenerationCancelled('Generation cancellation was requested.');
        }
        $evidence = collect($request->evidence)->keyBy('evidenceId');
        $citations = array_map(fn (string $evidenceId): array => [
            'reference' => $this->citationReference($run, $evidenceId),
            'source' => ['side' => $evidence->get($evidenceId)?->side->value],
        ], $candidate->evidenceIds);
        $this->events->record($run, ChatStreamEventType::AnswerPartAcceptedForDisplay, [
            'text' => $candidate->text,
            'citations' => $citations,
        ], true);
    }

    private function citationReference(GenerationRun $run, string $evidenceId): string
    {
        return 'cite_'.substr(hash_hmac('sha256', $run->public_id.'|'.$evidenceId, (string) config('app.key')), 0, 24);
    }

    /** @param array<string, mixed> $usage @return array<string, mixed> */
    private function normalisedUsage(array $usage, string $stage, string $provider, string $model): array
    {
        $deterministic = in_array(strtolower((string) ($usage['execution'] ?? '')), ['deterministic', 'local'], true);

        return [
            ...$usage,
            'stage' => $stage,
            'provider' => $deterministic ? 'application' : $provider,
            'model' => $deterministic ? 'deterministic' : $model,
            'execution' => $deterministic ? 'local' : 'provider_api',
            'request_count' => (int) ($usage['request_count'] ?? 1),
            'retry_count' => (int) ($usage['retry_count'] ?? max(0, ((int) ($usage['request_count'] ?? 1)) - 1)),
            'input_tokens' => $usage['input_tokens'] ?? null,
            'cached_input_tokens' => $usage['cached_input_tokens'] ?? null,
            'output_tokens' => $usage['output_tokens'] ?? null,
            'latency_ms' => $usage['latency_ms'] ?? 0,
            'cost_basis' => $usage['cost_basis'] ?? ($deterministic ? 'zero_cost_local' : 'unavailable'),
            'cost_usd' => $usage['cost_usd'] ?? ($deterministic ? 0 : null),
            'pricing_snapshot' => $usage['pricing_snapshot'] ?? null,
        ];
    }

    /** @return list<GenerationRunStatus> */
    private function activeStatuses(): array
    {
        return array_values(array_filter(GenerationRunStatus::cases(), fn (GenerationRunStatus $status): bool => ! $status->isTerminal()));
    }
}
