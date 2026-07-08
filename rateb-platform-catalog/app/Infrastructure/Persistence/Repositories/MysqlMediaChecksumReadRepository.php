<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\MediaChecksumReadRepositoryInterface;

final class MysqlMediaChecksumReadRepository extends BaseRepository implements MediaChecksumReadRepositoryInterface
{
    protected function table(): string
    {
        return 'product_images';
    }

    public function imageChecksumExists(string $checksum, ?string $excludeImageUuid = null): bool
    {
        $sql = 'SELECT id FROM product_images WHERE checksum_sha256 = :checksum AND deleted_at IS NULL';
        $params = ['checksum' => $checksum];
        if ($excludeImageUuid !== null) {
            $sql .= ' AND uuid <> :exclude_uuid';
            $params['exclude_uuid'] = $excludeImageUuid;
        }
        $sql .= ' LIMIT 1';

        return $this->fetchOne($sql, $params) !== null;
    }

    public function fileChecksumExists(string $checksum, ?string $excludeFileUuid = null): bool
    {
        $sql = 'SELECT id FROM product_files WHERE checksum_sha256 = :checksum AND deleted_at IS NULL';
        $params = ['checksum' => $checksum];
        if ($excludeFileUuid !== null) {
            $sql .= ' AND uuid <> :exclude_uuid';
            $params['exclude_uuid'] = $excludeFileUuid;
        }
        $sql .= ' LIMIT 1';

        return $this->fetchOne($sql, $params) !== null;
    }
}
