<?php

declare(strict_types=1);

namespace App\Support\Usage;

use App\Models\WorkspaceActivityEvent;
use App\Models\WorkspaceUsageEvent;
use InvalidArgumentException;

final class RecordWorkspaceUsage
{
    public function activity(int $workspaceId, string $kind, string $sourcePublicId, ?string $outcome = null): void
    {
        WorkspaceActivityEvent::query()->firstOrCreate(
            [
                'workspace_id' => $workspaceId,
                'event_kind' => $kind,
                'source_public_id' => $sourcePublicId,
            ],
            ['outcome' => $outcome, 'occurred_at' => now()],
        );
    }

    /** @param list<array<string, mixed>> $entries */
    public function usage(int $workspaceId, string $scopeType, string $scopePublicId, array $entries): void
    {
        foreach (array_values($entries) as $ordinal => $entry) {
            $this->validate($entry);
            WorkspaceUsageEvent::query()->firstOrCreate(
                [
                    'workspace_id' => $workspaceId,
                    'scope_type' => $scopeType,
                    'scope_public_id' => $scopePublicId,
                    'operation_kind' => strtolower((string) ($entry['stage'] ?? 'unknown')),
                    'ordinal' => $ordinal,
                ],
                [
                    'provider' => $entry['provider'],
                    'model' => $entry['model'],
                    'execution' => strtolower((string) $entry['execution']),
                    'request_count' => (int) ($entry['request_count'] ?? $entry['provider_attempt_count'] ?? 0),
                    'retry_count' => (int) ($entry['retry_count'] ?? $entry['provider_retry_count'] ?? 0),
                    'input_tokens' => $entry['input_tokens'] ?? $entry['provider_input_tokens'] ?? null,
                    'cached_input_tokens' => $entry['cached_input_tokens'] ?? null,
                    'output_tokens' => $entry['output_tokens'] ?? null,
                    'latency_ms' => $entry['latency_ms'] ?? null,
                    'cost_usd' => $entry['cost_usd'] ?? $entry['estimated_cost_usd'] ?? null,
                    'cost_basis' => strtolower((string) $entry['cost_basis']),
                    'pricing_snapshot' => $entry['pricing_snapshot'] ?? null,
                    'occurred_at' => now(),
                ],
            );
        }
    }

    /** @param array<string, mixed> $entry */
    private function validate(array $entry): void
    {
        $basis = strtolower((string) ($entry['cost_basis'] ?? ''));
        $cost = $entry['cost_usd'] ?? $entry['estimated_cost_usd'] ?? null;
        $pricing = $entry['pricing_snapshot'] ?? null;
        $execution = strtolower((string) ($entry['execution'] ?? ''));
        if (! is_string($entry['provider'] ?? null) || trim($entry['provider']) === ''
            || ! is_string($entry['model'] ?? null) || trim($entry['model']) === ''
            || ! is_string($entry['stage'] ?? null) || trim($entry['stage']) === ''
            || ! in_array($execution, ['provider_api', 'local', 'infrastructure'], true)
            || ! $this->validCount($entry['request_count'] ?? $entry['provider_attempt_count'] ?? 0)
            || ! $this->validCount($entry['retry_count'] ?? $entry['provider_retry_count'] ?? 0)
            || ! $this->validNullableCount($entry['input_tokens'] ?? $entry['provider_input_tokens'] ?? null)
            || ! $this->validNullableCount($entry['cached_input_tokens'] ?? null)
            || ! $this->validNullableCount($entry['output_tokens'] ?? null)
            || (($entry['latency_ms'] ?? null) !== null && (! is_numeric($entry['latency_ms']) || $entry['latency_ms'] < 0))
            || ($cost !== null && (! is_numeric($cost) || $cost < 0))
            || ! in_array($basis, ['provider_reported', 'estimated', 'unavailable', 'zero_cost_local'], true)
            || ($basis === 'unavailable' && $cost !== null)
            || ($basis === 'estimated' && ($cost === null || ! is_string($pricing) || trim($pricing) === ''))
            || ($basis === 'provider_reported' && $cost === null)
            || ($basis === 'zero_cost_local' && ((float) $cost !== 0.0 || ! in_array($execution, ['local', 'infrastructure'], true)))) {
            throw new InvalidArgumentException('The workspace usage entry is invalid.');
        }
    }

    private function validCount(mixed $value): bool
    {
        return is_int($value) && $value >= 0;
    }

    private function validNullableCount(mixed $value): bool
    {
        return $value === null || $this->validCount($value);
    }
}
