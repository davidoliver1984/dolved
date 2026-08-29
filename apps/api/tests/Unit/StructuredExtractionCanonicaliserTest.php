<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Documents\StructuredExtractionCanonicaliser;
use LogicException;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

class StructuredExtractionCanonicaliserTest extends TestCase
{
    /** @return array<string, mixed> */
    private function vectors(): array
    {
        $path = dirname(__DIR__, 4).'/contracts/documents/extraction-artifact/v1/canonicalisation-vectors.json';

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_shared_vector_matches_schema_and_all_three_digests(): void
    {
        $root = dirname(__DIR__, 4).'/contracts/documents/extraction-artifact/v1';
        $vectors = $this->vectors();
        $schema = json_decode((string) file_get_contents($root.'/document-extraction-artifact-v1.schema.json'));
        $artifactObject = json_decode(json_encode($vectors['artifact'], JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);

        $this->assertTrue((new Validator)->validate($artifactObject, $schema)->isValid());

        $canonicaliser = new StructuredExtractionCanonicaliser;
        $this->assertSame($vectors['expected']['artifact_sha256'], $canonicaliser->artifactDigest($vectors['artifact']));
        $this->assertSame($vectors['expected']['projection_manifest_sha256'], $canonicaliser->projectionManifestDigest($vectors['artifact']));
        $this->assertSame($vectors['expected']['warning_manifest_sha256'], $canonicaliser->warningManifestDigest($vectors['artifact']));
    }

    public function test_uuid_and_unicode_rules_are_canonical_without_content_normalisation(): void
    {
        $vectors = $this->vectors();
        $canonical = (new StructuredExtractionCanonicaliser)->canonicalBytes($vectors['artifact']);

        $this->assertStringNotContainsString('AAAAAAAA-AAAA', $canonical);
        $this->assertStringContainsString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1', $canonical);
        $this->assertNotSame($vectors['unicode_distinct_pair'][0], $vectors['unicode_distinct_pair'][1]);
        foreach ($vectors['unicode_distinct_pair'] as $value) {
            $this->assertStringContainsString($value, $canonical);
        }
    }

    public function test_non_finite_numbers_fail_closed(): void
    {
        $artifact = $this->vectors()['artifact'];
        $artifact['elements'][0]['confidence'] = NAN;

        $this->expectException(LogicException::class);
        (new StructuredExtractionCanonicaliser)->canonicalBytes($artifact);
    }

    public function test_shared_rfc8785_number_vectors_preserve_full_precision(): void
    {
        $canonicaliser = new StructuredExtractionCanonicaliser;

        foreach ($this->vectors()['number_vectors'] as $vector) {
            $this->assertSame($vector['canonical'], $canonicaliser->canonicalBytes(['value' => $vector['value']]));
        }
    }

    public function test_schema_rejects_ownership_fields(): void
    {
        $root = dirname(__DIR__, 4).'/contracts/documents/extraction-artifact/v1';
        $vectors = $this->vectors();
        $vectors['artifact']['workspace_id'] = '00000000-0000-4000-8000-000000000001';
        $schema = json_decode((string) file_get_contents($root.'/document-extraction-artifact-v1.schema.json'));
        $artifactObject = json_decode(json_encode($vectors['artifact'], JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);

        $this->assertFalse((new Validator)->validate($artifactObject, $schema)->isValid());
    }
}
