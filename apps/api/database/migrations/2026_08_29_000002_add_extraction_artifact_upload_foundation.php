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
        Schema::create('document_extraction_upload_authorisations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->foreignId('ingestion_event_claim_id')->constrained()->restrictOnDelete();
            $table->string('purpose')->default('extraction_artifact_upload');
            $table->string('object_key', 1024)->unique();
            $table->unsignedInteger('lease_generation');
            $table->string('contract_version')->default('document-extraction-artifact-v1');
            $table->timestampTz('expires_at');
            $table->enum('status', ['authorised', 'verified', 'failed', 'cancelled'])->default('authorised');
            $table->char('artifact_sha256', 64)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('projection_manifest_digest', 64)->nullable();
            $table->char('warning_manifest_digest', 64)->nullable();
            $table->unsignedInteger('element_count')->nullable();
            $table->unsignedInteger('warning_count')->nullable();
            $table->string('storage_version_id')->nullable();
            $table->string('storage_etag')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->enum('cleanup_state', ['not_needed', 'eligible', 'claimed', 'deleted', 'failed'])->default('not_needed');
            $table->unsignedSmallInteger('cleanup_attempt_count')->default(0);
            $table->timestampTz('cleanup_claimed_at')->nullable();
            $table->timestampTz('cleanup_last_attempted_at')->nullable();
            $table->string('cleanup_error_code')->nullable();
            $table->timestamps();
            $table->unique(
                ['ingestion_event_claim_id', 'lease_generation'],
                'extraction_upload_attempt_generation_unique',
            );
            $table->index(['cleanup_state', 'expires_at'], 'extraction_upload_cleanup_scan');
        });

        Schema::create('document_extraction_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->foreignId('upload_authorisation_id')->unique()
                ->constrained('document_extraction_upload_authorisations')->restrictOnDelete();
            $table->string('object_key', 1024)->unique();
            $table->string('contract_version');
            $table->char('artifact_sha256', 64);
            $table->unsignedBigInteger('size_bytes');
            $table->char('projection_manifest_digest', 64);
            $table->char('warning_manifest_digest', 64);
            $table->unsignedInteger('element_count');
            $table->unsignedInteger('warning_count');
            $table->string('storage_version_id')->nullable();
            $table->string('storage_etag')->nullable();
            $table->timestampTz('verified_at');
            $table->timestampTz('published_at')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE document_extraction_upload_authorisations ADD CONSTRAINT extraction_upload_purpose_check CHECK (purpose = 'extraction_artifact_upload')");
            DB::statement("ALTER TABLE document_extraction_upload_authorisations ADD CONSTRAINT extraction_upload_digest_check CHECK ((artifact_sha256 IS NULL OR artifact_sha256 ~ '^[0-9a-f]{64}$') AND (projection_manifest_digest IS NULL OR projection_manifest_digest ~ '^[0-9a-f]{64}$') AND (warning_manifest_digest IS NULL OR warning_manifest_digest ~ '^[0-9a-f]{64}$'))");
            DB::statement("ALTER TABLE document_extraction_artifacts ADD CONSTRAINT extraction_artifact_digest_check CHECK (artifact_sha256 ~ '^[0-9a-f]{64}$' AND projection_manifest_digest ~ '^[0-9a-f]{64}$' AND warning_manifest_digest ~ '^[0-9a-f]{64}$')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_extraction_artifacts');
        Schema::dropIfExists('document_extraction_upload_authorisations');
    }
};
