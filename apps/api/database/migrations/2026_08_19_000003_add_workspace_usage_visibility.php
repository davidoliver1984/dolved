<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_activity_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->string('event_kind', 80);
            $table->uuid('source_public_id');
            $table->string('outcome', 80)->nullable();
            $table->timestampTz('occurred_at');
            $table->timestamps();
            $table->unique(['workspace_id', 'event_kind', 'source_public_id'], 'workspace_activity_identity_unique');
            $table->index(['workspace_id', 'occurred_at'], 'workspace_activity_interval_index');
        });

        Schema::create('workspace_usage_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->string('scope_type', 40);
            $table->uuid('scope_public_id');
            $table->string('operation_kind', 80);
            $table->unsignedSmallInteger('ordinal')->default(0);
            $table->string('provider', 120);
            $table->string('model', 255);
            $table->string('execution', 40);
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedInteger('retry_count')->default(0);
            $table->unsignedBigInteger('input_tokens')->nullable();
            $table->unsignedBigInteger('cached_input_tokens')->nullable();
            $table->unsignedBigInteger('output_tokens')->nullable();
            $table->decimal('latency_ms', 16, 3)->nullable();
            $table->decimal('cost_usd', 18, 8)->nullable();
            $table->string('cost_basis', 40);
            $table->string('pricing_snapshot', 255)->nullable();
            $table->timestampTz('occurred_at');
            $table->timestamps();
            $table->unique(
                ['workspace_id', 'scope_type', 'scope_public_id', 'operation_kind', 'ordinal'],
                'workspace_usage_identity_unique',
            );
            $table->index(['workspace_id', 'occurred_at'], 'workspace_usage_interval_index');
            $table->index(['workspace_id', 'operation_kind', 'occurred_at'], 'workspace_usage_operation_interval_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_usage_events');
        Schema::dropIfExists('workspace_activity_events');
    }
};
