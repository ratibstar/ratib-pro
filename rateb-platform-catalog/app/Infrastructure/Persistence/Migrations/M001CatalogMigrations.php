<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

final class M001CatalogMigrations extends AbstractMigration
{
    public function name(): string
    {
        return '001_catalog_migrations';
    }

    public function up(): void
    {
        $this->exec(
            'CREATE TABLE IF NOT EXISTS catalog_migrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL,
                applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                UNIQUE KEY uk_catalog_migrations_filename (filename)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->exec('DROP TABLE IF EXISTS catalog_migrations');
    }
}
