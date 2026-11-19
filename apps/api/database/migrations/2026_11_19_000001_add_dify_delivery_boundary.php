<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->foreignId('selected_location_id')->nullable()->after('created_by_user_id')
                ->constrained('organisational_locations')->restrictOnDelete();
            $table->index(['workspace_id', 'created_by_user_id', 'updated_at'], 'conversations_owner_updated_index');
        });

        Schema::create('dify_workspace_bindings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->restrictOnDelete();
            $table->uuid('app_id');
            $table->uuid('dataset_id');
            $table->uuid('scope_metadata_id');
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('dify_conversation_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('conversation_id')->unique()->constrained()->cascadeOnDelete();
            $table->uuid('remote_conversation_id')->unique();
            $table->timestamps();
            $table->unique(['workspace_id', 'remote_conversation_id'], 'dify_session_workspace_remote_unique');
        });

        Schema::create('dify_document_bindings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->uuid('dataset_id');
            $table->string('scope_key', 64);
            $table->uuid('remote_document_id')->nullable()->unique();
            $table->uuid('remote_batch_id')->nullable();
            $table->string('remote_name', 255);
            $table->string('sync_status', 32);
            $table->char('source_checksum_sha256', 64);
            $table->text('failure_code')->nullable();
            $table->timestampTz('synced_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'sync_status']);
            $table->unique(['document_id', 'scope_key'], 'dify_document_scope_unique');
        });

        Schema::table('generation_runs', function (Blueprint $table): void {
            $table->uuid('provider_message_id')->nullable()->unique()->after('generation_fingerprint');
            $table->uuid('provider_task_id')->nullable()->after('provider_message_id');
            $table->string('provider_dispatch_state', 32)->nullable()->after('provider_task_id');
        });

        Schema::create('retrieved_source_references', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('generated_answer_id');
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('dataset_id')->nullable();
            $table->uuid('remote_document_id')->nullable();
            $table->uuid('remote_segment_id')->nullable();
            $table->unsignedInteger('rank');
            $table->decimal('score', 16, 12)->nullable();
            $table->string('display_name', 255);
            $table->text('retrieved_text');
            $table->char('content_digest', 64);
            $table->json('safe_metadata')->nullable();
            $table->timestamps();
            $table->foreign(['generated_answer_id', 'workspace_id'], 'retrieved_sources_answer_workspace_foreign')
                ->references(['id', 'workspace_id'])->on('generated_answers')->cascadeOnDelete();
            $table->unique(['generated_answer_id', 'rank'], 'retrieved_sources_answer_rank_unique');
            $table->index(['workspace_id', 'remote_document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retrieved_source_references');
        Schema::table('generation_runs', function (Blueprint $table): void {
            $table->dropColumn(['provider_message_id', 'provider_task_id', 'provider_dispatch_state']);
        });
        Schema::dropIfExists('dify_conversation_sessions');
        Schema::dropIfExists('dify_document_bindings');
        Schema::dropIfExists('dify_workspace_bindings');
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex('conversations_owner_updated_index');
            $table->dropConstrainedForeignId('selected_location_id');
        });
    }
};
