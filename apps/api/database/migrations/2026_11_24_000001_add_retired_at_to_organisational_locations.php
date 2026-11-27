<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisational_locations', function (Blueprint $table): void {
            $table->timestampTz('retired_at')->nullable()->after('reference_descriptor');
            $table->index(['workspace_id', 'retired_at'], 'organisational_locations_workspace_retired_index');
        });
    }

    public function down(): void
    {
        Schema::table('organisational_locations', function (Blueprint $table): void {
            $table->dropIndex('organisational_locations_workspace_retired_index');
            $table->dropColumn('retired_at');
        });
    }
};
