<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

use Rateb\PlatformCatalog\Support\Uuid;

final class M013PlatformRolesUuid extends AbstractMigration
{
    public function name(): string
    {
        return '013_platform_roles_uuid';
    }

    public function up(): void
    {
        $this->exec('ALTER TABLE platform_roles ADD COLUMN uuid CHAR(36) NULL AFTER id');

        $stmt = $this->pdo->query('SELECT id, code FROM platform_roles WHERE deleted_at IS NULL');
        if ($stmt !== false) {
            $update = $this->pdo->prepare('UPDATE platform_roles SET uuid = :uuid WHERE id = :id');
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                if (!is_array($row)) {
                    continue;
                }
                $update->execute([
                    'uuid' => Uuid::v4(),
                    'id' => (int) $row['id'],
                ]);
            }
        }

        $this->exec('ALTER TABLE platform_roles MODIFY uuid CHAR(36) NOT NULL');
        $this->exec('CREATE UNIQUE INDEX uk_platform_roles_uuid ON platform_roles (uuid)');
    }

    public function down(): void
    {
        $this->exec('DROP INDEX uk_platform_roles_uuid ON platform_roles');
        $this->exec('ALTER TABLE platform_roles DROP COLUMN uuid');
    }
}
