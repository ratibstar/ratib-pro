<?php
/**
 * EN: Handles application behavior in `database/migrations/2025_02_17_000010_create_ledger_journals_table.php`.
 * AR: يدير سلوك جزء من التطبيق في `database/migrations/2025_02_17_000010_create_ledger_journals_table.php`.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('description', 500)->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->char('currency_code', 3);
            $table->timestamp('posted_at');
            $table->timestamps();
        });

        Schema::table('ledger_journals', function (Blueprint $table) {
            $table->index(['agency_id', 'posted_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_journals');
    }
};
