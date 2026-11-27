<?php

declare(strict_types=1);

namespace App\Actions\Conversation;

use App\Enums\ChatStreamEventType;
use App\Enums\GenerationOutcome;
use App\Enums\GenerationRunFailureCode;
use App\Enums\GenerationRunStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Exceptions\ConversationException;
use App\Exceptions\DifyDispatchException;
use App\Models\AnswerPart;
use App\Models\Conversation;
use App\Models\DifyConversationSession;
use App\Models\DifyDocumentBinding;
use App\Models\GeneratedAnswer;
use App\Models\GenerationRun;
use App\Models\Message;
use App\Models\RetrievedSourceReference;
use App\Services\Conversation\ChatDeliveryEventRecorder;
use App\Services\Conversation\ConversationContinuationPolicy;
use App\Services\Dify\DifyChatClient;
use App\Support\Dify\DifyChatResult;
use App\Support\Usage\RecordWorkspaceUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class OrchestrateDifyConversationRun
{
    public function __construct(
        private DifyChatClient $client,
        private ChatDeliveryEventRecorder $events,
        private RecordWorkspaceUsage $usageRecorder,
        private ConversationContinuationPolicy $continuation,
    ) {}

    public function handle(int $runId): void
    {
        $run = $this->begin($runId);
        if ($run === null) {
            return;
        }

        try {
            $conversation = $run->conversation;
            $this->transition($run, GenerationRunStatus::Retrieving);
            $conversation->refresh();
            try {
                $this->continuation->assertCanContinue($conversation);
            } catch (ConversationException) {
                $this->failBeforeDispatch($run);

                return;
            }
            if (! $this->markDispatching($run)) {
                $this->failBeforeDispatch($run);

                return;
            }
            $session = $conversation->difySession;
            $site = $conversation->selectedLocation?->public_id ?? 'global';
            $result = $this->client->send(
                $run->userMessage->display_text,
                ['trusted_site' => (string) $site],
                $this->endUser($conversation),
                $session?->remote_conversation_id,
            );
            if ($session !== null && ! hash_equals($session->remote_conversation_id, $result->conversationId)) {
                throw new DifyDispatchException('conversation_identity_mismatch', true);
            }
            $this->transition($run, GenerationRunStatus::Generating);
            $this->complete($run, $result);
        } catch (DifyDispatchException $exception) {
            $this->fail($run, $exception->dispatchUncertain ? 'uncertain' : 'failed', [
                'dify' => [
                    'category' => $exception->category,
                    'http_status' => $exception->httpStatus,
                    'dispatch_uncertain' => $exception->dispatchUncertain,
                ],
            ]);
        } catch (Throwable) {
            $this->fail($run, 'failed');
        }
    }

    private function begin(int $runId): ?GenerationRun
    {
        return DB::transaction(function () use ($runId): ?GenerationRun {
            $run = GenerationRun::query()
                ->with(['conversation.workspace', 'conversation.selectedLocation', 'conversation.difySession', 'userMessage.createdBy'])
                ->lockForUpdate()->find($runId);
            if (! $run instanceof GenerationRun || $run->status !== GenerationRunStatus::Queued) {
                return null;
            }
            $run->update([
                'status' => GenerationRunStatus::Contextualising,
                'started_at' => now(),
                'first_progress_at' => now(),
                'context_policy_version' => 'explicit-site-selection-v1',
                'delivery_mode' => 'complete_result_only',
                'generation_endpoint' => 'dify.chat-messages',
                'provider_dispatch_state' => 'not_dispatched',
            ]);
            $this->events->record($run, ChatStreamEventType::RunProgress, [
                'stage' => GenerationRunStatus::Contextualising->value,
                'display_key' => 'conversation.progress.contextualising',
            ]);

            return $run->fresh(['conversation.workspace', 'conversation.selectedLocation', 'conversation.difySession', 'userMessage.createdBy']);
        });
    }

    private function transition(GenerationRun $run, GenerationRunStatus $status): void
    {
        $transitioned = DB::transaction(function () use ($run, $status): ?GenerationRun {
            $locked = GenerationRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($locked->status->isTerminal() || $locked->status === GenerationRunStatus::CancellationRequested) {
                return null;
            }
            $locked->update([
                'status' => $status,
                ...($status === GenerationRunStatus::Retrieving ? ['provider_dispatch_state' => 'ready_to_dispatch'] : []),
            ]);

            return $locked;
        });
        if (! $transitioned instanceof GenerationRun) {
            return;
        }
        $this->events->record($transitioned, ChatStreamEventType::RunProgress, [
            'stage' => $status->value,
            'display_key' => 'conversation.progress.'.$status->value,
        ]);
    }

    private function markDispatching(GenerationRun $run): bool
    {
        return DB::transaction(function () use ($run): bool {
            $locked = GenerationRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($locked->status->isTerminal() || $locked->status === GenerationRunStatus::CancellationRequested) {
                return false;
            }
            $locked->update(['provider_dispatch_state' => 'dispatching']);

            return true;
        });
    }

    private function complete(GenerationRun $run, DifyChatResult $result): void
    {
        DB::transaction(function () use ($run, $result): void {
            $locked = GenerationRun::query()->lockForUpdate()->findOrFail($run->id);
            $conversation = Conversation::query()->lockForUpdate()->findOrFail($run->conversation_id);
            if ($locked->status->isTerminal()) {
                return;
            }
            if ($locked->status === GenerationRunStatus::CancellationRequested) {
                if ($conversation->difySession()->exists()) {
                    $this->continuation->blockForRemoteHistory($conversation);
                }
                $locked->update(['status' => GenerationRunStatus::Cancelled, 'provider_dispatch_state' => 'completed_after_cancel', 'completed_at' => now()]);
                $this->events->record($locked, ChatStreamEventType::RunCancelled, []);

                return;
            }

            DifyConversationSession::query()->firstOrCreate(
                ['conversation_id' => $conversation->id],
                ['workspace_id' => $conversation->workspace_id, 'remote_conversation_id' => $result->conversationId],
            );
            $ordinal = ((int) $conversation->messages()->max('ordinal')) + 1;
            $message = Message::query()->create([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $locked->workspace_id,
                'conversation_id' => $locked->conversation_id,
                'ordinal' => $ordinal,
                'role' => MessageRole::Assistant,
                'kind' => MessageKind::GroundedAnswer,
                'display_text' => $result->answer,
                'created_by_user_id' => null,
                'in_reply_to_message_id' => $locked->user_message_id,
                'submission_idempotency_key' => null,
            ]);
            $configuration = [
                'site_input' => 'trusted_site',
                'site_scope' => $conversation->selectedLocation?->public_id ?? 'global',
                'response_mode' => 'blocking',
            ];
            $fingerprint = hash('sha256', json_encode($configuration, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $usage = $this->usage($result->usage);
            $answer = GeneratedAnswer::query()->create([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $locked->workspace_id,
                'created_by_user_id' => $locked->userMessage->created_by_user_id,
                'assistant_message_id' => $message->id,
                'generation_run_id' => $locked->id,
                'correlation_id' => $locked->correlation_id,
                'question' => $locked->userMessage->display_text,
                'outcome' => GenerationOutcome::Answered,
                'unsupported_aspects' => [],
                'insufficiency_reason' => null,
                'fingerprint_scheme_version' => 1,
                'generation_fingerprint' => $fingerprint,
                'provider' => 'dify',
                'model' => 'configured-in-dify',
                'contract_version' => (string) config('dify.contract_version'),
                'prompt_version' => (string) config('dify.prompt_version'),
                'adapter_version' => (string) config('dify.adapter_version'),
                'generation_configuration' => $configuration,
                'usage' => $usage,
                'latency_ms' => $usage['latency_ms'],
                'input_tokens' => $usage['input_tokens'],
                'output_tokens' => $usage['output_tokens'],
                'cost_usd' => $usage['cost_usd'],
            ]);
            AnswerPart::query()->create([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $locked->workspace_id,
                'generated_answer_id' => $answer->id,
                'ordinal' => 1,
                'text' => $result->answer,
            ]);
            $this->persistSources($answer, $result->retrievedSources);

            $locked->update([
                'status' => GenerationRunStatus::Completed,
                'assistant_message_id' => $message->id,
                'generation_fingerprint' => $fingerprint,
                'provider_message_id' => $result->messageId,
                'provider_task_id' => $result->taskId,
                'provider_dispatch_state' => 'completed',
                'usage' => ['generation' => $usage],
                'completed_at' => now(),
            ]);
            $this->usageRecorder->usage($locked->workspace_id, 'generation_run', $locked->public_id, [[
                ...$usage,
                'stage' => 'generation',
                'provider' => 'dify',
                'model' => 'configured-in-dify',
                'execution' => 'provider_api',
                'request_count' => 1,
                'retry_count' => 0,
                'cost_basis' => $usage['cost_usd'] === null ? 'unavailable' : 'provider_reported',
                'pricing_snapshot' => null,
            ]]);
            $this->usageRecorder->activity($locked->workspace_id, 'run_outcome', $locked->public_id, GenerationRunStatus::Completed->value);
            $this->events->record($locked, ChatStreamEventType::AnswerCompleted, [
                'message_id' => $message->public_id,
                'answer_id' => $answer->public_id,
                'outcome' => $answer->outcome->value,
                'presentation' => 'saved-message-v1',
                'text' => $message->display_text,
                'sources_retrieved' => $answer->retrievedSources()->orderBy('rank')->get()->map(fn ($source): array => [
                    'id' => $source->public_id,
                    'rank' => $source->rank,
                ])->all(),
            ]);
        });
    }

    /** @param list<array<string, mixed>> $sources */
    private function persistSources(GeneratedAnswer $answer, array $sources): void
    {
        foreach (array_values($sources) as $index => $source) {
            $text = is_string($source['content'] ?? null) ? trim($source['content']) : '';
            if ($text === '') {
                continue;
            }
            $remoteDocumentId = is_string($source['document_id'] ?? null) && Str::isUuid($source['document_id'])
                ? $source['document_id'] : null;
            $binding = $remoteDocumentId === null ? null : DifyDocumentBinding::query()
                ->where('workspace_id', $answer->workspace_id)
                ->where('remote_document_id', $remoteDocumentId)
                ->first();
            $safeMetadata = array_filter([
                'position' => is_numeric($source['position'] ?? null) ? (int) $source['position'] : null,
                'segment_position' => is_numeric($source['segment_position'] ?? null) ? (int) $source['segment_position'] : null,
            ], fn (mixed $value): bool => $value !== null);
            RetrievedSourceReference::query()->create([
                'public_id' => (string) Str::uuid(),
                'workspace_id' => $answer->workspace_id,
                'generated_answer_id' => $answer->id,
                'document_id' => $binding?->document_id,
                'dataset_id' => is_string($source['dataset_id'] ?? null) && Str::isUuid($source['dataset_id']) ? $source['dataset_id'] : null,
                'remote_document_id' => $remoteDocumentId,
                'remote_segment_id' => is_string($source['segment_id'] ?? null) && Str::isUuid($source['segment_id']) ? $source['segment_id'] : null,
                'rank' => $index + 1,
                'score' => is_numeric($source['score'] ?? null) ? (float) $source['score'] : null,
                'display_name' => $this->displayName($binding?->document?->source_filename ?? ($source['document_name'] ?? null)),
                'retrieved_text' => $text,
                'content_digest' => hash('sha256', $text),
                'safe_metadata' => $safeMetadata === [] ? null : $safeMetadata,
            ]);
        }
    }

    private function displayName(mixed $value): string
    {
        $name = is_string($value) ? basename(str_replace('\\', '/', $value)) : 'Document source';
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';

        return trim($name) === '' ? 'Document source' : mb_substr(trim($name), 0, 255);
    }

    /** @param array<string, mixed> $usage @return array<string, mixed> */
    private function usage(array $usage): array
    {
        $currency = strtoupper((string) ($usage['currency'] ?? ''));
        $price = is_numeric($usage['total_price'] ?? null) && $currency === 'USD' ? (float) $usage['total_price'] : null;

        return [
            'input_tokens' => is_numeric($usage['prompt_tokens'] ?? null) ? (int) $usage['prompt_tokens'] : null,
            'cached_input_tokens' => null,
            'output_tokens' => is_numeric($usage['completion_tokens'] ?? null) ? (int) $usage['completion_tokens'] : null,
            'latency_ms' => is_numeric($usage['latency'] ?? null) ? (int) round((float) $usage['latency'] * 1000) : null,
            'cost_usd' => $price,
        ];
    }

    private function fail(GenerationRun $run, string $dispatchState, ?array $usage = null): void
    {
        $failed = DB::transaction(function () use ($dispatchState, $run, $usage): ?GenerationRun {
            $locked = GenerationRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($locked->status->isTerminal()) {
                return null;
            }
            $conversation = $locked->conversation()->lockForUpdate()->firstOrFail();
            if ($dispatchState === 'uncertain' && $conversation->difySession()->exists()) {
                $this->continuation->blockForRemoteHistory($conversation);
            }
            if ($locked->status === GenerationRunStatus::CancellationRequested) {
                $locked->update([
                    'status' => GenerationRunStatus::Cancelled,
                    'provider_dispatch_state' => $dispatchState,
                    'usage' => $usage,
                    'cancellation_acknowledged_at' => now(),
                    'completed_at' => now(),
                ]);

                return $locked;
            }
            $locked->update([
                'status' => GenerationRunStatus::Failed,
                'failure_code' => GenerationRunFailureCode::ProviderUnavailable,
                'provider_dispatch_state' => $dispatchState,
                'usage' => $usage,
                'completed_at' => now(),
            ]);

            return $locked;
        });
        if (! $failed instanceof GenerationRun) {
            return;
        }
        $this->usageRecorder->activity($failed->workspace_id, 'run_outcome', $failed->public_id, $failed->status->value);
        if ($failed->status === GenerationRunStatus::Cancelled) {
            $this->events->record($failed, ChatStreamEventType::RunCancelled, []);

            return;
        }
        $this->events->record($failed, ChatStreamEventType::RunFailed, [
            'failure_code' => GenerationRunFailureCode::ProviderUnavailable->value,
            'retryable' => $dispatchState !== 'uncertain',
            'dispatch_uncertain' => $dispatchState === 'uncertain',
        ]);
    }

    private function failBeforeDispatch(GenerationRun $run): void
    {
        $failed = DB::transaction(function () use ($run): ?GenerationRun {
            $locked = GenerationRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($locked->status->isTerminal()) {
                return null;
            }
            if ($locked->status === GenerationRunStatus::CancellationRequested) {
                $locked->update([
                    'status' => GenerationRunStatus::Cancelled,
                    'provider_dispatch_state' => 'not_dispatched',
                    'cancellation_acknowledged_at' => now(),
                    'completed_at' => now(),
                ]);

                return $locked;
            }
            $locked->update([
                'status' => GenerationRunStatus::Failed,
                'failure_code' => GenerationRunFailureCode::ConversationContextUnavailable,
                'provider_dispatch_state' => 'not_dispatched',
                'completed_at' => now(),
            ]);

            return $locked;
        });
        if (! $failed instanceof GenerationRun) {
            return;
        }
        $this->usageRecorder->activity($failed->workspace_id, 'run_outcome', $failed->public_id, $failed->status->value);
        if ($failed->status === GenerationRunStatus::Cancelled) {
            $this->events->record($failed, ChatStreamEventType::RunCancelled, []);

            return;
        }
        $this->events->record($failed, ChatStreamEventType::RunFailed, [
            'failure_code' => GenerationRunFailureCode::ConversationContextUnavailable->value,
            'retryable' => false,
            'dispatch_uncertain' => false,
        ]);
    }

    private function endUser(Conversation $conversation): string
    {
        return 'dolved_'.substr(hash_hmac(
            'sha256',
            $conversation->workspace_id.'|'.$conversation->created_by_user_id,
            (string) config('app.key'),
        ), 0, 48);
    }
}
