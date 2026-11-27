<?php

declare(strict_types=1);

namespace App\Support\Pipeline;

use App\Enums\DocumentStatus;
use App\Enums\ExtractionProjectionStatus;
use App\Models\DifyWorkspaceBinding;
use App\Models\Document;
use App\Models\DocumentExtractionProjectionGeneration;
use App\Models\GenerationRun;
use App\Models\Workspace;

final class ActivePipelineCapabilities
{
    /** @return array{document_processing: string, chat_generation: string, status: string} */
    public function workspace(Workspace $workspace): array
    {
        $dify = DifyWorkspaceBinding::query()
            ->where('workspace_id', $workspace->id)
            ->where('enabled', true)
            ->exists();
        $documentDriver = (string) config('documents.processing_driver');

        return [
            'document_processing' => $documentDriver === 'dify' && $dify
                ? 'dify'
                : ($documentDriver === 'legacy' ? 'custom' : 'unavailable'),
            'chat_generation' => $dify ? 'dify' : 'custom',
            'status' => ($documentDriver === 'dify' && ! $dify) ? 'unavailable' : 'available',
        ];
    }

    /** @return array{extracted_text: bool, projection: string} */
    public function documentPresentation(Document $document): array
    {
        $generation = $document->relationLoaded('activeExtractionProjectionGeneration')
            ? $document->activeExtractionProjectionGeneration
            : $document->activeExtractionProjectionGeneration()->first();
        $available = $document->status === DocumentStatus::Indexed
            && $generation instanceof DocumentExtractionProjectionGeneration
            && $generation->status === ExtractionProjectionStatus::Published;

        return [
            'extracted_text' => $available,
            'projection' => $available ? 'published_custom_projection' : 'unavailable',
        ];
    }

    public function comparable(Document $from, Document $to): bool
    {
        if (! $this->documentPresentation($from)['extracted_text']
            || ! $this->documentPresentation($to)['extracted_text']) {
            return false;
        }

        $fromIdentity = $this->projectionIdentity($from->activeExtractionProjectionGeneration);
        $toIdentity = $this->projectionIdentity($to->activeExtractionProjectionGeneration);

        return $fromIdentity !== null && $fromIdentity === $toIdentity;
    }

    /** @return array{progress: string, stop_action: string} */
    public function runInteraction(GenerationRun $run): array
    {
        $pendingDify = $run->generation_endpoint === null
            && ! $run->status->isTerminal()
            && DifyWorkspaceBinding::query()
                ->where('workspace_id', $run->workspace_id)
                ->where('enabled', true)
                ->exists();
        if ($run->generation_endpoint === 'dify.chat-messages' || $pendingDify) {
            return ['progress' => 'dify_blocking', 'stop_action' => 'stop_waiting'];
        }

        return ['progress' => 'custom_pipeline', 'stop_action' => 'cancel'];
    }

    private function projectionIdentity(?DocumentExtractionProjectionGeneration $generation): ?string
    {
        if (! $generation instanceof DocumentExtractionProjectionGeneration) {
            return null;
        }
        if (! is_array($generation->source_extractor) || $generation->source_extractor === []
            || ! is_array($generation->normaliser) || $generation->normaliser === []) {
            return null;
        }

        return hash('sha256', json_encode([
            'source_extractor' => $generation->source_extractor,
            'normaliser' => $generation->normaliser,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
