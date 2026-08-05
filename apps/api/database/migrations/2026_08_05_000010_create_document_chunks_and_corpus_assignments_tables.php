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
        Schema::table('documents', function (Blueprint $table): void {
            $table->unique(
                ['id', 'workspace_id'],
                'documents_id_workspace_unique'
            );
        });

        Schema::create('document_chunks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')
                ->constrained()
                ->restrictOnDelete();
            $table->unsignedBigInteger('document_id');
            $table->unsignedInteger('ordinal');
            $table->text('text');
            $table->unsignedInteger('token_count');
            $table->string('strategy_name');
            $table->string('strategy_version');
            $table->json('configuration');
            $table->char('configuration_fingerprint', 64);
            $table->json('provenance');
            $table->timestamps();

            $table->foreign(['document_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('documents')
                ->restrictOnDelete();
            $table->unique(
                ['id', 'workspace_id'],
                'document_chunks_id_workspace_unique'
            );
            $table->unique(
                ['document_id', 'configuration_fingerprint', 'ordinal'],
                'document_chunks_document_configuration_ordinal_unique'
            );
            $table->index(['workspace_id', 'document_id']);
        });

        Schema::create('workspace_corpus_generation_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')
                ->constrained()
                ->restrictOnDelete();
            $table->unsignedBigInteger('workspace_corpus_generation_id');
            $table->unsignedBigInteger('document_chunk_id');
            $table->timestamps();

            $table->foreign(
                ['workspace_corpus_generation_id', 'workspace_id'],
                'corpus_generation_chunks_generation_workspace_foreign'
            )
                ->references(['id', 'workspace_id'])
                ->on('workspace_corpus_generations')
                ->cascadeOnDelete();
            $table->foreign(
                ['document_chunk_id', 'workspace_id'],
                'corpus_generation_chunks_chunk_workspace_foreign'
            )
                ->references(['id', 'workspace_id'])
                ->on('document_chunks')
                ->restrictOnDelete();
            $table->unique(
                ['workspace_corpus_generation_id', 'document_chunk_id'],
                'corpus_generation_chunks_generation_chunk_unique'
            );
            $table->index(['workspace_id', 'document_chunk_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE document_chunks
                ADD CONSTRAINT document_chunks_token_count_positive
                CHECK (token_count > 0)'
            );
            DB::statement(
                "ALTER TABLE document_chunks
                ADD CONSTRAINT document_chunks_text_not_blank
                CHECK (NULLIF(BTRIM(text), '') IS NOT NULL)"
            );
            DB::statement(
                "ALTER TABLE document_chunks
                ADD CONSTRAINT document_chunks_configuration_fingerprint_sha256
                CHECK (configuration_fingerprint ~ '^[0-9a-f]{64}$')"
            );
            DB::statement(
                "ALTER TABLE document_chunks
                ADD CONSTRAINT document_chunks_provenance_non_empty
                CHECK (
                    json_typeof(provenance) = 'array'
                    AND json_array_length(provenance) > 0
                )"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_corpus_generation_chunks');
        Schema::dropIfExists('document_chunks');

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropUnique('documents_id_workspace_unique');
        });
    }
};
