<?php
/**
 * EN: Handles application behavior in `database/migrations/2025_02_17_000013_add_ledger_journals_reference_unique.php`.
 * AR: يدير سلوك جزء من التطبيق في `database/migrations/2025_02_17_000013_add_ledger_journals_reference_unique.php`.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_journals', function (Blueprint $table) {
            $table->unique(['reference_type', 'reference_id'], 'ledger_journals_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ledger_journals', function (Blueprint $table) {
            $table->dropUnique('ledger_journals_reference_unique');
        });
    }
};
