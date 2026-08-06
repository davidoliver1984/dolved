<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Ingestion\DeterministicVectorPointIdentity;
use App\Services\Ingestion\IngestionCanonicaliser;
use JsonException;
use PHPUnit\Framework\TestCase;

class IngestionCanonicaliserTest extends TestCase
{
    /** @throws JsonException */
    public function test_shared_cross_language_canonicalisation_vector(): void
    {
        $path = dirname(__DIR__, 4).'/contracts/http/ingestion-worker/v1/canonicalisation-vectors.json';
        $vector = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $canonicaliser = new IngestionCanonicaliser;
        $chunk = $vector['chunk'];

        $this->assertSame($vector['chunk_content_digest'], $canonicaliser->chunkContentDigest($chunk));
        $this->assertSame($vector['chunk_manifest_digest'], $canonicaliser->chunkManifestDigest([[
            'chunk_id' => $chunk['chunk_id'],
            'ordinal' => $chunk['ordinal'],
            'content_digest' => $vector['chunk_content_digest'],
        ]]));
        $this->assertSame($vector['point_manifest_digest'], $canonicaliser->pointManifestDigest($vector['point_ids']));
        $identity = $vector['point_identity'];
        $this->assertSame(
            $identity['expected_point_id'],
            (new DeterministicVectorPointIdentity)->forChunk(
                $identity['embedding_space_generation_id'],
                $identity['workspace_id'],
                $identity['workspace_corpus_generation_id'],
                $identity['chunk_id'],
            ),
        );
    }
}
