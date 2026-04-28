<?php
/**
 * EN: Handles application behavior in `database/migrations/2025_02_17_000009_create_ledger_accounts_table.php`.
 * AR: يدير سلوك جزء من التطبيق في `database/migrations/2025_02_17_000009_create_ledger_accounts_table.php`.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 20);
            $table->string('name', 255);
            $table->char('type', 20);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('ledger_accounts')->nullOnDelete();
            $table->unique(['agency_id', 'code']);
            $table->index(['agency_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_accounts');
    }
};
