<?php
/**
 * EN: Handles application behavior in `database/migrations/2025_02_17_000001_create_countries_table.php`.
 * AR: يدير سلوك جزء من التطبيق في `database/migrations/2025_02_17_000001_create_countries_table.php`.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->char('code', 2)->unique();
            $table->string('name', 100);
            $table->char('currency_code', 3);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('countries', function (Blueprint $table) {
            $table->index('currency_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
