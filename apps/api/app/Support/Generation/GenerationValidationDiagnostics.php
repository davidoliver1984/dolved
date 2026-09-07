<?php

declare(strict_types=1);

namespace App\Support\Generation;

use Throwable;

final class GenerationValidationDiagnostics
{
    private const RULES = [
        'Generation result needs differ from the assembled request.' => ['aspect_links', 'request_needs'],
        'The needs generation result schema is invalid.' => ['result', 'needs_schema'],
        'The generation result schema is invalid.' => ['result', 'result_schema'],
        'The generation result fields are invalid.' => ['result', 'result_fields'],
        'An answer part is structurally invalid.' => ['answer_parts', 'part_shape'],
        'An answer part cites unauthorised evidence.' => ['answer_parts.evidence_ids', 'citation_membership'],
        'The generation outcome invariant is invalid.' => ['outcome', 'outcome_invariant'],
        'Generation usage must be an object or null.' => ['usage', 'usage_shape'],
        'Invalid aspect need identity.' => ['aspect_links', 'need_identity'],
        'Invalid generation outcome shape.' => ['outcome', 'outcome_invariant'],
        'Aspect links must cover each need exactly once.' => ['aspect_links', 'need_coverage'],
        'Every need must map an answer or gap.' => ['aspect_links', 'empty_need'],
        'Every authoritative answer and gap must be mapped.' => ['aspect_links', 'unmapped_content'],
        'Generation outcome contradicts aspect coverage.' => ['aspect_links', 'coverage_outcome'],
        'Invalid aspect reference list.' => ['aspect_links', 'reference_list'],
        'Aspect references must be unique and in range.' => ['aspect_links', 'reference_range'],
        'Answer cites another need evidence.' => ['answer_parts.evidence_ids', 'citation_ownership'],
        'Need without evidence requires a gap.' => ['aspect_links', 'missing_evidence_gap'],
    ];

    public static function local(Throwable $exception): array
    {
        // Prefer the actual coverage predicate to its generic wrapper, bounded depth.
        $cause = $exception->getPrevious() ?? $exception;
        [$field, $code] = self::RULES[$cause->getMessage()] ?? ['result', 'unclassified_validation'];

        return ['category' => 'contract_validation_failure', 'layer' => 'laravel_result_validation',
            'field' => $field, 'code' => $code, 'http_status' => null];
    }

    public static function remote(mixed $diagnostic): array
    {
        $pairs = [
            'detached_polarity' => 'answer_parts.text', 'outcome_invariant' => 'outcome',
            'citation_membership' => 'answer_parts.evidence_ids', 'duplicate_citation' => 'answer_parts.evidence_ids',
            'blank_answer' => 'answer_parts.text', 'need_coverage' => 'aspect_links',
            'reference_range' => 'aspect_links', 'empty_need' => 'aspect_links',
            'unmapped_content' => 'aspect_links', 'coverage_outcome' => 'aspect_links',
            'citation_ownership' => 'answer_parts.evidence_ids', 'missing_evidence_gap' => 'aspect_links',
            'schema_validation' => 'result',
        ];
        $code = is_array($diagnostic) ? ($diagnostic['code'] ?? null) : null;
        if (! is_string($code) || ! isset($pairs[$code]) || ($diagnostic['field'] ?? null) !== $pairs[$code]) {
            return ['field' => null, 'code' => null];
        }

        return ['field' => $pairs[$code], 'code' => $code];
    }
}
