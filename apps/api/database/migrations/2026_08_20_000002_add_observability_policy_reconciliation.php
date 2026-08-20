<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observability_policy_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('environment');
            $table->unsignedInteger('version');
            $table->string('manifest_version');
            $table->string('manifest_digest', 64);
            $table->json('desired_values');
            $table->foreignId('requested_by_user_id')->constrained('users');
            $table->uuid('correlation_id');
            $table->timestamps();
            $table->unique(['environment', 'version']);
        });

        Schema::create('observability_policy_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('policy_version_id')->constrained('observability_policy_versions');
            $table->string('setting_key');
            $table->string('target');
            $table->json('desired_value');
            $table->uuid('plan_id');
            $table->string('expected_digest', 64);
            $table->uuid('current_attempt_id')->nullable();
            $table->string('status')->default('PENDING');
            $table->json('observed_value')->nullable();
            $table->timestampTz('reconciled_at')->nullable();
            $table->timestamps();
            $table->unique(['policy_version_id', 'setting_key', 'target'], 'observability_policy_target_identity');
        });

        Schema::create('observability_deployment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('deployment_attempt_id')->unique();
            $table->foreignId('policy_target_id')->constrained('observability_policy_targets');
            $table->string('plan_digest', 64);
            $table->string('payload_digest', 64);
            $table->json('observed_value')->nullable();
            $table->string('outcome');
            $table->string('failure_category')->nullable();
            $table->uuid('correlation_id');
            $table->timestampTz('applied_at');
            $table->boolean('derived_state_applied')->default(false);
            $table->timestamps();
        });

        Schema::create('observability_reconciliation_nonces', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->timestampTz('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observability_reconciliation_nonces');
        Schema::dropIfExists('observability_deployment_attempts');
        Schema::dropIfExists('observability_policy_targets');
        Schema::dropIfExists('observability_policy_versions');
    }
};
