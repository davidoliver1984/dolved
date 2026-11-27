<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answer_feedback', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('generation_run_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('assistant_message_id')->unique()->constrained('messages')->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('rating', 24);
            $table->string('reason_code', 40)->nullable();
            $table->text('comment')->nullable();
            $table->timestampTz('submitted_at');
            $table->timestamps();
            $table->index(['workspace_id', 'rating', 'submitted_at']);
        });

        Schema::create('answer_feedback_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('generation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('answer_feedback_id')->nullable()->constrained('answer_feedback')->nullOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('event_type', 32);
            $table->json('safe_metadata');
            $table->timestampTz('occurred_at');
            $table->timestamps();
            $table->index(['workspace_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answer_feedback_audit_events');
        Schema::dropIfExists('answer_feedback');
    }
};
