<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_family_activity_summary', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('family_id')->unique()->constrained('document_families')->cascadeOnDelete();
            $table->timestampTz('last_meaningful_update');
            $table->timestampsTz();
            $table->index(['last_meaningful_update', 'family_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_family_activity_summary');
    }
};
