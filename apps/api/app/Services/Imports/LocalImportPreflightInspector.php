<?php

declare(strict_types=1);

namespace App\Services\Imports;

use ZipArchive;

final class LocalImportPreflightInspector
{
    /** @return array{result: string, diagnostic_code: string, source_checksum_sha256?: string, media_type?: string, size_bytes?: int} */
    public function inspect(string $source, string $declaredMediaType): array
    {
        $detected = $this->detectMediaType($source);

        if ($detected === 'application/x-ole-storage'
            && $declaredMediaType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            return ['result' => 'encrypted', 'diagnostic_code' => 'office_encrypted'];
        }

        if (! $this->declaredTypeMatches($detected, $declaredMediaType)) {
            return ['result' => 'mime_mismatch', 'diagnostic_code' => 'declared_type_mismatch'];
        }

        if ($detected === 'application/pdf') {
            if (str_contains($source, '/Encrypt')) {
                return ['result' => 'password_protected', 'diagnostic_code' => 'pdf_password_required'];
            }
            if (! str_contains(substr($source, -2048), '%%EOF')) {
                return ['result' => 'corrupt_structure', 'diagnostic_code' => 'invalid_container'];
            }
        } elseif ($detected === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            && ! $this->validDocx($source)) {
            return ['result' => 'corrupt_structure', 'diagnostic_code' => 'invalid_container'];
        } elseif (str_starts_with($detected, 'text/') && ! mb_check_encoding($source, 'UTF-8')) {
            return ['result' => 'corrupt_structure', 'diagnostic_code' => 'invalid_container'];
        }

        return [
            'result' => 'readable',
            'diagnostic_code' => 'readable',
            'source_checksum_sha256' => hash('sha256', $source),
            'media_type' => $declaredMediaType,
            'size_bytes' => strlen($source),
        ];
    }

    private function detectMediaType(string $source): string
    {
        if (str_starts_with($source, '%PDF-')) {
            return 'application/pdf';
        }
        if (str_starts_with($source, hex2bin('d0cf11e0a1b11ae1'))) {
            return 'application/x-ole-storage';
        }
        if (str_starts_with($source, 'PK')) {
            return $this->validDocx($source)
                ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                : 'application/zip';
        }
        if (str_starts_with(ltrim($source), '{\\rtf')) {
            return 'application/rtf';
        }

        return mb_check_encoding($source, 'UTF-8') ? 'text/plain' : 'application/octet-stream';
    }

    private function declaredTypeMatches(string $detected, string $declared): bool
    {
        if ($detected === $declared) {
            return true;
        }

        return ($detected === 'text/plain' && $declared === 'text/markdown')
            || ($detected === 'application/rtf' && $declared === 'text/rtf')
            || ($detected === 'application/x-ole-storage' && $declared === 'application/msword');
    }

    private function validDocx(string $source): bool
    {
        $temporary = tmpfile();
        if ($temporary === false) {
            return false;
        }

        try {
            fwrite($temporary, $source);
            $metadata = stream_get_meta_data($temporary);
            $archive = new ZipArchive;
            if ($archive->open($metadata['uri']) !== true) {
                return false;
            }
            $valid = $archive->locateName('word/document.xml') !== false;
            $archive->close();

            return $valid;
        } finally {
            fclose($temporary);
        }
    }
}
