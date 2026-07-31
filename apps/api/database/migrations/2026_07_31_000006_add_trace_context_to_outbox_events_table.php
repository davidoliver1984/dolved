<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbox_events', function (Blueprint $table): void {
            $table->string('traceparent', 55)->nullable()->after('correlation_id');
            $table->string('tracestate', 512)->nullable()->after('traceparent');
        });
    }

    public function down(): void
    {
        Schema::table('outbox_events', function (Blueprint $table): void {
            $table->dropColumn(['traceparent', 'tracestate']);
        });
    }
};
