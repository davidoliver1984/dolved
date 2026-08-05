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
        Schema::create('embedding_space_generations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('embedding_profile_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('collection_name')->unique();
            $table->string('vector_name')->default('dense');
            $table->unsignedInteger('dimensions');
            $table->enum('distance', ['cosine'])->default('cosine');
            $table->enum('status', [
                'building',
                'verifying',
                'available',
                'retiring',
                'retired',
            ])->default('building');
            $table->timestamp('available_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->index(['embedding_profile_id', 'status'], 'embedding_spaces_profile_status_index');
            $table->index('status');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE embedding_space_generations
                ADD CONSTRAINT embedding_space_generations_dimensions_positive
                CHECK (dimensions > 0)'
            );
            DB::statement(
                "ALTER TABLE embedding_space_generations
                ADD CONSTRAINT embedding_space_generations_lifecycle_timestamps
                CHECK (
                    (status IN ('building', 'verifying') AND available_at IS NULL AND retired_at IS NULL)
                    OR (status IN ('available', 'retiring') AND available_at IS NOT NULL AND retired_at IS NULL)
                    OR (status = 'retired' AND retired_at IS NOT NULL)
                )"
            );
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION enforce_embedding_space_profile_dimensions()
                RETURNS trigger AS $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1
                        FROM embedding_profiles
                        WHERE id = NEW.embedding_profile_id
                          AND dimensions = NEW.dimensions
                    ) THEN
                        RAISE EXCEPTION 'embedding-space dimensions must match its profile';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER embedding_space_profile_dimensions
                BEFORE INSERT OR UPDATE OF embedding_profile_id, dimensions
                ON embedding_space_generations
                FOR EACH ROW
                EXECUTE FUNCTION enforce_embedding_space_profile_dimensions();
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS embedding_space_profile_dimensions
                ON embedding_space_generations;
                DROP FUNCTION IF EXISTS enforce_embedding_space_profile_dimensions();
                SQL);
        }

        Schema::dropIfExists('embedding_space_generations');
    }
};
