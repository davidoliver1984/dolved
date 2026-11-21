<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_question_review_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('policy_version', 64)->default('question-review-v1');
            $table->timestampTz('effective_from')->nullable();
            $table->timestampTz('review_from')->nullable();
            $table->unsignedTinyInteger('retrospective_days')->default(0);
            $table->foreignId('enabled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('disabled_at')->nullable();
            $table->foreignId('disabled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('workspace_question_review_grants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('capability', 32);
            $table->string('reason', 255);
            $table->foreignId('granted_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['workspace_id', 'user_id', 'capability'], 'question_review_grants_lookup_index');
        });

        Schema::create('question_review_subjects', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('status', 32)->default('active');
            $table->string('priority', 16)->default('normal');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['workspace_id', 'name'], 'question_review_subjects_workspace_name_unique');
        });

        Schema::create('question_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generation_run_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('review_status', 32)->default('unreviewed');
            $table->string('issue_type', 40)->nullable();
            $table->string('workflow_state', 32)->default('needs_review');
            $table->string('priority', 16)->default('normal');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->string('linked_document_public_id', 64)->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'workflow_state', 'updated_at']);
            $table->index(['workspace_id', 'review_status', 'updated_at']);
        });

        Schema::create('question_review_subject_assignments', function (Blueprint $table): void {
            $table->foreignId('question_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_review_subject_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['question_review_id', 'question_review_subject_id'], 'question_review_subject_assignment_primary');
        });

        Schema::create('question_review_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('generation_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('question_review_id')->nullable()->constrained('question_reviews')->nullOnDelete();
            $table->string('event_type', 48);
            $table->string('purpose', 255)->nullable();
            $table->json('safe_metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestamps();
            $table->index(['workspace_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_review_audit_events');
        Schema::dropIfExists('question_review_subject_assignments');
        Schema::dropIfExists('question_reviews');
        Schema::dropIfExists('question_review_subjects');
        Schema::dropIfExists('workspace_question_review_grants');
        Schema::dropIfExists('workspace_question_review_policies');
    }
};
