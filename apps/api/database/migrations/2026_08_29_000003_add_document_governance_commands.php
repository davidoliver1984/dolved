<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_governance_commands', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->enum('purpose', ['approve', 'withdraw', 'reschedule', 'correct_timestamps', 'applicability_successor']);
            $table->uuid('idempotency_key');
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('target_kind', ['document_version']);
            $table->unsignedBigInteger('target_document_id');
            $table->enum('target_state_at_creation', ['draft', 'approved', 'withdrawn']);
            $table->char('request_payload_digest', 64);
            $table->enum('status', ['processing', 'completed']);
            $table->unsignedBigInteger('result_document_id')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['workspace_id', 'purpose', 'idempotency_key'],
                'document_governance_commands_workspace_purpose_key_unique',
            );
            $table->foreign(['target_document_id', 'workspace_id'], 'document_governance_commands_target_workspace_foreign')
                ->references(['id', 'workspace_id'])
                ->on('documents')
                ->restrictOnDelete();
            $table->foreign(['result_document_id', 'workspace_id'], 'document_governance_commands_result_workspace_foreign')
                ->references(['id', 'workspace_id'])
                ->on('documents')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_governance_commands');
    }
};
