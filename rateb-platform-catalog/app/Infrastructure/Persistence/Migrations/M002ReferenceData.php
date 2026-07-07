<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

final class M002ReferenceData extends AbstractMigration
{
    public function name(): string
    {
        return '002_reference_data';
    }

    public function up(): void
    {
        $sqlFile = (defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT : dirname(__DIR__, 4))
            . '/migrations/002_reference_data.sql';

        if (!is_file($sqlFile)) {
            throw new \RuntimeException('Reference data SQL file not found: ' . $sqlFile);
        }

        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            throw new \RuntimeException('Unable to read reference data SQL file.');
        }

        $this->exec(self::normalizeSql($sql));
    }

    public function down(): void
    {
        $this->exec('DROP TABLE IF EXISTS unit_translations');
        $this->exec('DROP TABLE IF EXISTS units');
        $this->exec('DROP TABLE IF EXISTS currencies');
        $this->exec('DROP TABLE IF EXISTS languages');
    }
}
