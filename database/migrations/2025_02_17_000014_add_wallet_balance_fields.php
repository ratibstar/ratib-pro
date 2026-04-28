<?php
/**
 * EN: Handles application behavior in `database/migrations/2025_02_17_000014_add_wallet_balance_fields.php`.
 * AR: يدير سلوك جزء من التطبيق في `database/migrations/2025_02_17_000014_add_wallet_balance_fields.php`.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('available_balance', 15, 2)->default(0);
            $table->decimal('pending_balance', 15, 2)->default(0);
            $table->decimal('total_earned', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn(['available_balance', 'pending_balance', 'total_earned', 'total_paid']);
        });
    }
};
