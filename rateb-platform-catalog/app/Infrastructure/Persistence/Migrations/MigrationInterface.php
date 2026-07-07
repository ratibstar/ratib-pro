<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

interface MigrationInterface
{
    public function name(): string;

    public function up(): void;

    public function down(): void;
}
