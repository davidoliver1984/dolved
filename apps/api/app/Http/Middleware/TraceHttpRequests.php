<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Telemetry\TelemetryAttributeAllowlist;
use App\Telemetry\TelemetryLifecycle;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class TraceHttpRequests
{
    private readonly TracerInterface $tracer;

    private readonly CounterInterface $requestCount;

    private readonly HistogramInterface $requestDuration;

    public function __construct(
        TracerProviderInterface $tracerProvider,
        MeterProviderInterface $meterProvider,
        private readonly TelemetryAttributeAllowlist $allowlist,
        private readonly TelemetryLifecycle $lifecycle,
    ) {
        $this->tracer = $tracerProvider->getTracer('dolved.laravel.http');
        $meter = $meterProvider->getMeter('dolved.laravel.http');
        $this->requestCount = $meter->createCounter(
            'http.server.request.count',
            '{request}',
            'Count of Laravel HTTP requests.',
        );
        $this->requestDuration = $meter->createHistogram(
            'http.server.request.duration',
            's',
            'Duration of Laravel HTTP requests.',
        );
    }

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $method = strtoupper($request->getMethod());
        $routeTemplate = 'unmatched';
        $parent = TraceContextPropagator::getInstance()->extract(
            $request->headers->all(),
        );
        $span = $this->tracer
            ->spanBuilder($method.' '.$routeTemplate)
            ->setParent($parent)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttributes($this->allowlist->trace([
                'http.request.method' => $method,
                'http.route' => $routeTemplate,
            ]))
            ->startSpan();
        $scope = $span->activate();
        $statusCode = 500;

        try {
            /** @var Response $response */
            $response = $next($request);
            $statusCode = $response->getStatusCode();
            $routeTemplate = $this->routeTemplate($request);
            $span->updateName($method.' '.$routeTemplate);
            $span->setAttribute(
                'http.response.status_code',
                $statusCode,
            );
            $span->setAttribute('http.route', $routeTemplate);

            if ($statusCode >= 500) {
                $span->setStatus(StatusCode::STATUS_ERROR);
            }

            return $response;
        } catch (Throwable $exception) {
            $routeTemplate = $this->routeTemplate($request);
            $span->updateName($method.' '.$routeTemplate);
            $span->setAttributes($this->allowlist->trace([
                'http.response.status_code' => 500,
                'http.route' => $routeTemplate,
                'error.type' => $exception::class,
            ]));
            $span->setStatus(StatusCode::STATUS_ERROR);

            throw $exception;
        } finally {
            $metricAttributes = $this->allowlist->metric([
                'http.request.method' => $method,
                'http.route' => $routeTemplate,
                'http.response.status_code' => $statusCode,
            ]);
            $this->requestCount->add(1, $metricAttributes);
            $this->requestDuration->record(
                (hrtime(true) - $startedAt) / 1_000_000_000,
                $metricAttributes,
            );
            $scope->detach();
            $span->end();
        }
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->lifecycle->flush();
    }

    private function routeTemplate(Request $request): string
    {
        $route = $request->route();

        return $route instanceof Route
            ? '/'.$route->uri()
            : 'unmatched';
    }
}
