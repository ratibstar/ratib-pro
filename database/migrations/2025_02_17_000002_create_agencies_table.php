<?php
/**
 * EN: Handles application behavior in `database/migrations/2025_02_17_000002_create_agencies_table.php`.
 * AR: يدير سلوك جزء من التطبيق في `database/migrations/2025_02_17_000002_create_agencies_table.php`.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('code', 50);
            $table->char('currency_code', 3);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('agencies', function (Blueprint $table) {
            $table->unique(['country_id', 'code']);
            $table->index('currency_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agencies');
    }
};
