<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_branding_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->restrictOnDelete();
            $table->string('mode', 16)->default('default');
            $table->unsignedInteger('draft_version')->default(0);
            $table->json('draft_configuration')->nullable();
            $table->unsignedBigInteger('applied_revision_id')->nullable();
            $table->unsignedBigInteger('last_custom_revision_id')->nullable();
            $table->timestamps();
        });

        Schema::create('workspace_branding_assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->string('kind', 32);
            $table->string('storage_path');
            $table->string('media_type', 64);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->char('checksum_sha256', 64);
            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['workspace_id', 'kind']);
        });

        Schema::create('workspace_branding_revisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->json('configuration');
            $table->foreignId('applied_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['workspace_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_branding_revisions');
        Schema::dropIfExists('workspace_branding_assets');
        Schema::dropIfExists('workspace_branding_profiles');
    }
};
