<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AuditEventWriteRepositoryInterface;
use Rateb\PlatformCatalog\Support\Request;
use Rateb\PlatformCatalog\Support\Uuid;

final class MysqlAuditEventWriteRepository extends BaseRepository implements AuditEventWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'audit_events';
    }

    public function append(
        string $entityType,
        string $entityUuid,
        ?int $entityVersion,
        string $action,
        ?int $actorId,
        string $actorType = 'platform_user',
        ?array $before = null,
        ?array $after = null,
        ?string $ipAddress = null
    ): string {
        $uuid = Uuid::v4();
        $ip = $ipAddress ?? Request::header('X-Forwarded-For') ?? ($_SERVER['REMOTE_ADDR'] ?? null);

        $this->writePdo->prepare(
            'INSERT INTO audit_events
             (event_uuid, entity_type, entity_uuid, entity_version, action, actor_id, actor_type, before_json, after_json, ip_address)
             VALUES (:event_uuid, :entity_type, :entity_uuid, :entity_version, :action, :actor_id, :actor_type, :before_json, :after_json, :ip_address)'
        )->execute([
            'event_uuid' => $uuid,
            'entity_type' => $entityType,
            'entity_uuid' => $entityUuid,
            'entity_version' => $entityVersion,
            'action' => $action,
            'actor_id' => $actorId,
            'actor_type' => $actorType,
            'before_json' => $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            'after_json' => $after !== null ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => $ip,
        ]);

        return $uuid;
    }
}
