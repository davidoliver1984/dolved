<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Platform\ManageObservabilityPolicy;
use App\Contracts\Platform\OperationalMetricsReader;
use App\Telemetry\OperationTracer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PlatformOperationsController extends Controller
{
    public function access(OperationTracer $tracer): JsonResponse
    {
        return $tracer->run('platform.operations.access', [
            'rag.operation.stage' => 'platform_administration',
        ], fn () => response()->json(['data' => ['access' => 'administrator']]));
    }

    public function health(
        OperationalMetricsReader $metrics,
        ManageObservabilityPolicy $policies,
        OperationTracer $tracer,
    ): JsonResponse {
        return $tracer->run('platform.operations.health', [
            'rag.operation.stage' => 'platform_administration',
        ], fn () => response()->json(['data' => $metrics->snapshot() + [
            'operational_policy' => $policies->current(),
        ]]));
    }

    public function policy(
        ManageObservabilityPolicy $policies,
        OperationTracer $tracer,
    ): JsonResponse {
        return $tracer->run('platform.operations.policy.read', [
            'rag.operation.stage' => 'platform_administration',
        ], fn () => response()->json(['data' => ['policy' => $policies->current()]]));
    }

    public function storePolicy(
        Request $request,
        ManageObservabilityPolicy $policies,
        OperationTracer $tracer,
    ): JsonResponse {
        $values = $request->validate([
            'trace_sampling_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'slow_operation_threshold_seconds' => ['required', 'numeric', 'min:0.1', 'max:3600'],
            'log_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'trace_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'metric_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        return $tracer->run('platform.operations.policy.create', [
            'rag.operation.stage' => 'platform_administration',
        ], function () use ($request, $policies, $values): JsonResponse {
            $policy = $policies->create($request->user(), $values, (string) Str::uuid());

            return response()->json(['data' => ['policy' => $policy]], 201);
        });
    }
}
