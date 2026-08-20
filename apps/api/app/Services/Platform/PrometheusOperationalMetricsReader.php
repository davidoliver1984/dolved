<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Contracts\Platform\OperationalMetricsReader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

final class PrometheusOperationalMetricsReader implements OperationalMetricsReader
{
    private const ALLOWED_RESPONSE_LABELS = [
        'messaging_destination_name',
        'rag_dependency_kind',
        'rag_operation_outcome',
        'rag_operation_stage',
    ];

    private const MAX_RESULTS_PER_QUERY = 50;

    private const QUERIES = [
        'api_request_rate' => 'sum(rate(http_server_request_count_total[5m]))',
        'api_error_rate' => 'sum(rate(http_server_request_count_total{http_response_status_code=~"5.."}[5m]))',
        'api_latency_p95_seconds' => 'histogram_quantile(0.95, sum by (le) (rate(http_server_request_duration_seconds_bucket[5m])))',
        'operation_rate' => 'sum by (rag_operation_stage,rag_operation_outcome) (rate(rag_operation_count_total[5m]))',
        'operation_latency_p95_seconds' => 'histogram_quantile(0.95, sum by (le,rag_operation_stage) (rate(rag_operation_duration_seconds_bucket[5m])))',
        'dependency_availability' => 'min by (rag_dependency_kind) (rag_dependency_available)',
        'queue_depth' => 'max by (messaging_destination_name) (rag_queue_depth)',
        'queue_oldest_message_age_seconds' => 'max by (messaging_destination_name) (rag_queue_oldest_message_age_seconds)',
        'stuck_operations' => 'sum by (rag_operation_stage) (rag_stuck_operation_count)',
    ];

    public function snapshot(): array
    {
        return Cache::remember('platform:operational-metrics:v1', (int) config('platform.metrics.cache_seconds'), function (): array {
            $metrics = [];
            foreach (self::QUERIES as $name => $query) {
                $metrics[$name] = $this->query($query);
            }
            $available = count(array_filter($metrics, fn (array $metric): bool => $metric['status'] === 'available'));

            $status = $available === count($metrics) ? 'available' : ($available === 0 ? 'unavailable' : 'partial');

            return [
                'status' => $status,
                'health_status' => $this->healthStatus($metrics, $status),
                'as_of' => now()->toIso8601String(),
                'freshness' => $available === 0 ? 'unavailable' : 'current',
                'metrics' => $metrics,
                'grafana_url' => (string) config('platform.metrics.grafana_url'),
            ];
        });
    }

    /** @param array<string, array{status: string, values: array<int, array{labels: array<string, string>, value: float|null}>}> $metrics */
    private function healthStatus(array $metrics, string $dataStatus): string
    {
        $dependencies = $metrics['dependency_availability']['values'] ?? [];
        $stuck = $metrics['stuck_operations']['values'] ?? [];
        if (array_any($dependencies, fn (array $value): bool => $value['value'] !== null && $value['value'] < 1)
            || array_any($stuck, fn (array $value): bool => $value['value'] !== null && $value['value'] > 0)) {
            return 'degraded';
        }
        if ($dataStatus !== 'available' || $dependencies === [] || $stuck === []
            || array_any([...$dependencies, ...$stuck], fn (array $value): bool => $value['value'] === null)) {
            return 'unknown';
        }

        return 'healthy';
    }

    /** @return array{status: string, values: array<int, array{labels: array<string, string>, value: float|null}>} */
    private function query(string $query): array
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('platform.metrics.base_url'), '/'))
                ->acceptJson()
                ->timeout((float) config('platform.metrics.timeout_seconds'))
                ->get('/api/v1/query', ['query' => $query]);
            if (! $response->successful() || $response->json('status') !== 'success') {
                return ['status' => 'unavailable', 'values' => []];
            }
            $results = $response->json('data.result');
            if (! is_array($results)) {
                return ['status' => 'unavailable', 'values' => []];
            }

            return [
                'status' => 'available',
                'values' => array_values(array_map(function (mixed $result): array {
                    $labels = is_array($result) && is_array($result['metric'] ?? null)
                        ? array_map(
                            'strval',
                            array_intersect_key(
                                $result['metric'],
                                array_flip(self::ALLOWED_RESPONSE_LABELS),
                            ),
                        )
                        : [];
                    $raw = is_array($result) && is_array($result['value'] ?? null) ? ($result['value'][1] ?? null) : null;

                    return ['labels' => $labels, 'value' => is_numeric($raw) ? (float) $raw : null];
                }, array_slice($results, 0, self::MAX_RESULTS_PER_QUERY))),
            ];
        } catch (Throwable) {
            return ['status' => 'unavailable', 'values' => []];
        }
    }
}
