<?php
/**
 * Government Labor Monitoring tables (tenant DB with workers).
 */
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $sql = file_get_contents(__DIR__ . '/../government_labor_module.sql');
        if ($sql !== false) {
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                if ($stmt !== '' && stripos($stmt, 'CREATE TABLE') !== false) {
                    DB::unprepared($stmt);
                }
            }
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS gov_worker_tracking');
        DB::unprepared('DROP TABLE IF EXISTS gov_violations');
        DB::unprepared('DROP TABLE IF EXISTS gov_blacklist');
        DB::unprepared('DROP TABLE IF EXISTS gov_inspections');
    }
};
