<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ChannelReadRepositoryInterface;

final class MysqlChannelReadRepository extends BaseRepository implements ChannelReadRepositoryInterface
{
    protected function table(): string
    {
        return 'channels';
    }

    public function list(LocaleContext $locale, int $limit, int $offset): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        $sql = 'SELECT ch.uuid, ch.code, ch.status, ch.created_at, ch.updated_at,
                       ' . $this->translationSelect('cht', 'name') . ',
                       COALESCE(cht_loc.language_code, cht_fb.language_code) AS resolved_language_code
                FROM channels ch
                ' . $this->translationJoin('ch', 'id', 'channel_translations', 'cht', 'channel_id') . '
                WHERE ' . $this->notDeletedClause('ch') . '
                ORDER BY ch.code ASC, ch.id ASC
                LIMIT ' . $limit . ' OFFSET ' . $offset;

        return $this->fetchAll($sql, $this->localeParams($locale));
    }

    public function listForProduct(string $productUuid, LocaleContext $locale): array
    {
        $sql = 'SELECT ch.uuid, ch.code, ch.status,
                       pc.uuid AS assignment_uuid, pc.is_enabled, pc.channel_config,
                       pc.publish_at, pc.archive_at,
                       ' . $this->translationSelect('cht', 'name') . ',
                       COALESCE(cht_loc.language_code, cht_fb.language_code) AS resolved_language_code
                FROM product_channels pc
                INNER JOIN products p ON p.id = pc.product_id AND p.deleted_at IS NULL
                INNER JOIN channels ch ON ch.id = pc.channel_id AND ch.deleted_at IS NULL
                ' . $this->translationJoin('ch', 'id', 'channel_translations', 'cht', 'channel_id') . '
                WHERE p.uuid = :product_uuid AND pc.deleted_at IS NULL
                ORDER BY ch.code ASC, pc.id ASC';

        $rows = $this->fetchAll($sql, array_merge(['product_uuid' => $productUuid], $this->localeParams($locale)));

        return array_map(function (array $row): array {
            if (isset($row['channel_config']) && is_string($row['channel_config'])) {
                $decoded = json_decode($row['channel_config'], true);
                $row['channel_config'] = is_array($decoded) ? $decoded : null;
            }

            return $row;
        }, $rows);
    }

    public function findIdByCode(string $code): ?int
    {
        $row = $this->fetchOne(
            'SELECT id FROM channels WHERE code = :code AND deleted_at IS NULL LIMIT 1',
            ['code' => $code]
        );

        return $row === null ? null : (int) $row['id'];
    }
}
