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
        Schema::create('sparse_embedding_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->char('fingerprint', 64)->unique();
            $table->string('provider');
            $table->string('model');
            $table->string('tokenizer');
            $table->string('tokenizer_revision')->nullable();
            $table->string('output_representation');
            $table->unsignedInteger('max_input_tokens');
            $table->string('document_input_type');
            $table->string('query_input_type');
            $table->string('model_revision')->nullable();
            $table->string('adapter_version');
            $table->timestamps();

            $table->index(['provider', 'model']);
        });

        Schema::create('sparse_space_generations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('sparse_embedding_profile_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('embedding_space_generation_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('vector_name')->default('sparse');
            $table->enum('status', [
                'building',
                'verifying',
                'available',
                'retiring',
                'retired',
            ])->default('building');
            $table->timestampTz('available_at')->nullable();
            $table->timestampTz('retired_at')->nullable();
            $table->timestamps();

            $table->index(
                ['embedding_space_generation_id', 'status'],
                'sparse_spaces_embedding_status_index',
            );
            $table->index(
                ['sparse_embedding_profile_id', 'status'],
                'sparse_spaces_profile_status_index',
            );
            $table->unique(
                ['sparse_embedding_profile_id', 'embedding_space_generation_id', 'vector_name'],
                'sparse_spaces_profile_embedding_vector_unique',
            );
        });

        Schema::table('workspace_corpus_generations', function (Blueprint $table): void {
            $table->unsignedBigInteger('rebuilt_from_generation_id')->nullable();
            $table->uuid('rebuild_event_id')->nullable()->unique();
            $table->unsignedBigInteger('sparse_space_generation_id')
                ->nullable()
                ->after('embedding_space_generation_id');
            $table->unsignedInteger('expected_point_count')->nullable();
            $table->char('point_manifest_digest', 64)->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->unique(
                ['workspace_id', 'rebuilt_from_generation_id', 'sparse_space_generation_id'],
                'corpus_rebuild_target_unique',
            );
        });

        Schema::table('ingestion_event_claims', function (Blueprint $table): void {
            $table->foreignId('sparse_space_generation_id')
                ->nullable()
                ->after('embedding_space_generation_id')
                ->constrained()
                ->restrictOnDelete();
            $table->char('sparse_profile_fingerprint', 64)->nullable();
        });

        Schema::create('evidence_threshold_policies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('version')->unique();
            $table->char('fingerprint', 64)->unique();
            $table->enum('status', ['calibrating', 'active', 'retired'])
                ->default('calibrating');
            $table->string('reranker_provider');
            $table->string('reranker_model');
            $table->string('reranker_adapter_version');
            $table->char('embedding_profile_fingerprint', 64);
            $table->char('sparse_profile_fingerprint', 64);
            $table->string('fusion_strategy');
            $table->string('fusion_version');
            $table->unsignedInteger('rrf_k');
            $table->unsignedInteger('dense_candidate_k');
            $table->unsignedInteger('sparse_candidate_k');
            $table->unsignedInteger('fusion_candidate_k');
            $table->unsignedInteger('reranker_candidate_k');
            $table->decimal('evidence_threshold', 12, 10);
            $table->unsignedInteger('final_evidence_k');
            $table->string('calibration_corpus_version');
            $table->char('calibration_corpus_digest', 64);
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('retired_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'reranker_provider', 'reranker_model'], 'evidence_policies_status_reranker_index');
        });

        Schema::create('workspace_corpus_generation_rollbacks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('demoted_generation_id')
                ->constrained('workspace_corpus_generations')
                ->restrictOnDelete();
            $table->foreignId('promoted_generation_id')
                ->constrained('workspace_corpus_generations')
                ->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500);
            $table->timestampTz('occurred_at');
            $table->timestamps();

            $table->index(['workspace_id', 'occurred_at'], 'corpus_rollbacks_workspace_occurred_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            Schema::table('workspace_corpus_generations', function (Blueprint $table): void {
                $table->foreign('rebuilt_from_generation_id')
                    ->references('id')
                    ->on('workspace_corpus_generations')
                    ->restrictOnDelete();
                $table->foreign('sparse_space_generation_id')
                    ->references('id')
                    ->on('sparse_space_generations')
                    ->restrictOnDelete();
            });
            DB::statement("ALTER TABLE sparse_embedding_profiles ADD CONSTRAINT sparse_profiles_fingerprint_sha256 CHECK (fingerprint ~ '^[0-9a-f]{64}$')");
            DB::statement('ALTER TABLE sparse_embedding_profiles ADD CONSTRAINT sparse_profiles_max_input_tokens_positive CHECK (max_input_tokens > 0)');
            DB::statement("ALTER TABLE sparse_space_generations ADD CONSTRAINT sparse_spaces_lifecycle_timestamps CHECK ((status IN ('building', 'verifying') AND available_at IS NULL AND retired_at IS NULL) OR (status IN ('available', 'retiring') AND available_at IS NOT NULL AND retired_at IS NULL) OR (status = 'retired' AND retired_at IS NOT NULL))");
            DB::statement("ALTER TABLE workspace_corpus_generations ADD CONSTRAINT hybrid_corpus_verification_evidence CHECK ((expected_point_count IS NULL AND point_manifest_digest IS NULL AND verified_at IS NULL) OR (expected_point_count IS NOT NULL AND point_manifest_digest ~ '^[0-9a-f]{64}$' AND verified_at IS NOT NULL))");
            DB::statement("ALTER TABLE evidence_threshold_policies ADD CONSTRAINT evidence_policies_fingerprints_sha256 CHECK (fingerprint ~ '^[0-9a-f]{64}$' AND embedding_profile_fingerprint ~ '^[0-9a-f]{64}$' AND sparse_profile_fingerprint ~ '^[0-9a-f]{64}$' AND calibration_corpus_digest ~ '^[0-9a-f]{64}$')");
            DB::statement('ALTER TABLE evidence_threshold_policies ADD CONSTRAINT evidence_policies_candidate_bounds CHECK (rrf_k > 0 AND dense_candidate_k > 0 AND sparse_candidate_k > 0 AND fusion_candidate_k > 0 AND fusion_candidate_k <= dense_candidate_k + sparse_candidate_k AND reranker_candidate_k > 0 AND reranker_candidate_k <= fusion_candidate_k AND final_evidence_k > 0 AND final_evidence_k <= reranker_candidate_k)');
            DB::statement('ALTER TABLE evidence_threshold_policies ADD CONSTRAINT evidence_policies_threshold_range CHECK (evidence_threshold >= 0 AND evidence_threshold <= 1)');
            DB::statement("ALTER TABLE evidence_threshold_policies ADD CONSTRAINT evidence_policies_lifecycle_timestamps CHECK ((status = 'calibrating' AND activated_at IS NULL AND retired_at IS NULL) OR (status = 'active' AND activated_at IS NOT NULL AND retired_at IS NULL) OR (status = 'retired' AND retired_at IS NOT NULL))");
            DB::statement("CREATE UNIQUE INDEX evidence_threshold_policies_one_active_per_vector_lineage ON evidence_threshold_policies (embedding_profile_fingerprint, sparse_profile_fingerprint) WHERE status = 'active'");
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION enforce_active_hybrid_corpus_sparse_space()
                RETURNS trigger AS $$
                BEGIN
                    IF NEW.status = 'active' AND NEW.sparse_space_generation_id IS NOT NULL
                       AND (NEW.verified_at IS NULL OR NOT EXISTS (
                           SELECT 1
                           FROM sparse_space_generations
                           WHERE id = NEW.sparse_space_generation_id
                             AND embedding_space_generation_id = NEW.embedding_space_generation_id
                             AND status = 'available'
                       )) THEN
                        RAISE EXCEPTION 'an active hybrid corpus generation requires a compatible available sparse space';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER active_hybrid_corpus_sparse_space
                BEFORE INSERT OR UPDATE OF status, embedding_space_generation_id, sparse_space_generation_id
                ON workspace_corpus_generations
                FOR EACH ROW
                EXECUTE FUNCTION enforce_active_hybrid_corpus_sparse_space();

                CREATE FUNCTION prevent_retiring_referenced_sparse_space()
                RETURNS trigger AS $$
                BEGIN
                    IF OLD.status = 'available' AND NEW.status <> 'available'
                       AND EXISTS (
                           SELECT 1
                           FROM workspace_corpus_generations
                           WHERE sparse_space_generation_id = NEW.id
                             AND status = 'active'
                       ) THEN
                        RAISE EXCEPTION 'a sparse space with active corpus generations must remain available';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER referenced_sparse_space_remains_available
                BEFORE UPDATE OF status
                ON sparse_space_generations
                FOR EACH ROW
                EXECUTE FUNCTION prevent_retiring_referenced_sparse_space();
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS evidence_threshold_policies_one_active_per_vector_lineage');
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS referenced_sparse_space_remains_available ON sparse_space_generations;
                DROP FUNCTION IF EXISTS prevent_retiring_referenced_sparse_space();
                DROP TRIGGER IF EXISTS active_hybrid_corpus_sparse_space ON workspace_corpus_generations;
                DROP FUNCTION IF EXISTS enforce_active_hybrid_corpus_sparse_space();
                SQL);
        }

        Schema::dropIfExists('workspace_corpus_generation_rollbacks');
        Schema::dropIfExists('evidence_threshold_policies');
        Schema::table('ingestion_event_claims', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sparse_space_generation_id');
            $table->dropColumn('sparse_profile_fingerprint');
        });
        Schema::table('workspace_corpus_generations', function (Blueprint $table): void {
            if (DB::getDriverName() === 'pgsql') {
                $table->dropForeign(['sparse_space_generation_id']);
                $table->dropForeign(['rebuilt_from_generation_id']);
            }
            $table->dropColumn('sparse_space_generation_id');
            $table->dropColumn('rebuilt_from_generation_id');
            $table->dropColumn('rebuild_event_id');
            $table->dropColumn(['expected_point_count', 'point_manifest_digest', 'verified_at']);
        });
        Schema::dropIfExists('sparse_space_generations');
        Schema::dropIfExists('sparse_embedding_profiles');
    }
};
