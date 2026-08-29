<?php

declare(strict_types=1);

namespace App\Actions\Ingestion;

use App\Enums\ExtractionProjectionStatus;
use App\Exceptions\IngestionAttemptException;
use App\Models\Document;
use App\Models\DocumentExtractionArtifact;
use App\Models\DocumentExtractionProjectionElement;
use App\Models\DocumentExtractionProjectionGeneration;
use App\Models\DocumentExtractionProjectionWarning;
use App\Services\Documents\ExtractionArtifactStreamReader;
use App\Services\Documents\StructuredExtractionCanonicaliser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class BuildAndPublishExtractionProjection
{
    public function __construct(
        private readonly ExtractionArtifactStreamReader $reader,
        private readonly StructuredExtractionCanonicaliser $canonicaliser,
    ) {}

    public function handle(DocumentExtractionArtifact $artifact): DocumentExtractionProjectionGeneration
    {
        if (! in_array($artifact->contract_version, config('ingestion.orchestration.extraction_artifact_contract_versions'), true)) {
            throw IngestionAttemptException::invalid('extraction_artifact_contract_unsupported', 'The extraction artifact contract version is unsupported.', 422);
        }
        if ($artifact->element_count > max(1, (int) config('ingestion.orchestration.extraction_artifact_max_elements'))) {
            throw IngestionAttemptException::invalid('extraction_artifact_element_limit_exceeded', 'The extraction artifact contains too many elements.', 422);
        }
        if ($artifact->warning_count > max(0, (int) config('ingestion.orchestration.extraction_artifact_max_warnings'))) {
            throw IngestionAttemptException::invalid('extraction_artifact_warning_limit_exceeded', 'The extraction artifact contains too many warnings.', 422);
        }
        $current = DocumentExtractionProjectionGeneration::query()
            ->where('document_extraction_artifact_id', $artifact->id)
            ->where('status', ExtractionProjectionStatus::Published)
            ->first();
        if ($current !== null) {
            return $current;
        }

        $generation = DocumentExtractionProjectionGeneration::query()->create([
            'public_id' => (string) Str::uuid(),
            'workspace_id' => $artifact->workspace_id,
            'document_id' => $artifact->document_id,
            'document_extraction_artifact_id' => $artifact->id,
            'status' => ExtractionProjectionStatus::Building,
            'expected_element_count' => $artifact->element_count,
            'expected_warning_count' => $artifact->warning_count,
            'expected_projection_manifest_digest' => $artifact->projection_manifest_digest,
            'expected_warning_manifest_digest' => $artifact->warning_manifest_digest,
        ]);

        try {
            $this->build($generation, $artifact);

            return $this->verifyAndPublish($generation, $artifact);
        } catch (Throwable $error) {
            DocumentExtractionProjectionGeneration::query()
                ->whereKey($generation->id)
                ->where('status', ExtractionProjectionStatus::Building)
                ->update([
                    'status' => ExtractionProjectionStatus::Failed->value,
                    'failure_code' => $this->failureCode($error),
                    'failed_at' => now(),
                    'updated_at' => now(),
                ]);
            if ($error instanceof IngestionAttemptException) {
                throw $error;
            }

            report($error);
            throw IngestionAttemptException::invalid(
                'extraction_projection_invalid',
                'The verified extraction artifact could not be projected.',
                422,
            );
        }
    }

    private function build(DocumentExtractionProjectionGeneration $generation, DocumentExtractionArtifact $artifact): void
    {
        $batchSize = max(1, (int) config('ingestion.orchestration.extraction_projection_batch_size'));
        $deadline = hrtime(true) + max(1, (int) config('ingestion.orchestration.extraction_projection_timeout_seconds')) * 1_000_000_000;
        $batch = [];
        foreach ($this->reader->elements($artifact->object_key) as $index => $element) {
            $this->assertWithinDeadline($deadline);
            $this->assertElement($element, $index);
            $batch[] = [
                'projection_generation_id' => $generation->id,
                'workspace_id' => $artifact->workspace_id,
                'document_id' => $artifact->document_id,
                'element_id' => strtolower((string) $element['id']),
                'ordinal' => (int) $element['ordinal'],
                'kind' => (string) $element['kind'],
                'text' => (string) $element['text'],
                'payload' => json_encode($element, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (count($batch) >= $batchSize) {
                DocumentExtractionProjectionElement::query()->insert($batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            DocumentExtractionProjectionElement::query()->insert($batch);
        }

        $batch = [];
        foreach ($this->reader->warnings($artifact->object_key) as $ordinal => $warning) {
            $this->assertWithinDeadline($deadline);
            $batch[] = [
                'projection_generation_id' => $generation->id,
                'ordinal' => $ordinal,
                'payload' => json_encode($warning, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (count($batch) >= $batchSize) {
                DocumentExtractionProjectionWarning::query()->insert($batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            DocumentExtractionProjectionWarning::query()->insert($batch);
        }

        $generation->forceFill([
            'source_extractor' => $this->reader->objectAt($artifact->object_key, '/source_extractor'),
            'normaliser' => $this->reader->objectAt($artifact->object_key, '/normaliser'),
            'metadata' => $this->reader->objectAt($artifact->object_key, '/metadata'),
            'changes' => $this->reader->arrayAt($artifact->object_key, '/changes'),
        ])->save();
    }

    private function verifyAndPublish(
        DocumentExtractionProjectionGeneration $generation,
        DocumentExtractionArtifact $artifact,
    ): DocumentExtractionProjectionGeneration {
        $elements = DocumentExtractionProjectionElement::query()
            ->where('projection_generation_id', $generation->id)
            ->orderBy('ordinal')->orderBy('element_id')
            ->cursor()
            ->map(fn (DocumentExtractionProjectionElement $row): array => $row->payload);
        $warnings = DocumentExtractionProjectionWarning::query()
            ->where('projection_generation_id', $generation->id)
            ->orderBy('ordinal')
            ->cursor()
            ->map(fn (DocumentExtractionProjectionWarning $row): array => $row->payload);
        $elementCount = DocumentExtractionProjectionElement::query()->where('projection_generation_id', $generation->id)->count();
        $warningCount = DocumentExtractionProjectionWarning::query()->where('projection_generation_id', $generation->id)->count();
        $projectionDigest = $this->canonicaliser->manifestDigest($elements);
        $warningDigest = $this->canonicaliser->manifestDigest($warnings);

        if ($elementCount !== $artifact->element_count || $warningCount !== $artifact->warning_count
            || $projectionDigest !== $artifact->projection_manifest_digest
            || $warningDigest !== $artifact->warning_manifest_digest) {
            throw IngestionAttemptException::invalid(
                'extraction_projection_identity_mismatch',
                'The persisted extraction projection does not match the verified artifact.',
                422,
            );
        }

        return DB::transaction(function () use ($generation, $artifact, $projectionDigest, $warningDigest): DocumentExtractionProjectionGeneration {
            $document = Document::query()->whereKey($artifact->document_id)->lockForUpdate()->firstOrFail();
            $locked = DocumentExtractionProjectionGeneration::query()->whereKey($generation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== ExtractionProjectionStatus::Building) {
                throw IngestionAttemptException::invalid('extraction_projection_publish_conflict', 'The extraction projection is no longer publishable.', 409);
            }

            $active = DocumentExtractionProjectionGeneration::query()
                ->whereKey($document->active_extraction_projection_generation_id)
                ->lockForUpdate()
                ->first();
            if ($active?->document_extraction_artifact_id === $artifact->id
                && $active->status === ExtractionProjectionStatus::Published) {
                $locked->forceFill([
                    'status' => ExtractionProjectionStatus::Retired,
                    'verified_projection_manifest_digest' => $projectionDigest,
                    'verified_warning_manifest_digest' => $warningDigest,
                    'verified_at' => now(),
                    'retired_at' => now(),
                ])->save();

                return $active;
            }

            DocumentExtractionProjectionGeneration::query()
                ->whereKey($document->active_extraction_projection_generation_id)
                ->where('status', ExtractionProjectionStatus::Published)
                ->update([
                    'status' => ExtractionProjectionStatus::Retired->value,
                    'retired_at' => now(),
                    'updated_at' => now(),
                ]);
            $locked->forceFill([
                'status' => ExtractionProjectionStatus::Published,
                'verified_projection_manifest_digest' => $projectionDigest,
                'verified_warning_manifest_digest' => $warningDigest,
                'verified_at' => now(),
                'published_at' => now(),
            ])->save();
            $document->forceFill(['active_extraction_projection_generation_id' => $locked->id])->save();
            $artifact->forceFill(['published_at' => now()])->save();

            return $locked->refresh();
        });
    }

    /** @param array<string, mixed> $element */
    private function assertElement(array $element, int $index): void
    {
        if (! is_string($element['id'] ?? null) || ! Str::isUuid($element['id'])
            || ! is_int($element['ordinal'] ?? null) || $element['ordinal'] < 0
            || ! in_array($element['kind'] ?? null, ['paragraph', 'heading', 'table', 'unknown'], true)
            || ! is_string($element['text'] ?? null)) {
            throw new InvalidArgumentException("Extraction artifact element {$index} is invalid.");
        }
        if (strlen($element['text']) > max(1, (int) config('ingestion.orchestration.extraction_artifact_max_element_text_bytes'))) {
            throw IngestionAttemptException::invalid('extraction_artifact_element_text_limit_exceeded', 'An extraction artifact element exceeds the configured text limit.', 422);
        }
    }

    private function assertWithinDeadline(int $deadline): void
    {
        if (hrtime(true) > $deadline) {
            throw IngestionAttemptException::invalid('extraction_projection_timeout', 'The extraction projection exceeded its configured processing timeout.', 422);
        }
    }

    private function failureCode(Throwable $error): string
    {
        return $error instanceof IngestionAttemptException
            ? $error->errorCode
            : 'extraction_projection_invalid';
    }
}
