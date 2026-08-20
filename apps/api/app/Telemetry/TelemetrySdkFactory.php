<?php

declare(strict_types=1);

namespace App\Telemetry;

use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Metrics\Data\Temporality;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Metrics\NoopMeterProvider;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\NoopTracerProvider;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessorBuilder;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use Throwable;

final class TelemetrySdkFactory
{
    private const CONTENT_TYPE_PROTOBUF = 'application/x-protobuf';

    public function tracerProvider(): TracerProviderInterface
    {
        if (! config('telemetry.enabled')) {
            return new NoopTracerProvider;
        }

        try {
            $transport = (new OtlpHttpTransportFactory)->create(
                $this->signalEndpoint('v1/traces'),
                self::CONTENT_TYPE_PROTOBUF,
                compression: 'gzip',
                timeout: $this->timeoutSeconds(),
                maxRetries: 0,
            );
            $processor = (new BatchSpanProcessorBuilder(
                new SpanExporter($transport),
            ))->build();

            return TracerProvider::builder()
                ->setResource($this->resource())
                ->addSpanProcessor($processor)
                ->build();
        } catch (Throwable $exception) {
            $this->reportSetupFailure($exception);

            return new NoopTracerProvider;
        }
    }

    public function meterProvider(): MeterProviderInterface
    {
        if (! config('telemetry.enabled')) {
            return new NoopMeterProvider;
        }

        try {
            $transport = (new OtlpHttpTransportFactory)->create(
                $this->signalEndpoint('v1/metrics'),
                self::CONTENT_TYPE_PROTOBUF,
                compression: 'gzip',
                timeout: $this->timeoutSeconds(),
                maxRetries: 0,
            );

            return MeterProvider::builder()
                ->setResource($this->resource())
                ->addReader(new ExportingReader(
                    new MetricExporter(
                        $transport,
                        $this->metricsTemporality(),
                    ),
                ))
                ->build();
        } catch (Throwable $exception) {
            $this->reportSetupFailure($exception);

            return new NoopMeterProvider;
        }
    }

    private function resource(): ResourceInfo
    {
        return ResourceInfo::create(Attributes::create([
            'service.name' => (string) config(
                'telemetry.service_name',
                'rag-platform-api',
            ),
            'deployment.environment.name' => app()->environment(),
        ]));
    }

    private function signalEndpoint(string $path): string
    {
        $protocol = (string) config(
            'telemetry.exporter.protocol',
            'http/protobuf',
        );

        if ($protocol !== 'http/protobuf') {
            throw new \UnexpectedValueException(
                'Laravel telemetry requires OTLP over HTTP/protobuf.',
            );
        }

        return rtrim(
            (string) config('telemetry.exporter.endpoint'),
            '/',
        ).'/'.$path;
    }

    private function timeoutSeconds(): float
    {
        return max(
            1,
            (int) config(
                'telemetry.exporter.timeout_milliseconds',
                250,
            ),
        ) / 1_000;
    }

    private function metricsTemporality(): string
    {
        return match (strtolower((string) config(
            'telemetry.exporter.metrics_temporality',
            'cumulative',
        ))) {
            'cumulative' => Temporality::CUMULATIVE,
            'delta' => Temporality::DELTA,
            default => throw new \UnexpectedValueException(
                'Laravel telemetry metrics temporality must be cumulative or delta.',
            ),
        };
    }

    private function reportSetupFailure(Throwable $exception): void
    {
        logger()->warning('OpenTelemetry SDK setup failed; using no-op telemetry.', [
            'event_name' => 'telemetry.setup_failed.v1',
            'error_type' => $exception::class,
        ]);
    }
}
