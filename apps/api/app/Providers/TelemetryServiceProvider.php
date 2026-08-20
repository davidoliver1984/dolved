<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\DocumentDeletionStatus;
use App\Http\Middleware\TraceHttpRequests;
use App\Models\DocumentDeletionOperation;
use App\Models\GenerationRun;
use App\Models\IngestionEventClaim;
use App\Telemetry\DatabaseTelemetry;
use App\Telemetry\OperationalTelemetry;
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
        $this->app->singleton(OperationalTelemetry::class);
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

        GenerationRun::updated(function (GenerationRun $run): void {
            if ($run->wasChanged('first_progress_at') && $run->first_progress_at !== null) {
                $this->app->make(OperationalTelemetry::class)->operation(
                    'generation_first_part',
                    'accepted',
                    max(0.0, $run->first_progress_at->diffInMilliseconds($run->started_at ?? $run->created_at, absolute: true) / 1_000),
                );
            }
            if ($run->wasChanged('status') && $run->status->isTerminal()) {
                $this->app->make(OperationalTelemetry::class)->operation(
                    'generation_run',
                    $run->status->value,
                    max(0.0, ($run->completed_at ?? now())->diffInMilliseconds($run->started_at ?? $run->created_at, absolute: true) / 1_000),
                    $run->failure_code?->value,
                );
            }
        });
        IngestionEventClaim::updated(function (IngestionEventClaim $claim): void {
            if ($claim->wasChanged('status') && in_array($claim->status->value, ['completed', 'failed', 'cancelled'], true)) {
                $this->app->make(OperationalTelemetry::class)->operation(
                    'ingestion',
                    $claim->status->value,
                    max(0.0, ($claim->completed_at ?? $claim->failed_at ?? $claim->cancelled_at ?? now())->diffInMilliseconds($claim->claimed_at ?? $claim->created_at, absolute: true) / 1_000),
                    $claim->failure_code,
                );
            }
        });
        DocumentDeletionOperation::updated(function (DocumentDeletionOperation $operation): void {
            if ($operation->wasChanged('status') && in_array($operation->status, [DocumentDeletionStatus::Completed, DocumentDeletionStatus::Failed], true)) {
                $this->app->make(OperationalTelemetry::class)->operation(
                    'document_deletion',
                    $operation->status->value,
                    max(0.0, ($operation->completed_at ?? now())->diffInMilliseconds($operation->created_at, absolute: true) / 1_000),
                    $operation->failure_code,
                );
            }
        });

        $this->app->terminating(function (): void {
            $this->app
                ->make(TelemetryLifecycle::class)
                ->shutdown();
        });
    }
}
