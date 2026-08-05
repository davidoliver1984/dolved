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
        Schema::create('embedding_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->char('fingerprint', 64)->unique();
            $table->string('provider');
            $table->string('model');
            $table->unsignedInteger('dimensions');
            $table->string('output_dtype');
            $table->string('document_input_type');
            $table->string('query_input_type');
            $table->string('normalisation');
            $table->boolean('truncation');
            $table->string('model_revision')->nullable();
            $table->string('adapter_version');
            $table->timestamps();

            $table->index(['provider', 'model']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE embedding_profiles
                ADD CONSTRAINT embedding_profiles_dimensions_positive
                CHECK (dimensions > 0)'
            );
            DB::statement(
                "ALTER TABLE embedding_profiles
                ADD CONSTRAINT embedding_profiles_fingerprint_sha256
                CHECK (fingerprint ~ '^[0-9a-f]{64}$')"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('embedding_profiles');
    }
};
