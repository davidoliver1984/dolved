<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generation_runs', function (Blueprint $table): void {
            $table->string('delivery_mode')->nullable()->after('generation_fingerprint');
            $table->string('generation_endpoint')->nullable()->after('delivery_mode');
        });
        Schema::create('chat_delivery_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('generation_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->string('event_type');
            $table->boolean('provisional')->default(false);
            $table->json('safe_payload');
            $table->timestampTz('expires_at');
            $table->timestamps();
            $table->unique(['generation_run_id', 'sequence']);
            $table->index(['workspace_id', 'generation_run_id', 'expires_at'], 'chat_delivery_events_replay_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_delivery_events');
        Schema::table('generation_runs', function (Blueprint $table): void {
            $table->dropColumn(['delivery_mode', 'generation_endpoint']);
        });
    }
};
