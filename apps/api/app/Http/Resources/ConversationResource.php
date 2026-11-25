<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ChatStreamEventType;
use App\Enums\DocumentStatus;
use App\Enums\GenerationRunStatus;
use App\Models\Document;
use App\Models\EvidenceSnapshot;
use App\Models\RetrievedSourceReference;
use App\Support\Pipeline\ActivePipelineCapabilities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'title' => $this->title,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'site_context' => $this->selectedLocation === null ? [
                'scope_key' => 'global',
                'location_id' => null,
                'name' => 'Organisation-wide',
            ] : [
                'scope_key' => (string) $this->selectedLocation->public_id,
                'location_id' => (string) $this->selectedLocation->public_id,
                'name' => $this->selectedLocation->name,
            ],
            'messages' => $this->whenLoaded('messages', fn () => $this->messages->map(fn ($message): array => [
                'id' => $message->public_id,
                'ordinal' => $message->ordinal,
                'role' => $message->role->value,
                'kind' => $message->kind?->value,
                'text' => $message->display_text,
                'in_reply_to_message_id' => $message->inReplyTo?->public_id,
                'created_at' => $message->created_at?->toIso8601String(),
            ])->all()),
            'runs' => $this->whenLoaded('generationRuns', fn () => $this->generationRuns->map(fn ($run): array => [
                'id' => $run->public_id,
                'user_message_id' => $run->userMessage?->public_id,
                'assistant_message_id' => $run->assistantMessage?->public_id,
                'retry_of_run_id' => $run->retryOf?->public_id,
                'status' => $run->status->value,
                'failure_code' => $run->failure_code?->value,
                'delivery_mode' => $run->delivery_mode,
                'interaction' => app(ActivePipelineCapabilities::class)->runInteraction($run),
                'presentation' => ($run->needs_profile['product_profile'] ?? null) === 'current-policy-pool-v1' ? 'saved-message-v1' : null,
                'retryable' => $run->status->isRetryEligible(),
                'provisional_content_retracted' => $run->status === GenerationRunStatus::Failed
                    && $run->relationLoaded('deliveryEvents')
                    && $run->deliveryEvents->contains(
                        'event_type',
                        ChatStreamEventType::AnswerPartAcceptedForDisplay,
                    ),
                'feedback' => $run->relationLoaded('answerFeedback') && $run->answerFeedback !== null ? [
                    'rating' => $run->answerFeedback->rating,
                    'reason_code' => $run->answerFeedback->reason_code,
                    'comment' => $run->answerFeedback->comment,
                    'submitted_at' => $run->answerFeedback->submitted_at?->toIso8601String(),
                ] : null,
                'answer' => $run->relationLoaded('generatedAnswer') && $run->generatedAnswer !== null ? ($run->generatedAnswer->contract_version === 'dify-chatflow-answer-v1' ? [
                    'id' => $run->generatedAnswer->public_id,
                    'outcome' => $run->generatedAnswer->outcome->value,
                    'presentation' => 'saved-message-v1',
                    'text' => $run->assistantMessage->display_text,
                    'sources_retrieved' => $run->generatedAnswer->retrievedSources
                        ->map(fn (RetrievedSourceReference $source): array => $this->retrievedSource($request, $source))
                        ->all(),
                ] : ($run->generatedAnswer->contract_version === 'current-policy-answer-v1' ? [
                    'id' => $run->generatedAnswer->public_id,
                    'outcome' => $run->generatedAnswer->outcome->value,
                    'presentation' => 'saved-message-v1',
                    'text' => $run->assistantMessage->display_text,
                    'scope_citations' => array_map(function (array $fact) use ($request): array {
                        $document = Document::where('workspace_id', $this->workspace_id)
                            ->where('public_id', $fact['document_version_id'])->first();
                        $available = $document !== null && $request->user()?->can('view', $document) === true
                            && ! in_array($document->status, [DocumentStatus::Deleting, DocumentStatus::Deleted], true);

                        return [...$fact, 'source_route' => $available
                            ? sprintf('/app/workspaces/%s/documents/%s', $this->workspace->public_id, $document->public_id) : null,
                            'source_state' => $available ? 'available' : 'unavailable'];
                    }, $run->generatedAnswer->scope_facts ?? []),
                    'citations' => $run->generatedAnswer->answerParts->flatMap->evidenceSnapshots->unique('id')
                        ->map(fn (EvidenceSnapshot $snapshot): array => $this->citation($request, $snapshot))->values()->all(),
                ] : [
                    'id' => $run->generatedAnswer->public_id,
                    'outcome' => $run->generatedAnswer->outcome->value,
                    'scope_citations' => $run->generatedAnswer->scope_facts ?? [],
                    'unsupported_aspects' => $run->generatedAnswer->unsupported_aspects,
                    'insufficiency_reason' => $run->generatedAnswer->insufficiency_reason,
                    'parts' => $run->generatedAnswer->answerParts->map(fn ($part): array => [
                        'id' => $part->public_id,
                        'text' => $part->text,
                        'citations' => $part->evidenceSnapshots
                            ->map(fn (EvidenceSnapshot $snapshot): array => $this->citation($request, $snapshot))
                            ->all(),
                    ])->all(),
                ])) : null,
                'created_at' => $run->created_at?->toIso8601String(),
                'completed_at' => $run->completed_at?->toIso8601String(),
            ])->all()),
        ];
    }

    /** @return array<string, mixed> */
    private function retrievedSource(Request $request, RetrievedSourceReference $source): array
    {
        $document = $source->document;
        $belongsToConversationWorkspace = $document !== null
            && $document->workspace_id === $this->workspace_id;
        $isRemoved = $belongsToConversationWorkspace
            && $document->status === DocumentStatus::Deleted;
        $isLive = $belongsToConversationWorkspace
            && ! in_array($document->status, [DocumentStatus::Deleting, DocumentStatus::Deleted], true)
            && $request->user()?->can('view', $document) === true;

        return [
            'id' => $source->public_id,
            'rank' => $source->rank,
            'score' => $source->score === null ? null : (float) $source->score,
            'document_id' => $isLive ? $document->public_id : null,
            'display_name' => $source->display_name,
            'source_state' => $isLive ? 'available' : ($isRemoved ? 'removed' : 'unavailable'),
            'source_route' => $isLive
                ? sprintf('/app/workspaces/%s/documents/%s', $this->workspace->public_id, $document->public_id)
                : null,
            'retrieved_text' => $source->retrieved_text,
        ];
    }

    /** @return array<string, mixed> */
    private function citation(Request $request, EvidenceSnapshot $snapshot): array
    {
        $document = $snapshot->document;
        $belongsToConversationWorkspace = $document !== null
            && $document->workspace_id === $this->workspace_id;
        $isRemoved = $belongsToConversationWorkspace
            && $document->status === DocumentStatus::Deleted;
        $isLive = $belongsToConversationWorkspace
            && $this->isSupportedMediaType($document->media_type)
            && ! in_array($document->status, [DocumentStatus::Deleting, DocumentStatus::Deleted], true)
            && $request->user()?->can('view', $document) === true;

        return [
            'id' => $snapshot->public_id,
            'document_id' => $isLive ? $document->public_id : null,
            'display_name' => $belongsToConversationWorkspace
                ? $this->safeDisplayName($document->source_filename)
                : 'Document source',
            'type_label' => $belongsToConversationWorkspace
                ? $this->safeTypeLabel($document->media_type)
                : 'Document',
            'size_bytes' => $belongsToConversationWorkspace ? $document->size_bytes : null,
            'source_state' => $isLive ? 'available' : ($isRemoved ? 'removed' : 'unavailable'),
            'source_route' => $isLive
                ? sprintf(
                    '/app/workspaces/%s/documents/%s',
                    $this->workspace->public_id,
                    $document->public_id,
                )
                : null,
            'cited_text' => $snapshot->cited_text_verbatim,
            'source_provenance' => $snapshot->source_provenance,
            'provenance_label' => $this->safeProvenanceLabel($snapshot->source_provenance),
        ];
    }

    private function safeDisplayName(string $filename): string
    {
        $basename = basename(str_replace('\\', '/', $filename));
        $sanitised = preg_replace('/[\x00-\x1F\x7F]/u', '', $basename);

        return is_string($sanitised) && trim($sanitised) !== ''
            ? trim($sanitised)
            : 'Document source';
    }

    private function safeTypeLabel(string $mediaType): string
    {
        return match (mb_strtolower(trim($mediaType))) {
            'application/pdf' => 'PDF document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word document',
            'text/plain' => 'Text document',
            default => 'Document',
        };
    }

    private function isSupportedMediaType(string $mediaType): bool
    {
        return in_array(mb_strtolower(trim($mediaType)), [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
        ], true);
    }

    /** @param array<int, mixed> $provenance */
    private function safeProvenanceLabel(array $provenance): string
    {
        $location = collect($provenance)
            ->flatMap(fn (mixed $item): array => is_array($item) && is_array($item['source_locations'] ?? null)
                ? $item['source_locations']
                : (is_array($item) ? [$item] : []))
            ->first(fn (mixed $item): bool => is_array($item));

        if (! is_array($location)) {
            return 'Source location preserved';
        }
        if (is_numeric($location['page_number'] ?? null)) {
            return 'PDF · page '.(int) $location['page_number'];
        }
        if (is_numeric($location['start_line'] ?? null)) {
            $start = (int) $location['start_line'];
            $end = is_numeric($location['end_line'] ?? null) ? (int) $location['end_line'] : $start;

            return $start === $end ? "Text source · line {$start}" : "Text source · lines {$start}–{$end}";
        }
        if (is_numeric($location['table_row_index'] ?? null)) {
            return 'Word document · table row '.((int) $location['table_row_index'] + 1);
        }

        return 'Source location preserved';
    }
}
