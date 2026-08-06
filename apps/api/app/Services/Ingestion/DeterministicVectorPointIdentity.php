<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

class DeterministicVectorPointIdentity
{
    private const NAMESPACE = '7f5d227f-546b-5859-a581-2785518a0225';

    public function forChunk(
        string $embeddingSpaceGenerationId,
        string $workspaceId,
        string $workspaceCorpusGenerationId,
        string $chunkId,
    ): string {
        $name = implode("\n", [
            $embeddingSpaceGenerationId,
            $workspaceId,
            $workspaceCorpusGenerationId,
            $chunkId,
        ]);
        $namespaceBytes = hex2bin(str_replace('-', '', self::NAMESPACE));
        $hash = sha1($namespaceBytes.$name);
        $hex = substr($hash, 0, 32);
        $hex[12] = '5';
        $variant = hexdec($hex[16]);
        $hex[16] = dechex(($variant & 0x3) | 0x8);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
