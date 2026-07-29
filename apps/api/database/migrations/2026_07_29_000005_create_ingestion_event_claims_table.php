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
        Schema::create('ingestion_event_claims', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->uuid('workspace_public_id');
            $table->uuid('document_public_id');
            $table->uuid('correlation_id')->index();
            $table->char('payload_sha256', 64);
            $table->timestampTz('claimed_at');
            $table->timestamps();

            $table->index(
                ['workspace_public_id', 'document_public_id'],
                'ingestion_claims_tenant_document',
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE ingestion_event_claims
                ADD CONSTRAINT ingestion_claims_payload_sha256
                CHECK (payload_sha256 ~ '^[0-9a-f]{64}$')"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ingestion_event_claims');
    }
};
