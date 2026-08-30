<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DocumentFamily;
use App\Support\Documents\MaintainDocumentFamilyActivitySummary;
use Illuminate\Console\Command;

final class RebuildDocumentFamilyActivitySummary extends Command
{
    protected $signature = 'documents:rebuild-family-activity {--after-id=0} {--limit=500} {--family-id=}';

    protected $description = 'Rebuild the exact document-family activity projection in bounded primary-key order';

    public function handle(MaintainDocumentFamilyActivitySummary $activity): int
    {
        $query = DocumentFamily::query()->orderBy('id');
        if ($familyId = $this->option('family-id')) {
            $query->whereKey((int) $familyId);
        } else {
            $query->where('id', '>', max(0, (int) $this->option('after-id')))
                ->limit(max(1, min(5000, (int) $this->option('limit'))));
        }

        $count = 0;
        foreach ($query->get() as $family) {
            $activity->rebuild($family);
            $count++;
        }
        $this->info("Rebuilt {$count} document-family activity summaries.");

        return self::SUCCESS;
    }
}
