<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Conversation\OrchestrateConversationRun;
use App\Enums\GenerationRunFailureCode;
use App\Models\GenerationRun;
use App\Telemetry\TelemetryAttributeAllowlist;
use App\Telemetry\TraceContextHeaders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Throwable;

class ExecuteGenerationRun implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public readonly ?string $traceparent;

    public readonly ?string $tracestate;

    public function __construct(public readonly int $generationRunId)
    {
        $trace = app(TraceContextHeaders::class)->current();
        $this->traceparent = $trace['traceparent'] ?? null;
        $this->tracestate = $trace['tracestate'] ?? null;
        $this->onConnection('conversation');
        $this->onQueue((string) config('conversation.queue'));
        $this->afterCommit();
    }

    public function handle(
        OrchestrateConversationRun $orchestration,
        TracerProviderInterface $tracerProvider,
        TelemetryAttributeAllowlist $allowlist,
    ): void {
        $parent = TraceContextPropagator::getInstance()->extract(array_filter([
            'traceparent' => $this->traceparent,
            'tracestate' => $this->tracestate,
        ]));
        $run = GenerationRun::query()->find($this->generationRunId);
        $span = $tracerProvider->getTracer('dolved.laravel.conversation')
            ->spanBuilder('conversation.generation.execute')
            ->setSpanKind(SpanKind::KIND_CONSUMER)
            ->setParent($parent)
            ->setAttributes($allowlist->trace([
                'rag.correlation.id' => $run?->correlation_id,
                'rag.operation.stage' => 'generation_execution',
            ]))
            ->startSpan();
        $scope = $span->activate();
        try {
            $orchestration->handle($this->generationRunId);
            $span->setAttribute('rag.operation.outcome', 'completed');
        } catch (Throwable $exception) {
            $span->setAttributes($allowlist->trace([
                'rag.operation.outcome' => 'failed',
                'error.type' => $exception::class,
            ]));
            $span->setStatus(StatusCode::STATUS_ERROR);
            throw $exception;
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(OrchestrateConversationRun::class)->fail(
            $this->generationRunId,
            GenerationRunFailureCode::InternalFailure,
        );
    }
}
