<?php

declare(strict_types=1);

namespace App\Support\Generation;

final class ValidationAttemptUsage
{
    public static function receipt(?array $usage): array
    {
        $out = [];
        foreach (['request_count', 'retry_count', 'input_tokens', 'output_tokens', 'cached_input_tokens', 'latency_ms', 'cost_usd'] as $key) {
            $value = $usage[$key] ?? null;
            $out[$key] = (is_int($value) || is_float($value)) && is_finite((float) $value) && $value >= 0 ? $value : null;
        }
        $out['request_count'] ??= 1;
        $out['retry_count'] ??= 0;
        $snapshot = $usage['pricing_snapshot'] ?? null;
        $out['pricing_snapshot'] = is_string($snapshot) && trim($snapshot) !== '' ? $snapshot : null;
        $out['cost_basis'] = $out['cost_usd'] === null ? 'unavailable' : 'estimated';

        return $out;
    }

    public static function combine(array $receipts): array
    {
        $out = [];
        foreach (['request_count', 'input_tokens', 'output_tokens', 'cached_input_tokens', 'latency_ms', 'cost_usd'] as $key) {
            $values = array_column($receipts, $key);
            $out[$key] = count($values) === count($receipts) && ! in_array(null, $values, true) ? array_sum($values) : null;
        }
        $out['retry_count'] = max(0, ($out['request_count'] ?? 1) - 1);
        $out['validation_retry_count'] = count($receipts) - 1;
        $snapshots = array_column($receipts, 'pricing_snapshot');
        $out['pricing_snapshot'] = count($snapshots) === count($receipts)
            && count(array_unique($snapshots)) === 1
            && is_string($snapshots[0]) && trim($snapshots[0]) !== ''
                ? $snapshots[0] : null;
        if ($out['cost_usd'] !== null && $out['pricing_snapshot'] === null) {
            // Without a common pricing basis, a summed estimate is not auditable.
            $out['cost_usd'] = null;
        }
        $out['cost_basis'] = $out['cost_usd'] === null ? 'unavailable' : 'estimated';
        $out['validation_attempts'] = $receipts;

        return $out;
    }
}
