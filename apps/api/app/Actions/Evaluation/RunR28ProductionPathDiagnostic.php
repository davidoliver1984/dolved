<?php

declare(strict_types=1);

namespace App\Actions\Evaluation;

use App\Actions\Conversation\CreateConversation;
use App\Actions\Conversation\OrchestrateConversationRun;
use App\Actions\Conversation\SubmitConversationMessage;
use App\Models\GenerationRun;
use App\Models\User;
use App\Queries\Workspaces\FindWorkspaceForUser;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Execute question-only R28 diagnostics through the normal conversation path.
 *
 * This action deliberately knows nothing about expected outcomes, evidence,
 * answers, relevance labels or scoring. Those belong to a separate
 * post-execution process.
 */
final readonly class RunR28ProductionPathDiagnostic
{
    public function __construct(
        private FindWorkspaceForUser $workspaces,
        private CreateConversation $conversations,
        private SubmitConversationMessage $messages,
        private OrchestrateConversationRun $orchestrator,
    ) {}

    /**
     * @param  array{schema_version: string, subset_id: string, population_id: string, population_digest: string, items: list<array{case_id: string, variant_id: string, utterance: string}>}  $input
     * @return array<string, mixed>
     */
    public function handle(User $user, string $workspacePublicId, array $input): array
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('R28 production-path diagnostics are restricted to local/testing environments.');
        }
        $this->validateInput($input);
        $workspace = $this->workspaces->handle($user, $workspacePublicId)->workspace;
        $observations = [];

        // The diagnostic invokes the job action synchronously. Prevent the
        // normal queue dispatch from scheduling a second execution.
        Queue::fake();
        foreach ($input['items'] as $item) {
            $trace = [];
            $conversation = $this->conversations->handle($workspace, $user);
            $run = $this->messages->handle(
                $conversation,
                $user,
                $item['utterance'],
                (string) Str::uuid(),
            );
            $this->orchestrator->handle(
                $run->id,
                function (string $stage, mixed $value) use (&$trace): void {
                    $trace[$stage] = $value;
                },
            );
            $observations[] = $this->observation($run->fresh(), $item, $trace);
        }

        return [
            'schema_version' => 'r28-production-path-observations-v1',
            'subset_id' => $input['subset_id'],
            'population_id' => $input['population_id'],
            'population_digest' => $input['population_digest'],
            'workspace_id' => (string) $workspace->public_id,
            'execution_boundary' => OrchestrateConversationRun::class,
            'items' => $observations,
        ];
    }

    /** @param array<string, mixed> $input */
    private function validateInput(array $input): void
    {
        if (array_keys($input) !== ['schema_version', 'subset_id', 'population_id', 'population_digest', 'items']
            || $input['schema_version'] !== 'r28-production-path-input-v1'
            || ! is_string($input['subset_id']) || trim($input['subset_id']) === ''
            || ! is_string($input['population_id']) || trim($input['population_id']) === ''
            || ! is_string($input['population_digest']) || preg_match('/^[0-9a-f]{64}$/', $input['population_digest']) !== 1
            || ! is_array($input['items']) || count($input['items']) !== 12) {
            throw new RuntimeException('The R28 production-path execution input is invalid.');
        }
        $identities = [];
        foreach ($input['items'] as $item) {
            if (! is_array($item)
                || array_keys($item) !== ['case_id', 'variant_id', 'utterance']
                || ! is_string($item['case_id']) || trim($item['case_id']) === ''
                || ! is_string($item['variant_id']) || trim($item['variant_id']) === ''
                || ! is_string($item['utterance']) || trim($item['utterance']) === '') {
                throw new RuntimeException('R28 execution input may contain only question identities and utterances.');
            }
            $identities[] = $item['case_id'].'::'.$item['variant_id'];
        }
        if (count(array_unique($identities)) !== count($identities)) {
            throw new RuntimeException('R28 production-path execution identities must be unique.');
        }
    }

    /**
     * @param  array{case_id: string, variant_id: string, utterance: string}  $identity
     * @return array<string, mixed>
     */
    private function observation(GenerationRun $run, array $identity, array $trace): array
    {
        $run->load([
            'contextualisationResult',
            'retrievalOutcomeSnapshot',
            'generatedAnswer.answerParts.evidenceSnapshots',
            'assistantMessage',
        ]);
        $answer = $run->generatedAnswer;

        return [
            'case_id' => $identity['case_id'],
            'variant_id' => $identity['variant_id'],
            'run_id' => (string) $run->public_id,
            'status' => $run->status->value,
            'failure_code' => $run->failure_code?->value,
            'production_trace' => $trace,
            'contextualisation' => $run->contextualisationResult?->only([
                'status',
                'resolved_query',
                'clarification_question',
                'used_prior_context',
                'interpretation_metadata',
                'contextualiser_version',
                'usage',
            ]),
            'retrieval' => $run->retrievalOutcomeSnapshot?->only([
                'outcome',
                'clarification_source',
                'clarification_reason',
                'resolved_temporal_authority',
                'resolved_applicability_location',
                'lineage',
                'candidate_count',
                'evidence_count',
            ]),
            'generation' => $answer === null ? null : [
                'outcome' => $answer->outcome->value,
                'rendered_text' => $answer->renderedText(),
                'unsupported_aspects' => $answer->unsupported_aspects,
                'insufficiency_reason' => $answer->insufficiency_reason,
                'provider' => $answer->provider,
                'model' => $answer->model,
                'generation_fingerprint' => $answer->generation_fingerprint,
                'usage' => $answer->usage,
                'parts' => $answer->answerParts->map(fn ($part): array => [
                    'text' => $part->text,
                    'evidence' => $part->evidenceSnapshots->map(fn ($snapshot): array => [
                        'evidence_handle' => $snapshot->evidence_handle,
                        'document_chunk_id' => $snapshot->document_chunk_id,
                        'document_id' => $snapshot->document_id,
                    ])->all(),
                ])->all(),
            ],
            'assistant_message_kind' => $run->assistantMessage?->kind?->value,
            'usage' => $run->usage,
        ];
    }
}
