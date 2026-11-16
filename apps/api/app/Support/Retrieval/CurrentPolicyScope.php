<?php

declare(strict_types=1);

namespace App\Support\Retrieval;

use InvalidArgumentException;

/** Scope only; does not establish evidence support or resolve source authority. */
final class CurrentPolicyScope
{
    public const CONTRACT = 'current-policy-needs-v1';

    public const DECLINE = 'This pilot answers current policy only. I cannot answer the historical or version-comparison part.';

    public const INTERPRETATION_FAILURE = 'I could not safely interpret this part of your request.';

    public const AMBIGUITY = 'Please clarify what you mean by this part of your request.';

    public static function base(array $value): array
    {
        // Detach caller-held references before stripping annotation-only fields.
        $value = json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        if (($value['schema_version'] ?? null) !== self::CONTRACT) {
            throw new InvalidArgumentException('Invalid current-policy identity.');
        }
        $value['schema_version'] = 'evidence-needs-plan-v4';
        foreach ($value['needs'] as &$need) {
            unset($need['requested_information'], $need['current_spans'], $need['history_spans'], $need['scope_error']);
        }
        unset($need);

        return $value;
    }

    public static function validate(array $value, array $turns, ?string $evaluatedAt): void
    {
        EvidenceNeedsPlan::validate(self::base($value), $turns, $evaluatedAt);
        $invalid = [];
        foreach ($value['needs'] as $need) {
            foreach (['requested_information', 'current_spans', 'history_spans'] as $field) {
                if (! array_key_exists($field, $need)) {
                    throw new InvalidArgumentException('Missing current-policy scope field.');
                }
            }
            $error = $need['scope_error'] ?? null;
            if ($error !== null) {
                if (! in_array($error, ['invalid_scope', 'dependent_scope'], true)
                    || $need['requested_information'] !== null || $need['current_spans'] !== [] || $need['history_spans'] !== []) {
                    throw new InvalidArgumentException('Invalid quarantined scope.');
                }
                $invalid[] = $need['need_id'];

                continue;
            }
            self::validateNeed($need);
        }
        foreach ($value['needs'] as $need) {
            if (in_array($need['supports_need_id'], $invalid, true) && ! in_array($need['need_id'], $invalid, true)) {
                throw new InvalidArgumentException('Dependency on quarantined scope.');
            }
        }
    }

    private static function validateNeed(array $need): void
    {
        $kind = $need['requested_information'];
        if (! in_array($kind, ['current_policy', 'history', 'mixed', 'unclear'], true)
            || ! is_array($need['current_spans']) || ! is_array($need['history_spans'])
            || count($need['current_spans']) > 4 || count($need['history_spans']) > 4) {
            throw new InvalidArgumentException('Invalid scope annotation.');
        }
        $spans = [...$need['current_spans'], ...$need['history_spans']];
        if ($kind !== 'mixed') {
            if ($spans !== [] || ($kind === 'current_policy' && ($need['authority']['kind'] !== 'current' || $need['operation'] === 'compare'))) {
                throw new InvalidArgumentException('Scope and authority disagree.');
            }

            return;
        }
        if ($need['current_spans'] === [] || $need['history_spans'] === []) {
            throw new InvalidArgumentException('Mixed scope requires both partitions.');
        }
        $intervals = [];
        $length = 0;
        foreach ($spans as $span) {
            if (! is_array($span) || count($span) !== 2 || ! is_string($span['quote'] ?? null)
                || trim($span['quote']) === '' || ($span['turn_id'] ?? null) !== $need['question_span']['turn_id']
                || substr_count($need['question_span']['quote'], $span['quote']) !== 1) {
                throw new InvalidArgumentException('Partition must identify one exact clause.');
            }
            $start = mb_strpos($need['question_span']['quote'], $span['quote']);
            $end = $start + mb_strlen($span['quote']);
            foreach ($intervals as [$a, $b]) {
                if ($start < $b && $a < $end) {
                    throw new InvalidArgumentException('Duplicate or overlapping clauses.');
                }
            }
            $intervals[] = [$start, $end];
            $length += mb_strlen($span['quote']);
        }
        if ($length > 8000) {
            throw new InvalidArgumentException('Partition capacity exceeded.');
        }
        foreach ($need['context_spans'] as $context) {
            foreach ($need['history_spans'] as $historical) {
                if (str_contains($context['quote'], $historical['quote'])) {
                    throw new InvalidArgumentException('Historical clause in current context.');
                }
            }
        }
        $currentText = implode(' ', array_column([...$need['context_spans'], ...$need['current_spans']], 'quote'));
        foreach ([$need['applicability']['source'], $need['fact_reference']['source'] ?? null, $need['claim_under_test']['source'] ?? null] as $source) {
            if ($source !== null && ! str_contains($currentText, $source['quote'])) {
                throw new InvalidArgumentException('Current projection loses grounded restriction.');
            }
        }
    }

    public static function dispositions(array $value): array
    {
        return array_map(function (array $need): array {
            $kind = $need['requested_information'];
            $status = ($need['scope_error'] ?? null) !== null ? 'interpretation_failure' : match ($kind) {
                'current_policy' => 'admitted', 'mixed' => 'partial_scope', 'history' => 'scope_declined', 'unclear' => 'ambiguity',
            };

            return ['version' => 'current-policy-disposition-v1', 'need_id' => $need['need_id'], 'status' => $status,
                'reason' => $need['scope_error'] ?? null,
                'declined_spans' => $kind === 'mixed' ? $need['history_spans'] : ($kind === 'history' ? [$need['question_span']] : []),
                'message' => match ($status) {
                    'partial_scope', 'scope_declined' => self::DECLINE,
                    'interpretation_failure' => self::INTERPRETATION_FAILURE,
                    'ambiguity' => self::AMBIGUITY, default => null,
                }];
        }, $value['needs']);
    }

    public static function executionNeeds(array $value): array
    {
        $result = [];
        foreach ($value['needs'] as $need) {
            if (($need['scope_error'] ?? null) !== null || ! in_array($need['requested_information'], ['current_policy', 'mixed'], true)) {
                continue;
            }
            if ($need['requested_information'] === 'mixed') {
                $need['aspect'] = implode(' ', array_column([...$need['context_spans'], ...$need['current_spans']], 'quote'));
                $need['authority'] = ['kind' => 'current', 'value' => null, 'precision' => 'none', 'source' => null];
                $need['operation'] = 'retrieve';
                $need['scope_question'] = null;
            } else {
                $need['aspect'] = EvidenceNeedsPlan::compiledRequest($need);
            }
            $result[] = $need;
        }

        return $result;
    }
}
