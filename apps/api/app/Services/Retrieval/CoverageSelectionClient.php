<?php

declare(strict_types=1);

namespace App\Services\Retrieval;

use App\Exceptions\CoverageSelectionFailure;
use App\Exceptions\RetrievalException;
use App\Models\Workspace;
use App\Telemetry\TraceContextHeaders;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** One optional attempt, never the ordinary retrieval client's retry loop. */
class CoverageSelectionClient
{
    public function __construct(private readonly RetrievalCallSigner $signer, private readonly TraceContextHeaders $trace) {}

    public function select(Workspace $workspace, array $needs, array $candidates, ?CarbonImmutable $deadline, array $turns = []): array
    {
        $remaining = $deadline === null ? 60.0 : CarbonImmutable::now()->diffInSeconds($deadline, false);
        if ($remaining <= 0) {
            throw new CoverageSelectionFailure('coverage_deadline_exhausted', true);
        }
        $timeout = min(60.0, $remaining);
        $id = (string) Str::uuid();
        $path = '/api/internal/retrieval/coverage-selection';
        $payload = ['contract_version' => 1, 'request_id' => $id, 'workspace_id' => $workspace->public_id,
            'needs' => $needs, 'candidates' => $candidates, 'timeout_seconds' => $timeout, 'turns' => $turns];
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $base = rtrim((string) config('retrieval.ai_url'), '/');
        if (app()->environment('production') && ! str_starts_with($base, 'https://')) {
            throw new RetrievalException('Coverage selection requires authenticated TLS.');
        }
        $started = hrtime(true);
        try {
            $response = Http::timeout($timeout)->withHeaders($this->signer->headers('POST', $path, $body,
                (string) $workspace->public_id, 'retrieval.coverage-selection', $id) + $this->trace->current())
                ->withBody($body, 'application/json')->post($base.$path);
        } catch (ConnectionException) {
            if ($deadline !== null && CarbonImmutable::now()->greaterThanOrEqualTo($deadline)) {
                throw new CoverageSelectionFailure('coverage_deadline_exhausted', true, $this->attemptUsage($started));
            }

            return ['status' => 'unavailable', 'reason' => 'service_transport', 'proposal' => null,
                'usage' => $this->attemptUsage($started)];
        }
        if ($deadline !== null && CarbonImmutable::now()->greaterThanOrEqualTo($deadline)) {
            throw new CoverageSelectionFailure('coverage_deadline_exhausted', true,
                $response->json('usage') !== null ? $this->usage($response->json('usage'))
                    : ($response->json('detail.usage') !== null ? $this->usage($response->json('detail.usage')) : $this->attemptUsage($started)));
        }
        if (! $response->successful()) {
            $code = $response->json('detail.code');
            if ($response->status() === 503 && in_array($code, ['service_timeout', 'service_unavailable', 'input_capacity'], true)) {
                return ['status' => 'unavailable', 'reason' => $code, 'proposal' => null, 'usage' => $this->usage($response->json('detail.usage'))];
            }
            // Invalid output, authentication, budgets and unknown errors are NOT fallback permissions.
            if ($response->status() === 503 && in_array($code, ['invalid_output', 'incomplete_output'], true)) {
                throw new CoverageSelectionFailure($code, false, $this->usage($response->json('detail.usage')));
            }
            throw new CoverageSelectionFailure('coverage_selection_failed_closed', true,
                $response->json('detail.usage') === null ? [] : $this->usage($response->json('detail.usage')));
        }
        if ($response->json('request_id') !== $id || $response->json('contract_version') !== 1
            || $response->json('version') !== 'grounded-coverage-selection-v1' || ! is_array($response->json('usage'))) {
            throw new CoverageSelectionFailure('coverage_selection_identity_invalid', true);
        }

        return ['status' => 'proposed', 'reason' => null, 'proposal' => $response->json('proposal'), 'usage' => $this->usage($response->json('usage'))];
    }

    private function usage(mixed $raw): array
    {
        if (! is_array($raw) || ($raw['stage'] ?? null) !== 'coverage_selection'
            || ($raw['provider'] ?? null) !== 'openai' || ($raw['model'] ?? null) !== config('retrieval.planner.model')
            || ! in_array($raw['request_count'] ?? null, [0, 1], true) || ($raw['retry_count'] ?? null) !== 0
            || ! is_numeric($raw['latency_ms'] ?? null) || $raw['latency_ms'] < 0) {
            throw new CoverageSelectionFailure('coverage_usage_integrity', true);
        }
        foreach (['input_tokens' => 30000, 'output_tokens' => 4096] as $field => $limit) {
            if (($raw[$field] ?? null) !== null && (! is_int($raw[$field]) || $raw[$field] < 0 || $raw[$field] > $limit)) {
                throw new CoverageSelectionFailure('coverage_usage_budget', true);
            }
        }

        return array_intersect_key($raw, array_flip(['stage', 'provider', 'model', 'request_count', 'retry_count',
            'input_tokens', 'output_tokens', 'latency_ms'])) + ['execution' => 'provider_api', 'cost_basis' => 'unavailable', 'cost_usd' => null];
    }

    private function attemptUsage(int $started): array
    {
        return ['stage' => 'coverage_selection', 'request_count' => 1, 'retry_count' => 0,
            'provider' => 'ai-service', 'model' => 'coverage-selection-v1', 'execution' => 'infrastructure',
            'input_tokens' => null, 'output_tokens' => null, 'cost_basis' => 'unavailable', 'cost_usd' => null,
            'latency_ms' => (hrtime(true) - $started) / 1_000_000];
    }
}
