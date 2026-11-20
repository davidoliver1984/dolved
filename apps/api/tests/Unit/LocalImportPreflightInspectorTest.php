<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Imports\LocalImportPreflightInspector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocalImportPreflightInspectorTest extends TestCase
{
    public function test_readable_pdf_is_verified_with_immutable_source_facts(): void
    {
        $source = "%PDF-1.7\n1 0 obj\n<<>>\nendobj\n%%EOF\n";

        $this->assertSame([
            'result' => 'readable',
            'diagnostic_code' => 'readable',
            'source_checksum_sha256' => hash('sha256', $source),
            'media_type' => 'application/pdf',
            'size_bytes' => strlen($source),
        ], (new LocalImportPreflightInspector)->inspect($source, 'application/pdf'));
    }

    #[DataProvider('rejections')]
    public function test_invalid_sources_fail_closed(string $source, string $declared, array $expected): void
    {
        $this->assertSame($expected, (new LocalImportPreflightInspector)->inspect($source, $declared));
    }

    public static function rejections(): array
    {
        return [
            'encrypted pdf' => [
                "%PDF-1.7\n/Encrypt 2 0 R\n%%EOF",
                'application/pdf',
                ['result' => 'password_protected', 'diagnostic_code' => 'pdf_password_required'],
            ],
            'truncated pdf' => [
                "%PDF-1.7\n1 0 obj\n<<>>",
                'application/pdf',
                ['result' => 'corrupt_structure', 'diagnostic_code' => 'invalid_container'],
            ],
            'declared mismatch' => [
                'plain text',
                'application/pdf',
                ['result' => 'mime_mismatch', 'diagnostic_code' => 'declared_type_mismatch'],
            ],
        ];
    }
}
