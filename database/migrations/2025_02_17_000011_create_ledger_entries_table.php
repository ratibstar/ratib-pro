<?php
/**
 * EN: Handles application behavior in `database/migrations/2025_02_17_000011_create_ledger_entries_table.php`.
 * AR: يدير سلوك جزء من التطبيق في `database/migrations/2025_02_17_000011_create_ledger_entries_table.php`.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained()->cascadeOnDelete();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->char('currency_code', 3);
            $table->string('description', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->index(['ledger_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
