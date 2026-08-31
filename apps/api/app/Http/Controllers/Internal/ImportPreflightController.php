<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Actions\Imports\RecordImportPreflightCallback;
use App\Exceptions\ImportPreflightException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportPreflightCallbackRequest;
use Illuminate\Http\JsonResponse;

final class ImportPreflightController extends Controller
{
    public function complete(string $eventId, ImportPreflightCallbackRequest $request, RecordImportPreflightCallback $record): JsonResponse
    {
        return $this->respond(fn (): string => $record->complete($eventId, $request->all()));
    }

    public function fail(string $eventId, ImportPreflightCallbackRequest $request, RecordImportPreflightCallback $record): JsonResponse
    {
        return $this->respond(fn (): string => $record->fail($eventId, $request->all()));
    }

    /** @param callable(): string $callback */
    private function respond(callable $callback): JsonResponse
    {
        try {
            return response()->json(['data' => ['outcome' => $callback()]]);
        } catch (ImportPreflightException $exception) {
            return response()->json([
                'message' => 'The import preflight callback was rejected.',
                'error' => ['code' => $exception->reason],
            ], $exception->status);
        }
    }
}
