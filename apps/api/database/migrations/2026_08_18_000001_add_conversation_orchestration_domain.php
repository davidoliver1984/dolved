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
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 160);
            $table->string('status');
            $table->timestamps();
            $table->unique(['id', 'workspace_id'], 'conversations_id_workspace_unique');
            $table->index(['workspace_id', 'status', 'updated_at']);
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('ordinal');
            $table->string('role');
            $table->string('kind')->nullable();
            $table->text('display_text');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('in_reply_to_message_id')->nullable()->constrained('messages')->restrictOnDelete();
            $table->uuid('submission_idempotency_key')->nullable();
            $table->timestamps();
            $table->unique(['id', 'workspace_id'], 'messages_id_workspace_unique');
            $table->unique(['conversation_id', 'ordinal']);
            $table->unique(['conversation_id', 'submission_idempotency_key'], 'messages_submission_idempotency_unique');
            $table->index(['workspace_id', 'conversation_id', 'ordinal']);
        });

        Schema::create('generation_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_message_id')->constrained('messages')->restrictOnDelete();
            $table->foreignId('assistant_message_id')->nullable()->constrained('messages')->restrictOnDelete();
            $table->foreignId('retry_of_run_id')->nullable()->constrained('generation_runs')->restrictOnDelete();
            $table->uuid('retry_idempotency_key')->nullable();
            $table->string('status');
            $table->string('failure_code')->nullable();
            $table->string('context_policy_version')->nullable();
            $table->string('contextualiser_version')->nullable();
            $table->char('resolved_query_digest', 64)->nullable();
            $table->string('retrieval_outcome')->nullable();
            $table->char('generation_fingerprint', 64)->nullable();
            $table->uuid('correlation_id')->index();
            $table->json('usage')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('first_progress_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancellation_requested_at')->nullable();
            $table->timestampTz('cancellation_acknowledged_at')->nullable();
            $table->unsignedBigInteger('next_event_sequence')->default(1);
            $table->timestamps();
            $table->unique(['id', 'workspace_id'], 'generation_runs_id_workspace_unique');
            $table->unique('assistant_message_id');
            $table->unique(['retry_of_run_id', 'retry_idempotency_key'], 'generation_runs_retry_idempotency_unique');
            $table->index(['workspace_id', 'conversation_id', 'status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX generation_runs_one_active_per_conversation ON generation_runs (conversation_id) WHERE status IN ('queued', 'contextualising', 'retrieving', 'preparing_evidence', 'generating', 'validating', 'cancellation_requested')");
        }

        Schema::create('contextualisation_result_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('generation_run_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->text('clarification_question')->nullable();
            $table->boolean('used_prior_context');
            $table->json('interpretation_metadata')->nullable();
            $table->string('contextualiser_version');
            $table->json('usage')->nullable();
            $table->uuid('correlation_id')->index();
            $table->timestamps();
        });

        Schema::create('retrieval_outcome_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('generation_run_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('outcome');
            $table->string('clarification_source')->nullable();
            $table->string('clarification_reason')->nullable();
            $table->json('resolved_temporal_authority');
            $table->json('resolved_applicability_location');
            $table->json('lineage')->nullable();
            $table->timestampTz('evaluated_at');
            $table->unsignedInteger('candidate_count')->default(0);
            $table->unsignedInteger('evidence_count')->default(0);
            $table->json('comparison_state')->nullable();
            $table->json('safe_metadata')->nullable();
            $table->string('renderer_version');
            $table->boolean('retryable')->default(false);
            $table->uuid('correlation_id')->index();
            $table->timestamps();
        });

        Schema::create('controlled_assistant_responses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('message_id')->unique()->constrained('messages')->cascadeOnDelete();
            $table->foreignId('generation_run_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('retrieval_outcome_snapshot_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('response_kind');
            $table->string('renderer_version');
            $table->text('rendered_text');
            $table->timestamps();
        });

        Schema::table('generated_answers', function (Blueprint $table): void {
            $table->foreignId('assistant_message_id')->nullable()->unique()->after('created_by_user_id')->constrained('messages')->restrictOnDelete();
            $table->foreignId('generation_run_id')->nullable()->unique()->after('assistant_message_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('generated_answers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('generation_run_id');
            $table->dropConstrainedForeignId('assistant_message_id');
        });
        Schema::dropIfExists('controlled_assistant_responses');
        Schema::dropIfExists('retrieval_outcome_snapshots');
        Schema::dropIfExists('contextualisation_result_snapshots');
        Schema::dropIfExists('generation_runs');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
