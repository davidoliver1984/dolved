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
            $table->enum('attempt_origin', ['ingestion', 'content_clone'])
                ->default('ingestion')->after('payload_sha256');
            $table->char('materialisation_pipeline_fingerprint', 64)->nullable()
                ->after('attempt_origin');
            $table->json('materialisation_pipeline_components')->nullable()
                ->after('materialisation_pipeline_fingerprint');
            $table->index(
                ['workspace_id', 'attempt_origin', 'status'],
                'ingestion_attempts_origin_status',
            );
            $table->unique(
                ['id', 'workspace_id', 'document_id'],
                'ingestion_attempts_id_workspace_document_unique',
            );
        });

        Schema::create('document_content_clone_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('source_document_id');
            $table->unsignedBigInteger('target_document_id')->unique();
            $table->unsignedBigInteger('source_ingestion_event_claim_id');
            $table->unsignedBigInteger('target_ingestion_event_claim_id')->unique();
            $table->enum('status', [
                'authorised', 'copying', 'verifying', 'indexed',
                'cleanup_required', 'fallback_ready',
            ])->default('authorised');
            $table->char('materialisation_pipeline_fingerprint', 64);
            $table->json('materialisation_pipeline_components');
            $table->char('source_checksum_sha256', 64);
            $table->unsignedInteger('expected_point_count')->nullable();
            $table->char('expected_point_manifest_digest', 64)->nullable();
            $table->unsignedInteger('verified_point_count')->nullable();
            $table->char('verified_point_manifest_digest', 64)->nullable();
            $table->json('layer_evidence')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestampTz('authorised_at');
            $table->timestampTz('copying_at')->nullable();
            $table->timestampTz('verifying_at')->nullable();
            $table->timestampTz('indexed_at')->nullable();
            $table->timestampTz('cleanup_required_at')->nullable();
            $table->timestampTz('fallback_ready_at')->nullable();
            $table->timestamps();
            $table->foreign(['source_document_id', 'workspace_id'], 'content_clone_source_document_scope_foreign')
                ->references(['id', 'workspace_id'])->on('documents')->restrictOnDelete();
            $table->foreign(['target_document_id', 'workspace_id'], 'content_clone_target_document_scope_foreign')
                ->references(['id', 'workspace_id'])->on('documents')->restrictOnDelete();
            $table->foreign(
                ['source_ingestion_event_claim_id', 'workspace_id', 'source_document_id'],
                'content_clone_source_attempt_scope_foreign',
            )->references(['id', 'workspace_id', 'document_id'])->on('ingestion_event_claims')->restrictOnDelete();
            $table->foreign(
                ['target_ingestion_event_claim_id', 'workspace_id', 'target_document_id'],
                'content_clone_target_attempt_scope_foreign',
            )->references(['id', 'workspace_id', 'document_id'])->on('ingestion_event_claims')->restrictOnDelete();
            $table->unique(
                ['id', 'target_ingestion_event_claim_id'],
                'content_clone_operation_target_attempt_unique',
            );
            $table->index(['workspace_id', 'status'], 'content_clone_workspace_status');
        });

        Schema::create('document_content_clone_manifests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('document_content_clone_operation_id');
            $table->unsignedBigInteger('ingestion_event_claim_id');
            $table->unsignedInteger('lease_generation');
            $table->string('object_key', 1024)->unique();
            $table->string('schema_version');
            $table->unsignedInteger('entry_count');
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->enum('status', ['created', 'verified', 'consumed', 'cancelled'])->default('created');
            $table->timestampTz('expires_at');
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('consumed_at')->nullable();
            $table->enum('cleanup_state', ['not_needed', 'eligible', 'claimed', 'deleted', 'failed'])->default('not_needed');
            $table->unsignedSmallInteger('cleanup_attempt_count')->default(0);
            $table->timestampTz('cleanup_claimed_at')->nullable();
            $table->timestampTz('cleanup_last_attempted_at')->nullable();
            $table->string('cleanup_error_code')->nullable();
            $table->timestamps();
            $table->foreign(
                ['document_content_clone_operation_id', 'ingestion_event_claim_id'],
                'content_clone_manifest_operation_attempt_foreign',
            )->references(['id', 'target_ingestion_event_claim_id'])
                ->on('document_content_clone_operations')->cascadeOnDelete();
            $table->unique(
                ['ingestion_event_claim_id', 'lease_generation'],
                'content_clone_manifest_claim_generation_unique',
            );
            $table->index(['cleanup_state', 'expires_at'], 'content_clone_manifest_cleanup_scan');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE ingestion_event_claims ADD CONSTRAINT ingestion_attempt_pipeline_fingerprint_check CHECK (materialisation_pipeline_fingerprint IS NULL OR materialisation_pipeline_fingerprint ~ '^[0-9a-f]{64}$')");
            DB::statement('ALTER TABLE ingestion_event_claims ADD CONSTRAINT ingestion_attempt_pipeline_binding_check CHECK ((materialisation_pipeline_fingerprint IS NULL) = (materialisation_pipeline_components IS NULL))');
            DB::statement("ALTER TABLE document_content_clone_operations ADD CONSTRAINT content_clone_digest_check CHECK (materialisation_pipeline_fingerprint ~ '^[0-9a-f]{64}$' AND source_checksum_sha256 ~ '^[0-9a-f]{64}$' AND (expected_point_manifest_digest IS NULL OR expected_point_manifest_digest ~ '^[0-9a-f]{64}$') AND (verified_point_manifest_digest IS NULL OR verified_point_manifest_digest ~ '^[0-9a-f]{64}$'))");
            DB::statement("ALTER TABLE document_content_clone_manifests ADD CONSTRAINT content_clone_manifest_digest_check CHECK (checksum_sha256 ~ '^[0-9a-f]{64}$')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_content_clone_manifests');
        Schema::dropIfExists('document_content_clone_operations');
        Schema::table('ingestion_event_claims', function (Blueprint $table): void {
            $table->dropUnique('ingestion_attempts_id_workspace_document_unique');
            $table->dropIndex('ingestion_attempts_origin_status');
            $table->dropColumn([
                'attempt_origin', 'materialisation_pipeline_fingerprint',
                'materialisation_pipeline_components',
            ]);
        });
    }
};
