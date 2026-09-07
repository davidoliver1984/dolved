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
            $table->string('reference_descriptor')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organisational_locations', function (Blueprint $table): void {
            $table->dropColumn('reference_descriptor');
        });
    }
};
