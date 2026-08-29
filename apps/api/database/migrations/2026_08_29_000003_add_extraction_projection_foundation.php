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
        Schema::table('document_extraction_artifacts', function (Blueprint $table): void {
            $table->unique(['id', 'workspace_id', 'document_id'], 'extraction_artifact_scope_unique');
        });

        Schema::create('document_extraction_projection_generations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id');
            $table->foreignId('document_extraction_artifact_id');
            $table->enum('status', ['building', 'published', 'retired', 'failed'])->default('building');
            $table->unsignedInteger('expected_element_count');
            $table->unsignedInteger('expected_warning_count');
            $table->char('expected_projection_manifest_digest', 64);
            $table->char('expected_warning_manifest_digest', 64);
            $table->char('verified_projection_manifest_digest', 64)->nullable();
            $table->char('verified_warning_manifest_digest', 64)->nullable();
            $table->json('source_extractor')->nullable();
            $table->json('normaliser')->nullable();
            $table->json('metadata')->nullable();
            $table->json('changes')->nullable();
            $table->string('failure_code')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('retired_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'workspace_id', 'document_id'], 'extraction_projection_scope_unique');
            $table->foreign(['document_id', 'workspace_id'], 'extraction_projection_document_scope_fk')
                ->references(['id', 'workspace_id'])->on('documents')->cascadeOnDelete();
            $table->foreign(
                ['document_extraction_artifact_id', 'workspace_id', 'document_id'],
                'extraction_projection_artifact_scope_fk',
            )->references(['id', 'workspace_id', 'document_id'])
                ->on('document_extraction_artifacts')->cascadeOnDelete();
            $table->index(['document_id', 'status'], 'extraction_projection_document_status');
        });

        Schema::create('document_extraction_projection_elements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projection_generation_id');
            $table->foreignId('workspace_id');
            $table->foreignId('document_id');
            $table->uuid('element_id');
            $table->unsignedInteger('ordinal');
            $table->enum('kind', ['paragraph', 'heading', 'table', 'unknown']);
            $table->text('text');
            $table->json('payload');
            $table->timestamps();
            $table->foreign(
                ['projection_generation_id', 'workspace_id', 'document_id'],
                'extraction_element_generation_scope_fk',
            )->references(['id', 'workspace_id', 'document_id'])
                ->on('document_extraction_projection_generations')->cascadeOnDelete();
            $table->unique(['projection_generation_id', 'element_id'], 'extraction_element_identity_unique');
            $table->index(['projection_generation_id', 'ordinal', 'element_id'], 'extraction_element_page_order');
        });

        Schema::create('document_extraction_projection_warnings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projection_generation_id')->constrained('document_extraction_projection_generations')->cascadeOnDelete();
            $table->unsignedInteger('ordinal');
            $table->json('payload');
            $table->timestamps();
            $table->unique(['projection_generation_id', 'ordinal'], 'extraction_warning_order_unique');
        });

        if (DB::getDriverName() === 'sqlite') {
            // Laravel's SQLite table-rebuild path drops predicates from the existing
            // partial document-lineage indexes. SQLite is test-only; preserve those
            // indexes and exercise the authoritative composite FK in PostgreSQL.
            DB::statement('ALTER TABLE documents ADD COLUMN active_extraction_projection_generation_id INTEGER NULL');
        } else {
            Schema::table('documents', function (Blueprint $table): void {
                $table->unsignedBigInteger('active_extraction_projection_generation_id')->nullable();
                $table->foreign(
                    ['active_extraction_projection_generation_id', 'workspace_id', 'id'],
                    'documents_active_extraction_projection_scope_fk',
                )->references(['id', 'workspace_id', 'document_id'])
                    ->on('document_extraction_projection_generations')->restrictOnDelete();
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE document_extraction_projection_generations ADD CONSTRAINT extraction_projection_digest_check CHECK (expected_projection_manifest_digest ~ '^[0-9a-f]{64}$' AND expected_warning_manifest_digest ~ '^[0-9a-f]{64}$' AND (verified_projection_manifest_digest IS NULL OR verified_projection_manifest_digest ~ '^[0-9a-f]{64}$') AND (verified_warning_manifest_digest IS NULL OR verified_warning_manifest_digest ~ '^[0-9a-f]{64}$'))");
            DB::statement("ALTER TABLE document_extraction_projection_generations ADD CONSTRAINT extraction_projection_state_check CHECK ((status = 'building' AND published_at IS NULL AND retired_at IS NULL AND failed_at IS NULL) OR (status = 'published' AND verified_projection_manifest_digest IS NOT NULL AND verified_warning_manifest_digest IS NOT NULL AND verified_at IS NOT NULL AND published_at IS NOT NULL AND retired_at IS NULL AND failed_at IS NULL) OR (status = 'retired' AND verified_projection_manifest_digest IS NOT NULL AND verified_warning_manifest_digest IS NOT NULL AND verified_at IS NOT NULL AND retired_at IS NOT NULL AND failed_at IS NULL) OR (status = 'failed' AND failure_code IS NOT NULL AND failed_at IS NOT NULL AND published_at IS NULL AND retired_at IS NULL))");
            DB::statement("ALTER TABLE document_extraction_projection_elements ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('english', coalesce(text, ''))) STORED");
            DB::statement('CREATE INDEX extraction_element_search_gin ON document_extraction_projection_elements USING GIN (search_vector)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE documents DROP COLUMN active_extraction_projection_generation_id');
        } else {
            Schema::table('documents', function (Blueprint $table): void {
                $table->dropForeign('documents_active_extraction_projection_scope_fk');
                $table->dropColumn('active_extraction_projection_generation_id');
            });
        }
        Schema::dropIfExists('document_extraction_projection_warnings');
        Schema::dropIfExists('document_extraction_projection_elements');
        Schema::dropIfExists('document_extraction_projection_generations');
        Schema::table('document_extraction_artifacts', function (Blueprint $table): void {
            $table->dropUnique('extraction_artifact_scope_unique');
        });
    }
};
