<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\CoverageSelectionFailure;
use App\Models\Workspace;
use App\Services\Retrieval\CoverageSelectionClient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CoverageSelectionClientTest extends TestCase
{
    use RefreshDatabase;

    public static function failures(): array
    {
        return [['service_unavailable', 503, false], ['service_timeout', 503, false],
            ['invalid_output', 503, false], ['incomplete_output', 503, false],
            ['provider_rejected', 503, true], ['unauthorised', 401, true], ['budget_exhausted', 503, true]];
    }

    #[DataProvider('failures')]
    public function test_one_attempt_failure_categories_are_not_blanket_fallbacks(string $code, int $status, bool $stop): void
    {
        $workspace = Workspace::factory()->create();
        Http::preventStrayRequests();
        Http::fake(['*coverage-selection' => Http::response(['detail' => ['code' => $code, 'usage' => [
            'stage' => 'coverage_selection', 'provider' => 'openai', 'model' => config('retrieval.planner.model'),
            'request_count' => 1, 'retry_count' => 0, 'input_tokens' => 100, 'output_tokens' => 12, 'latency_ms' => 10,
        ]]], $status)]);
        try {
            $actual = app(CoverageSelectionClient::class)->select($workspace, [], [], now()->toImmutable()->addMinute());
            $this->assertContains($code, ['service_unavailable', 'service_timeout']);
            $this->assertSame('unavailable', $actual['status']);
            $this->assertNull($actual['proposal']);
            $this->assertSame(1, $actual['usage']['request_count']);
        } catch (CoverageSelectionFailure $failure) {
            $this->assertNotContains($code, ['service_unavailable', 'service_timeout']);
            $this->assertSame($stop, $failure->runStopRequired);
        }
        Http::assertSentCount(1);
    }

    public function test_exhausted_deadline_never_starts_optional_work_or_fallback(): void
    {
        Http::preventStrayRequests();
        $workspace = Workspace::factory()->create();
        try {
            app(CoverageSelectionClient::class)->select($workspace, [], [], CarbonImmutable::now()->subSecond());
            $this->fail('Expired work must not be started.');
        } catch (CoverageSelectionFailure $failure) {
            $this->assertTrue($failure->runStopRequired);
            $this->assertSame('coverage_deadline_exhausted', $failure->reason);
        }
        Http::assertNothingSent();
    }

    public function test_deadline_expiring_during_transport_keeps_attempt_accounting_and_stops(): void
    {
        $workspace = Workspace::factory()->create();
        $start = CarbonImmutable::parse('2026-11-15T12:00:00Z');
        CarbonImmutable::setTestNow($start);
        Http::fake(function () use ($start) {
            CarbonImmutable::setTestNow($start->addMinutes(2));
            throw new ConnectionException('controlled transport failure');
        });
        try {
            app(CoverageSelectionClient::class)->select($workspace, [], [], $start->addMinute());
            $this->fail('Expired call must not fall back.');
        } catch (CoverageSelectionFailure $failure) {
            $this->assertTrue($failure->runStopRequired);
            $this->assertSame('coverage_deadline_exhausted', $failure->reason);
            $this->assertSame(1, $failure->usage['request_count']);
            $this->assertSame(0, $failure->usage['retry_count']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
