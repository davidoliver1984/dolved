<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Contracts\Platform\OperationalMetricsReader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

final class PrometheusOperationalMetricsReader implements OperationalMetricsReader
{
    private const CACHE_KEY = 'platform:operational-metrics:v2';

    private const ALLOWED_RESPONSE_LABELS = [
        'messaging_destination_name',
        'rag_dependency_kind',
        'rag_operation_outcome',
        'rag_operation_stage',
    ];

    private const MAX_RESULTS_PER_QUERY = 50;

    private const MAX_ACTIVE_ALERTS = 50;

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

    private const SLOS = [
        'authenticated_api_technical_availability' => [
            'objective' => 0.99,
            'window_days' => 28,
            'query' => '(1 - (sum(increase(http_server_request_count_total{http_route=~"/api/(auth/(user|logout|email/verification-notification)|platform/.*|workspaces.*|workspace-invitations/accept)",http_response_status_code=~"5.."}[28d])) / clamp_min(sum(increase(http_server_request_count_total{http_route=~"/api/(auth/(user|logout|email/verification-notification)|platform/.*|workspaces.*|workspace-invitations/accept)",http_response_status_code!~"4.."}[28d])), 1))) and on() (sum(increase(http_server_request_count_total{http_route=~"/api/(auth/(user|logout|email/verification-notification)|platform/.*|workspaces.*|workspace-invitations/accept)",http_response_status_code!~"4.."}[28d])) > 0)',
        ],
        'conversation_technical_success' => [
            'objective' => 0.99,
            'window_days' => 28,
            'query' => '(1 - (sum(increase(rag_operation_count_total{rag_operation_stage="generation_run",rag_operation_outcome="failed"}[28d])) / clamp_min(sum(increase(rag_operation_count_total{rag_operation_stage="generation_run",rag_operation_outcome=~"completed|retrieval_no_answer|clarification_required|failed"}[28d])), 1))) and on() (sum(increase(rag_operation_count_total{rag_operation_stage="generation_run",rag_operation_outcome=~"completed|retrieval_no_answer|clarification_required|failed"}[28d])) > 0)',
        ],
    ];

    public function snapshot(): array
    {
        return Cache::remember(self::CACHE_KEY, (int) config('platform.metrics.cache_seconds'), function (): array {
            $metrics = [];
            foreach (self::QUERIES as $name => $query) {
                $metrics[$name] = $this->query($query);
            }
            $available = count(array_filter($metrics, fn (array $metric): bool => $metric['status'] === 'available'));

            $status = $available === count($metrics) ? 'available' : ($available === 0 ? 'unavailable' : 'partial');

            $slos = [];
            foreach (self::SLOS as $id => $definition) {
                $observation = $this->query($definition['query']);
                $value = $observation['values'][0]['value'] ?? null;
                $slos[] = [
                    'id' => $id,
                    'objective' => $definition['objective'],
                    'window_days' => $definition['window_days'],
                    'status' => $observation['status'] !== 'available'
                        ? 'unavailable'
                        : ($value === null ? 'no_data' : 'available'),
                    'value' => $value,
                    'compliant' => $value === null ? null : $value >= $definition['objective'],
                ];
            }
            $alerts = $this->activeAlerts();

            return [
                'status' => $status,
                'health_status' => $this->healthStatus($metrics, $status, $alerts),
                'as_of' => now()->toIso8601String(),
                'freshness' => $available === 0 ? 'unavailable' : 'current',
                'metrics' => $metrics,
                'slos' => $slos,
                'alerts' => $alerts,
                'grafana_url' => (string) config('platform.metrics.grafana_url'),
                'alertmanager_url' => (string) config('platform.metrics.alertmanager_public_url'),
            ];
        });
    }

    /** @return array{status: string, values: array<int, array<string, string>>} */
    private function activeAlerts(): array
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('platform.metrics.alertmanager_url'), '/'))
                ->acceptJson()
                ->timeout((float) config('platform.metrics.timeout_seconds'))
                ->get('/api/v2/alerts', ['active' => 'true', 'silenced' => 'false', 'inhibited' => 'false']);
            if (! $response->successful() || ! is_array($response->json())) {
                return ['status' => 'unavailable', 'values' => []];
            }

            $alerts = array_map(function (mixed $alert): array {
                $labels = is_array($alert) && is_array($alert['labels'] ?? null) ? $alert['labels'] : [];
                $annotations = is_array($alert) && is_array($alert['annotations'] ?? null) ? $alert['annotations'] : [];
                $status = is_array($alert) && is_array($alert['status'] ?? null) ? $alert['status'] : [];

                return [
                    'name' => $this->boundedIdentifier($labels['alertname'] ?? null, 'unknown'),
                    'severity' => in_array($labels['severity'] ?? null, ['warning', 'urgent'], true) ? $labels['severity'] : 'warning',
                    'subsystem' => $this->boundedIdentifier($labels['subsystem'] ?? null, 'unknown'),
                    'state' => $this->boundedIdentifier($status['state'] ?? null, 'active'),
                    'started_at' => $this->timestamp($alert['startsAt'] ?? null),
                    'impact' => $this->boundedText($annotations['impact'] ?? null, 500),
                    'runbook_url' => $this->runbookUrl($annotations['runbook_url'] ?? null),
                ];
            }, array_slice($response->json(), 0, self::MAX_ACTIVE_ALERTS));

            return ['status' => 'available', 'values' => array_values($alerts)];
        } catch (Throwable) {
            return ['status' => 'unavailable', 'values' => []];
        }
    }

    private function boundedIdentifier(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/\A[a-zA-Z0-9_.-]{1,100}\z/', $value) === 1 ? $value : $fallback;
    }

    private function boundedText(mixed $value, int $maximum): string
    {
        return is_string($value) ? mb_substr(trim($value), 0, $maximum) : '';
    }

    private function timestamp(mixed $value): string
    {
        return is_string($value) && preg_match('/\A\d{4}-\d{2}-\d{2}T/', $value) === 1
            ? mb_substr($value, 0, 40)
            : '';
    }

    private function runbookUrl(mixed $value): string
    {
        if (! is_string($value) || ! str_starts_with($value, 'https://github.com/davidoliver1984/dolved/')) {
            return '';
        }

        return mb_substr($value, 0, 500);
    }

    /**
     * @param  array<string, array{status: string, values: array<int, array{labels: array<string, string>, value: float|null}>}>  $metrics
     * @param  array{status: string, values: array<int, array<string, string>>}  $alerts
     */
    private function healthStatus(array $metrics, string $dataStatus, array $alerts): string
    {
        $dependencies = $metrics['dependency_availability']['values'] ?? [];
        $stuck = $metrics['stuck_operations']['values'] ?? [];
        if (array_any($dependencies, fn (array $value): bool => $value['value'] !== null && $value['value'] < 1)
            || array_any($stuck, fn (array $value): bool => $value['value'] !== null && $value['value'] > 0)
            || array_any($alerts['values'], fn (array $alert): bool => ($alert['severity'] ?? null) === 'urgent')) {
            return 'degraded';
        }
        if ($dataStatus !== 'available' || $alerts['status'] !== 'available' || $dependencies === [] || $stuck === []
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
