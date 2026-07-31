<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Ingestion\PublishIngestionOutbox;
use App\Contracts\Ingestion\IngestionEventPublisher;
use App\Http\Middleware\TraceHttpRequests;
use App\Models\OutboxEvent;
use App\Services\Ingestion\SqsIngestionEventPublisher;
use App\Telemetry\DatabaseTelemetry;
use App\Telemetry\TelemetryAttributeAllowlist;
use App\Telemetry\TelemetryLifecycle;
use App\Telemetry\TelemetrySdkFactory;
use ArrayObject;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\SqsQueue;
use Illuminate\Support\Facades\DB;
use Mockery;
use OpenTelemetry\API\Metrics\MeterProviderInterface as ApiMeterProviderInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface as ApiTracerProviderInterface;
use OpenTelemetry\SDK\Metrics\Data\Histogram;
use OpenTelemetry\SDK\Metrics\Data\Metric;
use OpenTelemetry\SDK\Metrics\Data\Sum;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\MetricExporter\InMemoryExporter as InMemoryMetricExporter;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter as InMemorySpanExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class TelemetryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('OTEL_SDK_DISABLED=true');
        $_ENV['OTEL_SDK_DISABLED'] = 'true';
        $_SERVER['OTEL_SDK_DISABLED'] = 'true';

        parent::tearDown();
    }

    public function test_http_trace_uses_route_template_and_propagates_parent_context(): void
    {
        [$spanExporter, $metricExporter, $tracerProvider, $meterProvider] =
            $this->installInMemoryTelemetry();
        $traceId = '11111111111111111111111111111111';
        $parentSpanId = '2222222222222222';

        $this->withHeader(
            'traceparent',
            "00-{$traceId}-{$parentSpanId}-01",
        )->get('/api/auth/user?question=synthetic-secret')
            ->assertUnauthorized();

        $span = collect($spanExporter->getSpans())
            ->firstWhere(
                fn ($span): bool => $span->getName()
                    === 'GET /api/auth/user',
            );

        $this->assertNotNull(
            $span,
            'Observed spans: '.collect($spanExporter->getSpans())
                ->map(fn ($span): string => $span->getName())
                ->implode(', '),
        );
        $this->assertSame($traceId, $span->getContext()->getTraceId());
        $this->assertSame(
            $parentSpanId,
            $span->getParentContext()->getSpanId(),
        );
        $this->assertSame([
            'http.request.method' => 'GET',
            'http.route' => '/api/auth/user',
            'http.response.status_code' => 401,
        ], $span->getAttributes()->toArray());
        $this->assertStringNotContainsString(
            'synthetic-secret',
            serialize($spanExporter->getSpans()),
        );

        $meterProvider->forceFlush();
        $metrics = $metricExporter->collect();
        $metricNames = array_column($metrics, 'name');

        $this->assertContains(
            'http.server.request.count',
            $metricNames,
            'Observed metrics: '.implode(', ', $metricNames),
        );
        $this->assertContains(
            'http.server.request.duration',
            $metricNames,
            'Observed metrics: '.implode(', ', $metricNames),
        );
        $this->assertMetricAttributesExcludeEntityIdentifiers($metrics);

        $tracerProvider->shutdown();
        $meterProvider->shutdown();
    }

    public function test_database_telemetry_records_operation_without_sql_or_bindings(): void
    {
        [$spanExporter, $metricExporter, $tracerProvider, $meterProvider] =
            $this->installInMemoryTelemetry();

        DB::select('select ? as marker', ['synthetic-db-secret']);

        $databaseSpan = collect($spanExporter->getSpans())
            ->firstWhere(fn ($span): bool => $span->getName() === 'db.SELECT');

        $this->assertNotNull($databaseSpan);
        $this->assertSame([
            'db.system.name' => 'sqlite',
            'db.operation.name' => 'SELECT',
        ], $databaseSpan->getAttributes()->toArray());
        $serialized = serialize($spanExporter->getSpans());
        $this->assertStringNotContainsString(
            'synthetic-db-secret',
            $serialized,
        );
        $this->assertStringNotContainsString(
            'select ? as marker',
            $serialized,
        );

        $meterProvider->forceFlush();
        $metrics = $metricExporter->collect();
        $metricNames = array_column($metrics, 'name');

        $this->assertContains(
            'db.client.operation.duration',
            $metricNames,
            'Observed metrics: '.implode(', ', $metricNames),
        );
        $this->assertMetricAttributesExcludeEntityIdentifiers($metrics);

        $tracerProvider->shutdown();
        $meterProvider->shutdown();
    }

    public function test_outbox_publication_emits_safe_trace_and_low_cardinality_metrics(): void
    {
        [$spanExporter, $metricExporter, $tracerProvider, $meterProvider] =
            $this->installInMemoryTelemetry();
        $event = OutboxEvent::factory()->create();
        $spanExporter->getStorage()->exchangeArray([]);
        $transport = Mockery::mock(IngestionEventPublisher::class);
        $transport->shouldReceive('publish')
            ->once()
            ->andReturn('transport-message-id');
        $this->app->instance(IngestionEventPublisher::class, $transport);

        $summary = app(PublishIngestionOutbox::class)->handle();

        $this->assertSame(1, $summary['published']);
        $span = collect($spanExporter->getSpans())->firstWhere(
            fn ($span): bool => $span->getName()
                === 'messaging.publish document.ingestion.requested',
        );

        $this->assertNotNull($span);
        $attributes = $span->getAttributes()->toArray();
        $this->assertSame($event->event_id, $attributes['rag.event.id']);
        $this->assertSame(
            $event->correlation_id,
            $attributes['rag.correlation.id'],
        );
        $this->assertSame('published', $attributes['rag.outbox.outcome']);
        $this->assertArrayNotHasKey('payload', $attributes);
        $this->assertArrayNotHasKey('storage_key', $attributes);

        $meterProvider->forceFlush();
        $metrics = $metricExporter->collect();
        $metricNames = array_column($metrics, 'name');
        $this->assertContains(
            'rag.ingestion.outbox.publication.count',
            $metricNames,
            'Observed metrics: '.implode(', ', $metricNames),
        );
        $this->assertContains(
            'rag.ingestion.outbox.publication.duration',
            $metricNames,
            'Observed metrics: '.implode(', ', $metricNames),
        );
        $this->assertMetricAttributesExcludeEntityIdentifiers($metrics);

        $tracerProvider->shutdown();
        $meterProvider->shutdown();
    }

    public function test_sqs_publisher_injects_w3c_context_as_message_attributes(): void
    {
        [, , $tracerProvider, $meterProvider] =
            $this->installInMemoryTelemetry();
        $queue = Mockery::mock(SqsQueue::class);
        $queue->shouldReceive('pushRaw')
            ->once()
            ->withArgs(function (
                string $payload,
                mixed $queueName,
                array $options,
            ): bool {
                $this->assertNull($queueName);
                $this->assertJson($payload);
                $traceparent = $options['MessageAttributes']['traceparent']['StringValue'] ?? null;
                $this->assertIsString($traceparent);
                $this->assertMatchesRegularExpression(
                    '/^00-[0-9a-f]{32}-[0-9a-f]{16}-01$/',
                    $traceparent,
                );

                return true;
            })
            ->andReturn('transport-message-id');
        $queues = Mockery::mock(QueueFactory::class);
        $queues->shouldReceive('connection')
            ->once()
            ->with('sqs')
            ->andReturn($queue);
        $span = $tracerProvider
            ->getTracer('telemetry-test')
            ->spanBuilder('active-parent')
            ->startSpan();
        $scope = $span->activate();

        try {
            $messageId = (new SqsIngestionEventPublisher($queues))
                ->publish(['event_id' => 'test-event']);
        } finally {
            $scope->detach();
            $span->end();
        }

        $this->assertSame('transport-message-id', $messageId);

        $tracerProvider->shutdown();
        $meterProvider->shutdown();
    }

    public function test_attribute_allowlist_rejects_content_secrets_and_metric_entity_ids(): void
    {
        $allowlist = app(TelemetryAttributeAllowlist::class);
        $attributes = [
            'http.route' => '/api/documents/{document}',
            'rag.document.id' => 'document-id',
            'document.content' => 'private content',
            'password' => 'secret',
            'prompt' => 'private prompt',
        ];

        $this->assertSame([
            'http.route' => '/api/documents/{document}',
            'rag.document.id' => 'document-id',
        ], $allowlist->trace($attributes));
        $this->assertSame([
            'http.route' => '/api/documents/{document}',
        ], $allowlist->metric($attributes));
    }

    public function test_resource_attributes_use_an_explicit_privacy_allowlist(): void
    {
        config(['telemetry.service_name' => 'rag-platform-api']);
        $resourceMethod = new ReflectionMethod(
            TelemetrySdkFactory::class,
            'resource',
        );
        $resource = $resourceMethod->invoke(
            app(TelemetrySdkFactory::class),
        );

        $this->assertSame([
            'service.name' => 'rag-platform-api',
            'deployment.environment.name' => 'testing',
        ], $resource->getAttributes()->toArray());
    }

    public function test_telemetry_lifecycle_swallows_export_failures(): void
    {
        $tracerProvider = Mockery::mock(TracerProviderInterface::class);
        $tracerProvider->shouldReceive('forceFlush')
            ->once()
            ->andThrow(new RuntimeException('collector unavailable'));
        $tracerProvider->shouldReceive('shutdown')
            ->once()
            ->andThrow(new RuntimeException('collector unavailable'));
        $meterProvider = Mockery::mock(MeterProviderInterface::class);
        $meterProvider->shouldReceive('forceFlush')
            ->once()
            ->andThrow(new RuntimeException('collector unavailable'));
        $meterProvider->shouldReceive('shutdown')
            ->once()
            ->andThrow(new RuntimeException('collector unavailable'));
        $lifecycle = new TelemetryLifecycle(
            $tracerProvider,
            $meterProvider,
        );

        $lifecycle->flush();
        $lifecycle->shutdown();

        $this->addToAssertionCount(1);
    }

    /**
     * @return array{
     *     InMemorySpanExporter,
     *     InMemoryMetricExporter,
     *     TracerProvider,
     *     MeterProvider
     * }
     */
    private function installInMemoryTelemetry(): array
    {
        putenv('OTEL_SDK_DISABLED=false');
        $_ENV['OTEL_SDK_DISABLED'] = 'false';
        $_SERVER['OTEL_SDK_DISABLED'] = 'false';

        $spanExporter = new InMemorySpanExporter(new ArrayObject);
        $tracerProvider = TracerProvider::builder()
            ->addSpanProcessor(new SimpleSpanProcessor($spanExporter))
            ->build();
        $metricExporter = new InMemoryMetricExporter(new ArrayObject);
        $meterProvider = MeterProvider::builder()
            ->addReader(new ExportingReader($metricExporter))
            ->build();

        $this->app->instance(
            TracerProviderInterface::class,
            $tracerProvider,
        );
        $this->app->instance(
            ApiTracerProviderInterface::class,
            $tracerProvider,
        );
        $this->app->instance(
            MeterProviderInterface::class,
            $meterProvider,
        );
        $this->app->instance(
            ApiMeterProviderInterface::class,
            $meterProvider,
        );
        $this->app->forgetInstance(DatabaseTelemetry::class);
        $this->app->forgetInstance(TraceHttpRequests::class);

        return [
            $spanExporter,
            $metricExporter,
            $tracerProvider,
            $meterProvider,
        ];
    }

    /**
     * @param  array<int, Metric>  $metrics
     */
    private function assertMetricAttributesExcludeEntityIdentifiers(
        array $metrics,
    ): void {
        foreach ($metrics as $metric) {
            $dataPoints = match (true) {
                $metric->data instanceof Histogram,
                $metric->data instanceof Sum => $metric->data->dataPoints,
                default => [],
            };

            foreach ($dataPoints as $dataPoint) {
                $attributes = $dataPoint->attributes->toArray();

                $this->assertArrayNotHasKey('rag.correlation.id', $attributes);
                $this->assertArrayNotHasKey('rag.document.id', $attributes);
                $this->assertArrayNotHasKey('rag.event.id', $attributes);
                $this->assertArrayNotHasKey('rag.workspace.id', $attributes);
            }
        }
    }
}
