<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Validators;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\BaseRepository;

final class MediaChecksumValidator extends BaseRepository
{
    protected function table(): string
    {
        return 'product_images';
    }

    public function assertImageChecksumAvailable(?string $checksum, ?string $excludeImageUuid = null): void
    {
        if ($checksum === null || $checksum === '') {
            return;
        }

        $sql = 'SELECT id FROM product_images WHERE checksum_sha256 = :checksum AND deleted_at IS NULL';
        $params = ['checksum' => $checksum];
        if ($excludeImageUuid !== null) {
            $sql .= ' AND uuid <> :exclude_uuid';
            $params['exclude_uuid'] = $excludeImageUuid;
        }
        $sql .= ' LIMIT 1';

        if ($this->fetchOne($sql, $params) !== null) {
            throw new \InvalidArgumentException('Image checksum already exists');
        }
    }

    public function assertFileChecksumAvailable(?string $checksum, ?string $excludeFileUuid = null): void
    {
        if ($checksum === null || $checksum === '') {
            return;
        }

        $sql = 'SELECT id FROM product_files WHERE checksum_sha256 = :checksum AND deleted_at IS NULL';
        $params = ['checksum' => $checksum];
        if ($excludeFileUuid !== null) {
            $sql .= ' AND uuid <> :exclude_uuid';
            $params['exclude_uuid'] = $excludeFileUuid;
        }
        $sql .= ' LIMIT 1';

        if ($this->fetchOne($sql, $params) !== null) {
            throw new \InvalidArgumentException('File checksum already exists');
        }
    }
}
