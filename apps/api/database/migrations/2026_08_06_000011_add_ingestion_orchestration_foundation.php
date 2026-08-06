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
        Schema::table('ingestion_event_claims', function (Blueprint $table): void {
            $table->foreignId('workspace_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->after('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('embedding_space_generation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('workspace_corpus_generation_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('status', [
                'open',
                'sealed',
                'publication_authorised',
                'completed',
                'failed',
            ])->default('open');
            $table->char('lease_token_hash', 64)->nullable();
            $table->unsignedInteger('lease_generation')->default(1);
            $table->timestampTz('lease_expires_at')->nullable();
            $table->unsignedInteger('expected_chunk_count')->nullable();
            $table->char('chunk_manifest_digest', 64)->nullable();
            $table->timestampTz('sealed_at')->nullable();
            $table->unsignedInteger('expected_point_count')->nullable();
            $table->char('point_manifest_digest', 64)->nullable();
            $table->char('embedding_profile_fingerprint', 64)->nullable();
            $table->json('publication_evidence')->nullable();
            $table->timestampTz('publication_authorised_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->index(['workspace_id', 'document_id', 'status'], 'ingestion_attempts_scope_status');
        });

        DB::table('ingestion_event_claims')->orderBy('id')->each(function (object $claim): void {
            $workspaceId = DB::table('workspaces')->where('public_id', $claim->workspace_public_id)->value('id');
            $documentId = DB::table('documents')->where('public_id', $claim->document_public_id)->value('id');
            DB::table('ingestion_event_claims')->where('id', $claim->id)->update([
                'workspace_id' => $workspaceId,
                'document_id' => $documentId,
            ]);
        });
        Schema::table('ingestion_event_claims', function (Blueprint $table): void {
            $table->foreignId('workspace_id')->nullable(false)->change();
            $table->foreignId('document_id')->nullable(false)->change();
        });

        Schema::table('document_chunks', function (Blueprint $table): void {
            $table->dropUnique('document_chunks_document_configuration_ordinal_unique');
            $table->foreignId('ingestion_event_claim_id')->nullable()->after('document_id')
                ->constrained()->restrictOnDelete();
            $table->char('content_digest', 64)->nullable()->after('provenance');
            $table->unique(
                ['ingestion_event_claim_id', 'ordinal'],
                'document_chunks_attempt_ordinal_unique',
            );
            $table->unique(
                ['ingestion_event_claim_id', 'public_id'],
                'document_chunks_attempt_public_id_unique',
            );
        });
        DB::statement(
            'CREATE UNIQUE INDEX document_chunks_legacy_document_configuration_ordinal_unique
             ON document_chunks (document_id, configuration_fingerprint, ordinal)
             WHERE ingestion_event_claim_id IS NULL'
        );

        Schema::create('ingestion_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->index();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->string('action');
            $table->string('outcome');
            $table->json('context');
            $table->timestampTz('occurred_at');
            $table->timestamps();
            $table->unique(['event_id', 'action', 'outcome'], 'ingestion_audit_event_action_outcome_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE ingestion_event_claims ADD CONSTRAINT ingestion_attempts_lease_hash CHECK (lease_token_hash IS NULL OR lease_token_hash ~ '^[0-9a-f]{64}$')");
            DB::statement("ALTER TABLE ingestion_event_claims ADD CONSTRAINT ingestion_attempts_manifest_hashes CHECK ((chunk_manifest_digest IS NULL OR chunk_manifest_digest ~ '^[0-9a-f]{64}$') AND (point_manifest_digest IS NULL OR point_manifest_digest ~ '^[0-9a-f]{64}$'))");
            DB::statement('ALTER TABLE ingestion_event_claims ADD CONSTRAINT ingestion_attempts_counts_non_negative CHECK ((expected_chunk_count IS NULL OR expected_chunk_count >= 0) AND (expected_point_count IS NULL OR expected_point_count >= 0))');
            DB::statement("ALTER TABLE document_chunks ADD CONSTRAINT document_chunks_content_digest_sha256 CHECK (content_digest IS NULL OR content_digest ~ '^[0-9a-f]{64}$')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ingestion_audit_events');
        Schema::table('document_chunks', function (Blueprint $table): void {
            DB::statement('DROP INDEX IF EXISTS document_chunks_legacy_document_configuration_ordinal_unique');
            $table->dropUnique('document_chunks_attempt_ordinal_unique');
            $table->dropUnique('document_chunks_attempt_public_id_unique');
            $table->dropConstrainedForeignId('ingestion_event_claim_id');
            $table->dropColumn('content_digest');
            $table->unique(
                ['document_id', 'configuration_fingerprint', 'ordinal'],
                'document_chunks_document_configuration_ordinal_unique',
            );
        });
        Schema::table('ingestion_event_claims', function (Blueprint $table): void {
            $table->dropIndex('ingestion_attempts_scope_status');
            $table->dropConstrainedForeignId('workspace_id');
            $table->dropConstrainedForeignId('document_id');
            $table->dropConstrainedForeignId('embedding_space_generation_id');
            $table->dropConstrainedForeignId('workspace_corpus_generation_id');
            $table->dropColumn([
                'status', 'lease_token_hash', 'lease_generation', 'lease_expires_at',
                'expected_chunk_count', 'chunk_manifest_digest', 'sealed_at',
                'expected_point_count', 'point_manifest_digest',
                'embedding_profile_fingerprint', 'publication_evidence',
                'publication_authorised_at', 'completed_at', 'failed_at',
                'failure_code', 'failure_message',
            ]);
        });
    }
};
