<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_answers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('correlation_id')->index();
            $table->text('question');
            $table->string('outcome');
            $table->json('unsupported_aspects');
            $table->text('insufficiency_reason')->nullable();
            $table->unsignedSmallInteger('fingerprint_scheme_version');
            $table->char('generation_fingerprint', 64);
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('contract_version');
            $table->string('prompt_version')->nullable();
            $table->string('adapter_version')->nullable();
            $table->json('generation_configuration');
            $table->json('usage')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('cost_usd', 12, 8)->nullable();
            $table->timestamps();
            $table->unique(['id', 'workspace_id'], 'generated_answers_id_workspace_unique');
            $table->index(['workspace_id', 'created_at']);
        });

        Schema::create('evidence_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('generated_answer_id');
            $table->string('evidence_handle');
            $table->foreignId('document_chunk_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->foreignId('ingestion_event_claim_id')->constrained()->restrictOnDelete();
            $table->json('source_provenance');
            $table->text('cited_text_verbatim');
            $table->char('content_digest', 64);
            $table->timestamps();
            $table->foreign(['generated_answer_id', 'workspace_id'], 'evidence_snapshots_answer_workspace_foreign')
                ->references(['id', 'workspace_id'])->on('generated_answers')->cascadeOnDelete();
            $table->unique(['generated_answer_id', 'evidence_handle'], 'evidence_snapshots_answer_handle_unique');
        });

        Schema::create('answer_parts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('generated_answer_id');
            $table->unsignedInteger('ordinal');
            $table->text('text');
            $table->timestamps();
            $table->foreign(['generated_answer_id', 'workspace_id'], 'answer_parts_answer_workspace_foreign')
                ->references(['id', 'workspace_id'])->on('generated_answers')->cascadeOnDelete();
            $table->unique(['generated_answer_id', 'ordinal']);
        });

        Schema::create('answer_part_evidence_snapshot', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('answer_part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evidence_snapshot_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('ordinal');
            $table->unique(['answer_part_id', 'evidence_snapshot_id'], 'answer_part_snapshot_unique');
            $table->unique(['answer_part_id', 'ordinal'], 'answer_part_snapshot_ordinal_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answer_part_evidence_snapshot');
        Schema::dropIfExists('answer_parts');
        Schema::dropIfExists('evidence_snapshots');
        Schema::dropIfExists('generated_answers');
    }
};
