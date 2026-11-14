<?php

declare(strict_types=1);

namespace App\Support\Generation;

use App\Exceptions\GenerationCitationOwnershipException;
use App\Exceptions\GenerationServiceAuthenticationException;
use Throwable;

final class GenerationValidationDiagnostics
{
    private const RULES = [
        'Temporal answer-use coverage is invalid.' => ['result', 'temporal_target_invalid'],
        'Temporal answer target is unavailable or ungrounded.' => ['result', 'temporal_target_invalid'],
        'Temporal answer reference mismatch.' => ['result', 'temporal_target_invalid'],
        'Temporal evidence ownership or version mismatch.' => ['result', 'temporal_target_invalid'],
        'Temporal evidence interval mismatch.' => ['result', 'temporal_target_invalid'],
        'Temporal answer-use coverage is incomplete.' => ['result', 'temporal_target_invalid'],
        'Generation result needs differ from the assembled request.' => ['aspect_links', 'request_needs'],
        'The needs generation result schema is invalid.' => ['result', 'needs_schema'],
        'The needs generation entailment coverage is invalid.' => ['answer_parts.text', 'entailment_coverage'],
        'The needs generation entailment verdict is invalid.' => ['answer_parts.text', 'entailment_verdict'],
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
        if ($exception instanceof GenerationServiceAuthenticationException) {
            return ['category' => 'service_authentication_failure', 'layer' => 'python_service_authentication',
                'field' => 'request', 'code' => $exception->replayRejected ? 'replay_rejected' : 'authentication_rejected', 'http_status' => 401];
        }
        // Prefer the actual coverage predicate to its generic wrapper, bounded depth.
        $cause = $exception->getPrevious() ?? $exception;
        [$field, $code] = self::RULES[$cause->getMessage()] ?? ['result', 'unclassified_validation'];

        return ['category' => 'contract_validation_failure', 'layer' => 'laravel_result_validation',
            'field' => $field, 'code' => $code, 'http_status' => null,
            ...($exception instanceof GenerationCitationOwnershipException
                ? self::ownership($exception->ownership) : [])];
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
            'site_scope_condition_unproven' => 'answer_parts.text',
            'entailment_unsupported' => 'answer_parts.text', 'entailment_uncertain' => 'answer_parts.text',
            'schema_validation' => 'result',
            'temporal_target_invalid' => 'result',
        ];
        $code = is_array($diagnostic) ? ($diagnostic['code'] ?? null) : null;
        if (! is_string($code) || ! isset($pairs[$code]) || ($diagnostic['field'] ?? null) !== $pairs[$code]) {
            return ['field' => null, 'code' => null];
        }

        return ['field' => $pairs[$code], 'code' => $code,
            ...($code === 'citation_ownership' ? self::ownership($diagnostic['ownership'] ?? null) : [])];
    }

    private static function ownership(mixed $value): array
    {
        if (! is_array($value) || ! in_array($value['need_id'] ?? null, ['n1', 'n2', 'n3'], true)
            || ! is_int($value['answer_part_index'] ?? null) || $value['answer_part_index'] < 0 || $value['answer_part_index'] > 127) {
            return [];
        }
        foreach (['cited_ids', 'allowed_ids'] as $key) {
            if (! is_array($value[$key] ?? null) || ! array_is_list($value[$key]) || count($value[$key]) > 5) {
                return [];
            }
            foreach ($value[$key] as $id) {
                if (! is_string($id) || strlen($id) > 16 || preg_match('/^ev-[0-9]{2,}$/D', $id) !== 1) {
                    return [];
                }
            }
        }

        return ['ownership' => array_intersect_key($value, array_flip(['need_id', 'answer_part_index', 'cited_ids', 'allowed_ids']))];
    }
}
