<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\Ingestion\BuildAndPublishExtractionProjection;
use App\Exceptions\IngestionAttemptException;
use App\Models\DocumentExtractionArtifact;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

final class ExtractionProjectionPolicyTest extends TestCase
{
    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidDeclaredLimits(): array
    {
        return [
            'unsupported schema' => [
                ['contract_version' => 'document-extraction-artifact-v2', 'element_count' => 1, 'warning_count' => 0],
                'extraction_artifact_contract_unsupported',
            ],
            'element count' => [
                ['contract_version' => 'document-extraction-artifact-v1', 'element_count' => 2, 'warning_count' => 0],
                'extraction_artifact_element_limit_exceeded',
            ],
            'warning count' => [
                ['contract_version' => 'document-extraction-artifact-v1', 'element_count' => 1, 'warning_count' => 2],
                'extraction_artifact_warning_limit_exceeded',
            ],
        ];
    }

    /** @param array<string, mixed> $attributes */
    #[DataProvider('invalidDeclaredLimits')]
    public function test_declared_projection_limits_fail_closed(array $attributes, string $expectedCode): void
    {
        config()->set('ingestion.orchestration.extraction_artifact_contract_versions', ['document-extraction-artifact-v1']);
        config()->set('ingestion.orchestration.extraction_artifact_max_elements', 1);
        config()->set('ingestion.orchestration.extraction_artifact_max_warnings', 1);

        try {
            app(BuildAndPublishExtractionProjection::class)->handle(new DocumentExtractionArtifact($attributes));
            $this->fail('An out-of-policy artifact reached projection construction.');
        } catch (IngestionAttemptException $exception) {
            $this->assertSame($expectedCode, $exception->errorCode);
        }
    }

    public function test_element_text_and_projection_timeout_fail_with_typed_codes(): void
    {
        $action = app(BuildAndPublishExtractionProjection::class);
        config()->set('ingestion.orchestration.extraction_artifact_max_element_text_bytes', 3);
        $elementCheck = new ReflectionMethod($action, 'assertElement');
        try {
            $elementCheck->invoke($action, [
                'id' => '00000000-0000-4000-8000-000000000001',
                'ordinal' => 0,
                'kind' => 'paragraph',
                'text' => 'four',
            ], 0);
            $this->fail('An over-limit element was accepted.');
        } catch (IngestionAttemptException $exception) {
            $this->assertSame('extraction_artifact_element_text_limit_exceeded', $exception->errorCode);
        }

        $deadlineCheck = new ReflectionMethod($action, 'assertWithinDeadline');
        try {
            $deadlineCheck->invoke($action, hrtime(true) - 1);
            $this->fail('An expired projection deadline was accepted.');
        } catch (IngestionAttemptException $exception) {
            $this->assertSame('extraction_projection_timeout', $exception->errorCode);
        }
    }
}
