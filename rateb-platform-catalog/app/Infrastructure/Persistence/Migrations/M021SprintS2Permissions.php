<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

final class M021SprintS2Permissions extends AbstractMigration
{
    /** @var list<string> */
    private const S2_PERMISSION_SLUGS = [
        'catalog.import.export',
        'catalog.import.csv',
        'catalog.import.excel',
        'catalog.import.ftp',
        'catalog.import.history',
        'catalog.sync.view',
        'catalog.collections.manage',
        'catalog.channels.manage',
        'catalog.duplicates.view',
        'catalog.duplicates.resolve',
        'catalog.duplicate_rules.manage',
        'catalog.saved_filters.manage',
        'catalog.webhooks.manage',
        'catalog.pricing.manage',
        'catalog.bulk.manage',
    ];

    public function name(): string
    {
        return '021_sprint_s2_permissions';
    }

    public function up(): void
    {
        $registry = require dirname(__DIR__, 4) . '/config/entity-permissions.php';

        foreach (self::S2_PERMISSION_SLUGS as $slug) {
            $meta = $registry[$slug] ?? ['module' => 'catalog', 'description' => $slug];
            $this->exec(
                'INSERT IGNORE INTO platform_permissions (slug, description, module) VALUES ('
                . $this->pdo->quote($slug) . ', '
                . $this->pdo->quote((string) ($meta['description'] ?? $slug)) . ', '
                . $this->pdo->quote((string) ($meta['module'] ?? 'catalog')) . ')'
            );
        }

        $slugList = implode(', ', array_map(
            fn (string $slug): string => $this->pdo->quote($slug),
            self::S2_PERMISSION_SLUGS
        ));

        $this->exec(
            'INSERT IGNORE INTO platform_role_permissions (role_id, permission_id)
             SELECT r.id, p.id
             FROM platform_roles r
             INNER JOIN platform_permissions p ON p.slug IN (' . $slugList . ')
             WHERE r.code = "super_admin" AND p.deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        $slugList = implode(', ', array_map(
            fn (string $slug): string => $this->pdo->quote($slug),
            self::S2_PERMISSION_SLUGS
        ));

        $this->exec(
            'DELETE rp FROM platform_role_permissions rp
             INNER JOIN platform_permissions p ON p.id = rp.permission_id
             WHERE p.slug IN (' . $slugList . ')'
        );

        $this->exec(
            'DELETE FROM platform_permissions WHERE slug IN (' . $slugList . ')'
        );
    }
}
