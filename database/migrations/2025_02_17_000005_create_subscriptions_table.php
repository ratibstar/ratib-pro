<?php
/**
 * EN: Handles application behavior in `database/migrations/2025_02_17_000005_create_subscriptions_table.php`.
 * AR: يدير سلوك جزء من التطبيق في `database/migrations/2025_02_17_000005_create_subscriptions_table.php`.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->char('currency_code', 3);
            $table->decimal('amount', 15, 2);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['customer_id', 'status']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
