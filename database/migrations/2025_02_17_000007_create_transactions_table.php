<?php
/**
 * EN: Handles application behavior in `database/migrations/2025_02_17_000007_create_transactions_table.php`.
 * AR: يدير سلوك جزء من التطبيق في `database/migrations/2025_02_17_000007_create_transactions_table.php`.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->char('type', 20);
            $table->decimal('amount', 15, 2);
            $table->char('currency_code', 3);
            $table->string('reference', 255)->nullable();
            $table->string('external_reference', 255)->nullable();
            $table->string('status', 20);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['customer_id', 'created_at']);
            $table->index(['wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
