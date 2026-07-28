<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Ingestion\PublishIngestionOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PublishIngestionOutboxCommand extends Command
{
    protected $signature = 'ingestion:publish
        {--once : Process at most one configured batch and exit}';

    protected $description = 'Publish durable ingestion outbox events to SQS';

    public function handle(PublishIngestionOutbox $publisher): int
    {
        do {
            if (! Schema::hasTable('outbox_events')) {
                if ($this->option('once')) {
                    $this->components->error(
                        'The outbox_events table does not exist. Run migrations first.',
                    );

                    return self::FAILURE;
                }

                $this->components->warn(
                    'Waiting for the outbox_events migration.',
                );
                sleep($this->pollInterval());

                continue;
            }

            $summary = $publisher->handle();

            if ($this->option('once')) {
                $this->components->info(sprintf(
                    'Claimed %d; published %d; retryable %d; failed %d.',
                    $summary['claimed'],
                    $summary['published'],
                    $summary['retryable'],
                    $summary['failed'],
                ));

                return self::SUCCESS;
            }

            if ($summary['claimed'] === 0) {
                sleep($this->pollInterval());
            }
        } while (true);
    }

    private function pollInterval(): int
    {
        return max(
            1,
            (int) config('ingestion.publisher.poll_interval_seconds'),
        );
    }
}
