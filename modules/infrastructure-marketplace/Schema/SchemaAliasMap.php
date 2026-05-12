<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Schema;

/**
 * Resolves legacy / documentation naming drift without renaming live tables.
 */
final class SchemaAliasMap
{
    /**
     * @var array<string, string> alias (singular / doc typo) => canonical table name
     */
    private const CANONICAL = [
        'ratib_infra_catalog_item' => 'ratib_infra_catalog_items',
        'ratib_infra_catalog_items' => 'ratib_infra_catalog_items',
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
        if ($referencedAs === 'ratib_infra_catalog_item') {
            $warnings[] = 'Documentation or legacy scripts may reference ratib_infra_catalog_item (singular); runtime code uses ratib_infra_catalog_items.';
        }
        return $warnings;
    }
}
