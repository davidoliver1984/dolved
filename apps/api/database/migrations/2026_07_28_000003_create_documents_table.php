<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('created_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->enum('status', [
                'uploading',
                'uploaded',
                'queued',
                'processing',
                'indexed',
                'failed',
                'deleting',
                'deleted',
            ])->default('uploading');
            $table->string('source_filename');
            $table->string('media_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('storage_key')->unique();
            $table->string('failure_category')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index('created_by_user_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE documents
                ADD CONSTRAINT documents_size_bytes_non_negative
                CHECK (size_bytes >= 0)'
            );

            DB::statement(
                "ALTER TABLE documents
                ADD CONSTRAINT documents_failed_requires_details
                CHECK (
                    status <> 'failed'
                    OR (
                        NULLIF(BTRIM(failure_category), '') IS NOT NULL
                        AND NULLIF(BTRIM(failure_message), '') IS NOT NULL
                    )
                )"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
