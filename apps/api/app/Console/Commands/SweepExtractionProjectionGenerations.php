<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ExtractionProjectionStatus;
use App\Models\DocumentExtractionProjectionGeneration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SweepExtractionProjectionGenerations extends Command
{
    protected $signature = 'documents:sweep-extraction-projections';

    protected $description = 'Remove abandoned, retired or failed extraction projection generations after the safety grace period.';

    public function handle(): int
    {
        $cutoff = now()->subSeconds(max(1, (int) config('ingestion.orchestration.extraction_projection_cleanup_grace_seconds')));
        $limit = max(1, (int) config('ingestion.orchestration.extraction_projection_cleanup_batch_size'));
        $ids = DocumentExtractionProjectionGeneration::query()
            ->whereIn('status', [
                ExtractionProjectionStatus::Building,
                ExtractionProjectionStatus::Retired,
                ExtractionProjectionStatus::Failed,
            ])
            ->where(function ($query) use ($cutoff): void {
                $query->where('retired_at', '<=', $cutoff)
                    ->orWhere('failed_at', '<=', $cutoff)
                    ->orWhere(function ($query) use ($cutoff): void {
                        $query->where('status', ExtractionProjectionStatus::Building)
                            ->where('created_at', '<=', $cutoff);
                    });
            })
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('documents')
                    ->whereColumn('documents.active_extraction_projection_generation_id', 'document_extraction_projection_generations.id');
            })
            ->orderBy('id')->limit($limit)->pluck('id');

        $deleted = DB::transaction(fn (): int => DocumentExtractionProjectionGeneration::query()
            ->whereKey($ids)
            ->whereIn('status', [
                ExtractionProjectionStatus::Building,
                ExtractionProjectionStatus::Retired,
                ExtractionProjectionStatus::Failed,
            ])
            ->delete());
        $this->info("Removed {$deleted} inactive extraction projection generations.");

        return self::SUCCESS;
    }
}
