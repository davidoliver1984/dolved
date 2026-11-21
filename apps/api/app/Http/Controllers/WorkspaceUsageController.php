<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\WorkspaceUsageRequest;
use App\Models\User;
use App\Queries\Workspaces\FindWorkspaceForUser;
use App\Queries\Workspaces\GetWorkspaceUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class WorkspaceUsageController extends Controller
{
    public function show(WorkspaceUsageRequest $request, string $workspacePublicId, FindWorkspaceForUser $workspaces, GetWorkspaceUsage $usage): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $membership = $workspaces->handle($user, $workspacePublicId);
        Gate::authorize('viewUsage', $membership->workspace);

        return response()->json(['data' => $usage->handle($membership->workspace, (string) $request->validated('range', '30d'))]);
    }

    public function export(WorkspaceUsageRequest $request, string $workspacePublicId, FindWorkspaceForUser $workspaces, GetWorkspaceUsage $usage): Response
    {
        /** @var User $user */
        $user = $request->user();
        $membership = $workspaces->handle($user, $workspacePublicId);
        Gate::authorize('viewUsage', $membership->workspace);
        $snapshot = $usage->handle($membership->workspace, (string) $request->validated('range', '30d'));
        $safe = static function (mixed $value): string {
            $text = (string) ($value ?? 'unavailable');

            return preg_match('/^[=+\-@]/', $text) === 1 ? "'".$text : $text;
        };
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['generated_at_utc', $snapshot['as_of']]);
        fputcsv($stream, ['interval_start_utc', $snapshot['range']['start']]);
        fputcsv($stream, ['interval_end_utc_exclusive', $snapshot['range']['end']]);
        fputcsv($stream, ['coverage', $snapshot['coverage']['status']]);
        fputcsv($stream, []);
        fputcsv($stream, ['operation_kind', 'provider', 'model', 'cost_basis', 'requests', 'retries', 'input_tokens', 'output_tokens', 'cost_usd', 'observation_count']);
        foreach ($snapshot['historical']['usage'] as $row) {
            fputcsv($stream, array_map($safe, [
                $row->operation_kind, $row->provider, $row->model, $row->cost_basis, $row->request_count,
                $row->retry_count, $row->input_tokens, $row->output_tokens, $row->cost_usd, $row->observation_count,
            ]));
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return response($csv === false ? '' : $csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="workspace-usage-'.$snapshot['range']['key'].'-'.$snapshot['as_of'].'.csv"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
