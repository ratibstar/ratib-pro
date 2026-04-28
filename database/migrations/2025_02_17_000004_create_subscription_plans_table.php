<?php
/**
 * EN: Handles application behavior in `database/migrations/2025_02_17_000004_create_subscription_plans_table.php`.
 * AR: يدير سلوك جزء من التطبيق في `database/migrations/2025_02_17_000004_create_subscription_plans_table.php`.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->char('interval', 20);
            $table->decimal('amount', 15, 2);
            $table->char('currency_code', 3);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->index(['currency_code', 'interval']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
