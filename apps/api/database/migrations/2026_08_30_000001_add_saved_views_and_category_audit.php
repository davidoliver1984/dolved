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
        Schema::create('saved_views', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('normalised_name', 100);
            $table->unsignedSmallInteger('definition_schema_version')->default(1);
            $table->json('definition');
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id', 'normalised_name'], 'saved_views_owner_name_unique');
            $table->index(['workspace_id', 'user_id']);
        });

        Schema::create('library_settings_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('target_kind', 24);
            $table->uuid('target_public_id');
            $table->string('action', 48);
            $table->json('previous_values');
            $table->json('new_values');
            $table->timestampTz('occurred_at');
            $table->timestamps();
            $table->index(['workspace_id', 'occurred_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE saved_views ADD CONSTRAINT saved_views_schema_version_check CHECK (definition_schema_version > 0)');
            DB::statement("ALTER TABLE library_settings_audit_events ADD CONSTRAINT library_settings_target_kind_check CHECK (target_kind IN ('saved_view', 'document_category'))");
            DB::statement("ALTER TABLE library_settings_audit_events ADD CONSTRAINT library_settings_action_check CHECK (action IN ('saved_view_created', 'saved_view_renamed', 'saved_view_deleted', 'document_category_created', 'document_category_renamed', 'document_category_archived'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_settings_audit_events');
        Schema::dropIfExists('saved_views');
    }
};
