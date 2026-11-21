<?php

declare(strict_types=1);

namespace App\Queries\Workspaces;

use App\Enums\IngestionAttemptOrigin;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class GetWorkspaceUsage
{
    /** @return array<string, mixed> */
    public function handle(Workspace $workspace, string $range): array
    {
        $end = CarbonImmutable::now('UTC');
        $start = match ($range) {
            '7d' => $end->subDays(7),
            'month' => $end->startOfMonth(),
            default => $end->subDays(30),
        };
        $activeStatuses = ['uploading', 'uploaded', 'queued', 'processing', 'indexed', 'failed'];
        $documents = DB::table('documents')->where('workspace_id', $workspace->id)->whereIn('status', $activeStatuses);
        $gauges = [
            'active_documents' => (clone $documents)->count(),
            'logical_source_bytes' => (int) ((clone $documents)->sum('size_bytes') ?? 0),
            'indexed_chunks' => $workspace->active_workspace_corpus_generation_id === null ? 0 : DB::table('workspace_corpus_generation_chunks as assignments')
                ->join('document_chunks as chunks', 'chunks.id', '=', 'assignments.document_chunk_id')
                ->join('documents', 'documents.id', '=', 'chunks.document_id')
                ->where('assignments.workspace_id', $workspace->id)
                ->where('assignments.workspace_corpus_generation_id', $workspace->active_workspace_corpus_generation_id)
                ->whereIn('documents.status', $activeStatuses)
                ->count(),
        ];
        $activities = DB::table('workspace_activity_events')
            ->where('workspace_id', $workspace->id)->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end)
            ->selectRaw('event_kind, outcome, COUNT(*) AS aggregate_count')->groupBy('event_kind', 'outcome')->get();
        $usage = DB::table('workspace_usage_events')
            ->where('workspace_id', $workspace->id)->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end)
            ->selectRaw('operation_kind, provider, model, cost_basis, pricing_snapshot, SUM(request_count) AS request_count, SUM(retry_count) AS retry_count, SUM(input_tokens) AS input_tokens, SUM(cached_input_tokens) AS cached_input_tokens, SUM(output_tokens) AS output_tokens, SUM(latency_ms) AS latency_ms, SUM(cost_usd) AS cost_usd, COUNT(*) AS observation_count')
            ->groupBy('operation_kind', 'provider', 'model', 'cost_basis', 'pricing_snapshot')->orderBy('operation_kind')->get();
        $materialisationAttempts = DB::table('ingestion_event_claims')
            ->where('workspace_id', $workspace->id)
            ->where('claimed_at', '>=', $start)
            ->where('claimed_at', '<', $end)
            ->selectRaw('attempt_origin, status, COUNT(*) AS aggregate_count')
            ->groupBy('attempt_origin', 'status')
            ->orderBy('attempt_origin')
            ->orderBy('status')
            ->get();
        $runs = DB::table('generation_runs')
            ->where('workspace_id', $workspace->id)->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->get(['id', 'status', 'retrieval_outcome', 'started_at', 'first_progress_at', 'completed_at']);
        $duration = static function (mixed $from, mixed $to): ?int {
            if ($from === null || $to === null) {
                return null;
            }

            return max(0, CarbonImmutable::parse((string) $from)->diffInMilliseconds(CarbonImmutable::parse((string) $to)));
        };
        $firstResponse = $runs->map(fn ($run): ?int => $duration($run->started_at, $run->first_progress_at))->filter(fn ($value): bool => $value !== null)->values();
        $completion = $runs->map(fn ($run): ?int => $duration($run->started_at, $run->completed_at))->filter(fn ($value): bool => $value !== null)->values();
        $runStatuses = $runs->countBy('status')->map(fn (int $count, string $status): array => ['status' => $status, 'aggregate_count' => $count])->values();
        $retrievedCounts = DB::table('generated_answers')
            ->join('generation_runs', 'generation_runs.id', '=', 'generated_answers.generation_run_id')
            ->leftJoin('retrieved_source_references', 'retrieved_source_references.generated_answer_id', '=', 'generated_answers.id')
            ->where('generation_runs.workspace_id', $workspace->id)
            ->where('generation_runs.created_at', '>=', $start)->where('generation_runs.created_at', '<', $end)
            ->groupBy('generated_answers.id')->selectRaw('COUNT(retrieved_source_references.id) AS passage_count')->pluck('passage_count');
        $syncStatuses = DB::table('dify_document_bindings')->where('workspace_id', $workspace->id)
            ->selectRaw('sync_status, COUNT(*) AS aggregate_count')->groupBy('sync_status')->orderBy('sync_status')->get();

        return [
            'range' => ['key' => $range, 'start' => $start->toIso8601String(), 'end' => $end->toIso8601String(), 'semantics' => '[start,end) UTC'],
            'as_of' => $end->toIso8601String(),
            'gauges' => $gauges,
            'historical' => [
                'ingestion_failures' => DB::table('ingestion_event_claims')
                    ->where('workspace_id', $workspace->id)
                    ->where('attempt_origin', IngestionAttemptOrigin::Ingestion->value)
                    ->where('status', 'failed')->where('failed_at', '>=', $start)->where('failed_at', '<', $end)->count(),
                'content_clone_failures' => DB::table('ingestion_event_claims')
                    ->where('workspace_id', $workspace->id)
                    ->where('attempt_origin', IngestionAttemptOrigin::ContentClone->value)
                    ->where('status', 'failed')->where('failed_at', '>=', $start)->where('failed_at', '<', $end)->count(),
                'materialisation_attempts' => $materialisationAttempts,
                'activity' => $activities,
                'usage' => $usage,
                'chat' => [
                    'accepted_submissions' => $runs->count(),
                    'statuses' => $runStatuses,
                    'first_visible_response_ms_average' => $firstResponse->isEmpty() ? null : (int) round($firstResponse->average()),
                    'completion_ms_average' => $completion->isEmpty() ? null : (int) round($completion->average()),
                ],
                'retrieval' => [
                    'runs_with_recorded_sources' => $retrievedCounts->count(),
                    'average_returned_passages' => $retrievedCounts->isEmpty() ? null : round((float) $retrievedCounts->average(), 2),
                    'empty_retrievals' => $runs->whereIn('retrieval_outcome', ['no_eligible_evidence', 'no_retrieval_candidates'])->count(),
                ],
                'document_sync' => $syncStatuses,
                'unknown_cost_observations' => $usage->where('cost_basis', 'unavailable')->sum('observation_count'),
            ],
            'coverage' => [
                'status' => $runs->isEmpty() && $usage->isEmpty() && $activities->isEmpty() ? 'no_data' : 'partial',
                'collected_at' => $end->toIso8601String(),
                'usage_observation_count' => $usage->sum('observation_count'),
                'limitations' => [
                    'Latency averages include only runs with recorded timestamps; percentiles are unavailable in this pilot view.',
                    'Cost entries marked unavailable are excluded from priced subtotals and are never treated as zero.',
                    'Retrieved passage counts describe recorded source references, not claim-level support.',
                ],
            ],
            'labels' => [
                'logical_source_bytes' => 'Logical uploaded source bytes; not physical storage or billing usage.',
                'cost' => 'Provider-reported and estimated cost are distinct; unavailable cost is not zero. Figures are not billing-grade.',
            ],
        ];
    }
}
