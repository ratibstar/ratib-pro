<?php
/**
 * EN: Handles application behavior in `database/migrations/2025_02_17_000016_add_transactions_external_reference_unique.php`.
 * AR: يدير سلوك جزء من التطبيق في `database/migrations/2025_02_17_000016_add_transactions_external_reference_unique.php`.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unique('external_reference', 'transactions_external_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_external_reference_unique');
        });
    }
};
