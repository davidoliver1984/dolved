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
        Schema::create('workspace_corpus_materialisation_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('workspace_corpus_generation_id')
                ->constrained('workspace_corpus_generations')
                ->restrictOnDelete();
            $table->unsignedInteger('batch_number');
            $table->uuid('request_id')->unique();
            $table->unsignedInteger('input_count');
            $table->char('input_identity_digest', 64);
            $table->enum('status', ['pending', 'completed'])->default('pending');
            $table->char('point_manifest_digest', 64)->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['workspace_corpus_generation_id', 'batch_number'],
                'corpus_materialisation_generation_batch_unique',
            );
            $table->foreign(
                ['workspace_corpus_generation_id', 'workspace_id'],
                'corpus_materialisation_generation_workspace_foreign',
            )
                ->references(['id', 'workspace_id'])
                ->on('workspace_corpus_generations')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE workspace_corpus_materialisation_batches ADD CONSTRAINT corpus_materialisation_batch_digests CHECK (input_identity_digest ~ '^[0-9a-f]{64}$' AND (point_manifest_digest IS NULL OR point_manifest_digest ~ '^[0-9a-f]{64}$'))");
            DB::statement("ALTER TABLE workspace_corpus_materialisation_batches ADD CONSTRAINT corpus_materialisation_batch_completion CHECK ((status = 'pending' AND point_manifest_digest IS NULL AND completed_at IS NULL) OR (status = 'completed' AND point_manifest_digest IS NOT NULL AND completed_at IS NOT NULL))");
            DB::statement('ALTER TABLE workspace_corpus_materialisation_batches ADD CONSTRAINT corpus_materialisation_batch_input_count CHECK (input_count BETWEEN 1 AND 100)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_corpus_materialisation_batches');
    }
};
