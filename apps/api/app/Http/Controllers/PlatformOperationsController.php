<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Platform\OperationalMetricsReader;
use Illuminate\Http\JsonResponse;

final class PlatformOperationsController extends Controller
{
    public function access(): JsonResponse
    {
        return response()->json(['data' => ['access' => 'administrator']]);
    }

    public function health(OperationalMetricsReader $metrics): JsonResponse
    {
        return response()->json(['data' => $metrics->snapshot()]);
    }
}
