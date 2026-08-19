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
        Schema::table('evidence_snapshots', function (Blueprint $table): void {
            $table->uuid('source_chunk_public_id')->nullable()->after('document_chunk_id');
            $table->unsignedInteger('source_chunk_ordinal')->nullable()->after('source_chunk_public_id');
            $table->uuid('source_ingestion_event_id')->nullable()->after('source_chunk_ordinal');
        });

        DB::table('evidence_snapshots')->orderBy('id')->each(function (object $snapshot): void {
            $chunk = DB::table('document_chunks')->where('id', $snapshot->document_chunk_id)->first();
            $claim = DB::table('ingestion_event_claims')->where('id', $snapshot->ingestion_event_claim_id)->first();

            if ($chunk === null || $claim === null) {
                throw new RuntimeException('EvidenceSnapshot lineage could not be backfilled.');
            }

            DB::table('evidence_snapshots')->where('id', $snapshot->id)->update([
                'source_chunk_public_id' => $chunk->public_id,
                'source_chunk_ordinal' => $chunk->ordinal,
                'source_ingestion_event_id' => $claim->event_id,
            ]);
        });

        Schema::table('evidence_snapshots', function (Blueprint $table): void {
            $table->dropForeign(['document_chunk_id']);
            $table->unsignedBigInteger('document_chunk_id')->nullable()->change();
            $table->foreign('document_chunk_id')
                ->references('id')
                ->on('document_chunks')
                ->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ingestion_event_claims DROP CONSTRAINT IF EXISTS ingestion_event_claims_status_check');
            DB::statement("ALTER TABLE ingestion_event_claims ADD CONSTRAINT ingestion_event_claims_status_check CHECK (status IN ('open', 'sealed', 'publication_authorised', 'completed', 'failed', 'cancelled'))");
        }

        Schema::table('ingestion_event_claims', function (Blueprint $table): void {
            $table->timestampTz('cancelled_at')->nullable()->after('failed_at');
        });

        Schema::create('document_ingestion_retries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('idempotency_key');
            $table->uuid('event_id')->unique();
            $table->uuid('correlation_id');
            $table->timestamps();
            $table->unique(['document_id', 'idempotency_key'], 'document_retries_document_key_unique');
            $table->index(['workspace_id', 'created_at']);
        });

        Schema::create('document_deletion_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('correlation_id');
            $table->string('status');
            $table->json('active_attempt_ids');
            $table->json('vector_scopes')->nullable();
            $table->json('cleanup_evidence')->nullable();
            $table->string('lease_token_hash')->nullable();
            $table->unsignedInteger('lease_generation')->default(0);
            $table->timestampTz('lease_expires_at')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestampTz('dispatched_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'status']);
        });
        DB::statement("CREATE UNIQUE INDEX document_deletion_operations_open_document_unique ON document_deletion_operations (document_id) WHERE status IN ('awaiting_quiescence', 'queued', 'processing', 'failed')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS document_deletion_operations_open_document_unique');
        Schema::dropIfExists('document_deletion_operations');
        Schema::dropIfExists('document_ingestion_retries');

        Schema::table('ingestion_event_claims', function (Blueprint $table): void {
            $table->dropColumn('cancelled_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ingestion_event_claims DROP CONSTRAINT IF EXISTS ingestion_event_claims_status_check');
            DB::statement("ALTER TABLE ingestion_event_claims ADD CONSTRAINT ingestion_event_claims_status_check CHECK (status IN ('open', 'sealed', 'publication_authorised', 'completed', 'failed'))");
        }

        Schema::table('evidence_snapshots', function (Blueprint $table): void {
            $table->dropForeign(['document_chunk_id']);
            $table->unsignedBigInteger('document_chunk_id')->nullable(false)->change();
            $table->foreign('document_chunk_id')
                ->references('id')
                ->on('document_chunks')
                ->restrictOnDelete();
            $table->dropColumn([
                'source_chunk_public_id',
                'source_chunk_ordinal',
                'source_ingestion_event_id',
            ]);
        });
    }
};
