<?php
/**
 * EN: Handles application behavior in `database/migrations/2025_02_17_000006_create_wallets_table.php`.
 * AR: يدير سلوك جزء من التطبيق في `database/migrations/2025_02_17_000006_create_wallets_table.php`.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('holder_type', 50);
            $table->unsignedBigInteger('holder_id');
            $table->decimal('balance', 15, 2)->default(0);
            $table->char('currency_code', 3);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->unique(['agency_id', 'holder_type', 'holder_id', 'currency_code']);
            $table->index(['agency_id', 'holder_type']);
            $table->index(['holder_type', 'holder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
