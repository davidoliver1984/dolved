<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Actions\Platform\ManageObservabilityPolicy;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class ObservabilityReconciliationController extends Controller
{
    public function plan(Request $request, ManageObservabilityPolicy $policies): JsonResponse
    {
        try {
            return response()->json(['data' => $policies->authorizePlan($request->attributes->get('observability_reconciliation'))]);
        } catch (RuntimeException) {
            return response()->json(['message' => 'The reconciliation plan request was rejected.'], 409);
        }
    }

    public function acknowledge(Request $request, ManageObservabilityPolicy $policies): JsonResponse
    {
        $payload = $request->validate([
            'outcome' => ['required', 'in:SUCCEEDED,FAILED'],
            'observed_value' => ['present'],
            'expected_digest' => ['required', 'string', 'size:64'],
            'effective_configuration_verified' => ['required', 'boolean'],
            'failure_category' => ['nullable', 'string', 'max:64'],
            'applied_at' => ['required', 'date_format:Y-m-d\\TH:i:sP'],
        ]);
        try {
            return response()->json(['data' => $policies->acknowledge(
                $request->attributes->get('observability_reconciliation'),
                $payload,
            )]);
        } catch (RuntimeException) {
            return response()->json(['message' => 'The reconciliation acknowledgement was rejected.'], 409);
        }
    }
}
