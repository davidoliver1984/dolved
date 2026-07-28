<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_type');
            $table->unsignedSmallInteger('event_version');
            $table->uuid('workspace_public_id')->index();
            $table->uuid('document_public_id')->index();
            $table->uuid('correlation_id')->index();
            $table->json('payload');
            $table->timestampTz('occurred_at');
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('claimed_at')->nullable();
            $table->uuid('claim_token')->nullable()->unique();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(
                ['published_at', 'failed_at', 'next_attempt_at'],
                'outbox_events_publication_lookup',
            );
            $table->index(
                ['claimed_at', 'created_at'],
                'outbox_events_claim_lookup',
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE outbox_events
                ADD CONSTRAINT outbox_events_positive_version
                CHECK (event_version > 0)'
            );
            DB::statement(
                'ALTER TABLE outbox_events
                ADD CONSTRAINT outbox_events_terminal_state
                CHECK (published_at IS NULL OR failed_at IS NULL)'
            );
            DB::statement(
                'ALTER TABLE outbox_events
                ADD CONSTRAINT outbox_events_claim_pair
                CHECK (
                    (claimed_at IS NULL AND claim_token IS NULL)
                    OR (claimed_at IS NOT NULL AND claim_token IS NOT NULL)
                )'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
