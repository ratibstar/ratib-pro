<?php
/**
 * EN: Handles application behavior in `database/migrations/2025_02_17_000012_create_settlements_table.php`.
 * AR: يدير سلوك جزء من التطبيق في `database/migrations/2025_02_17_000012_create_settlements_table.php`.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->char('currency_code', 3);
            $table->string('status', 20);
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('settlements', function (Blueprint $table) {
            $table->index(['agency_id', 'status']);
            $table->index(['agency_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
