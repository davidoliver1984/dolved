<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\TraceHttpRequests;
use App\Telemetry\DatabaseTelemetry;
use App\Telemetry\TelemetryLifecycle;
use App\Telemetry\TelemetrySdkFactory;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Metrics\MeterProviderInterface as ApiMeterProviderInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface as ApiTracerProviderInterface;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;

final class TelemetryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DatabaseTelemetry::class);
        $this->app->singleton(TraceHttpRequests::class);

        $this->app->singleton(
            TracerProviderInterface::class,
            fn (): TracerProviderInterface => $this->app
                ->make(TelemetrySdkFactory::class)
                ->tracerProvider(),
        );
        $this->app->alias(
            TracerProviderInterface::class,
            ApiTracerProviderInterface::class,
        );

        $this->app->singleton(
            MeterProviderInterface::class,
            fn (): MeterProviderInterface => $this->app
                ->make(TelemetrySdkFactory::class)
                ->meterProvider(),
        );
        $this->app->alias(
            MeterProviderInterface::class,
            ApiMeterProviderInterface::class,
        );
    }

    public function boot(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            $this->app
                ->make(DatabaseTelemetry::class)
                ->record($query);
        });

        $this->app->terminating(function (): void {
            $this->app
                ->make(TelemetryLifecycle::class)
                ->shutdown();
        });
    }
}
