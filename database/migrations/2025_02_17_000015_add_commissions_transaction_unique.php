<?php
/**
 * EN: Handles application behavior in `database/migrations/2025_02_17_000015_add_commissions_transaction_unique.php`.
 * AR: يدير سلوك جزء من التطبيق في `database/migrations/2025_02_17_000015_add_commissions_transaction_unique.php`.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->unique('transaction_id', 'commissions_transaction_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropUnique('commissions_transaction_id_unique');
        });
    }
};
