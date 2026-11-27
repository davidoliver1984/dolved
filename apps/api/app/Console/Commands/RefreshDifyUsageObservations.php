<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DifyDocumentBinding;
use App\Services\Dify\DifyKnowledgeClient;
use Illuminate\Console\Command;
use Throwable;

final class RefreshDifyUsageObservations extends Command
{
    protected $signature = 'dify:refresh-usage-observations {--workspace=} {--limit=100}';

    protected $description = 'Refresh cached Dify segment counts for available document bindings';

    public function handle(DifyKnowledgeClient $dify): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $query = DifyDocumentBinding::query()
            ->where('sync_status', 'available')
            ->whereNotNull('remote_document_id')
            ->orderByRaw('segment_count_observed_at IS NULL DESC')
            ->orderBy('segment_count_observed_at')
            ->limit($limit);
        if (is_string($this->option('workspace')) && $this->option('workspace') !== '') {
            $query->whereHas('document.workspace', fn ($workspace) => $workspace
                ->where('public_id', $this->option('workspace')));
        }

        $ok = 0;
        $unavailable = 0;
        foreach ($query->get() as $binding) {
            try {
                $binding->forceFill([
                    'indexed_segment_count' => $dify->segmentCount($binding->dataset_id, $binding->remote_document_id),
                    'segment_count_status' => 'available',
                    'segment_count_observed_at' => now(),
                ])->save();
                $ok++;
            } catch (Throwable) {
                $binding->forceFill([
                    'indexed_segment_count' => null,
                    'segment_count_status' => 'unavailable',
                    'segment_count_observed_at' => now(),
                ])->save();
                $unavailable++;
            }
        }

        $this->components->info("Observed {$ok}; unavailable {$unavailable}.");

        return self::SUCCESS;
    }
}
