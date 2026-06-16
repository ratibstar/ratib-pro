<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Schema;

/**
 * Resolves legacy / documentation naming drift without renaming live tables.
 */
final class SchemaAliasMap
{
    /**
     * @var array<string, string> alias (singular / doc typo) => canonical table name
     */
    private const CANONICAL = [
        'rateb_infra_catalog_item' => 'rateb_infra_catalog_items',
        'rateb_infra_catalog_items' => 'rateb_infra_catalog_items',
    ];

    public static function canonicalTable(string $name): string
    {
        $t = trim($name);
        return self::CANONICAL[$t] ?? $t;
    }

    /**
     * @return list<string> human-readable warnings when alias used
     */
    public static function compatibilityWarningsFor(string $referencedAs): array
    {
        $warnings = [];
        if ($referencedAs === 'rateb_infra_catalog_item') {
            $warnings[] = 'Documentation or legacy scripts may reference rateb_infra_catalog_item (singular); runtime code uses rateb_infra_catalog_items.';
        }
        return $warnings;
    }
}
