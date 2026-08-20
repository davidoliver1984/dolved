<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->after('id');
            $table->string('platform_role')->nullable()->after('remember_token');
            $table->timestampTz('disabled_at')->nullable()->after('platform_role');
        });

        DB::table('users')->orderBy('id')->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update([
                'public_id' => (string) Str::uuid(),
            ]);
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('users', function (Blueprint $table): void {
                $table->uuid('public_id')->nullable(false)->change();
            });
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->unique('public_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_platform_role_check CHECK (platform_role IS NULL OR platform_role = 'ADMINISTRATOR')");
        }

        Schema::create('platform_administration_commands', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->uuid('idempotency_key')->unique();
            $table->string('command_type');
            $table->string('request_digest', 64);
            $table->string('actor_kind');
            $table->string('actor_identifier');
            $table->uuid('target_user_public_id');
            $table->uuid('correlation_id');
            $table->json('result')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_administration_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('actor_kind');
            $table->string('actor_identifier');
            $table->string('action');
            $table->string('target_type');
            $table->uuid('target_public_id');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->uuid('correlation_id');
            $table->timestampTz('occurred_at');
            $table->timestamps();
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_administration_audit_events');
        Schema::dropIfExists('platform_administration_commands');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_platform_role_check');
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn(['public_id', 'platform_role', 'disabled_at']);
        });
    }
};
