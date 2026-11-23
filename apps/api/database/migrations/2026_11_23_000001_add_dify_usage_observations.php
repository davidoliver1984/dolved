<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dify_document_bindings', function (Blueprint $table): void {
            $table->unsignedInteger('indexed_segment_count')->nullable()->after('failure_code');
            $table->string('segment_count_status', 32)->default('unavailable')->after('indexed_segment_count');
            $table->timestampTz('segment_count_observed_at')->nullable()->after('segment_count_status');
        });
    }

    public function down(): void
    {
        Schema::table('dify_document_bindings', function (Blueprint $table): void {
            $table->dropColumn(['indexed_segment_count', 'segment_count_status', 'segment_count_observed_at']);
        });
    }
};
