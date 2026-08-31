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
        Schema::table('import_items', function (Blueprint $table): void {
            $table->string('source_filename')->nullable()->after('staged_object_key');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
CREATE FUNCTION guard_import_item_source_filename_update() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.source_filename IS DISTINCT FROM OLD.source_filename THEN
        RAISE EXCEPTION 'import source filename is immutable';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER import_items_source_filename_update_guard
BEFORE UPDATE ON import_items
FOR EACH ROW EXECUTE FUNCTION guard_import_item_source_filename_update();
SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS guard_import_item_source_filename_update() CASCADE');
        }
        Schema::table('import_items', function (Blueprint $table): void {
            $table->dropColumn('source_filename');
        });
    }
};
