<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\SupplierRepositoryInterface;

final class MysqlSupplierRepository extends BaseRepository implements SupplierRepositoryInterface
{
    protected function table(): string
    {
        return 'suppliers';
    }

    public function findByUuid(string $uuid, LocaleContext $locale): ?array
    {
        $sql = 'SELECT s.uuid, s.code, s.contact_email, s.contact_phone, s.country_code, s.status,
                       s.created_at, s.updated_at,
                       ' . $this->translationSelect('st', 'name') . ',
                       COALESCE(st_loc.language_code, st_fb.language_code) AS resolved_language_code
                FROM suppliers s
                ' . $this->translationJoin('s', 'id', 'supplier_translations', 'st', 'supplier_id') . '
                WHERE s.uuid = :uuid AND ' . $this->notDeletedClause('s') . '
                LIMIT 1';

        return $this->fetchOne($sql, array_merge(['uuid' => $uuid], $this->localeParams($locale)));
    }

    public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        $sql = 'SELECT s.uuid, s.code, s.contact_email, s.contact_phone, s.country_code, s.status,
                       ' . $this->translationSelect('st', 'name') . ',
                       COALESCE(st_loc.language_code, st_fb.language_code) AS resolved_language_code
                FROM suppliers s
                ' . $this->translationJoin('s', 'id', 'supplier_translations', 'st', 'supplier_id') . '
                WHERE ' . $this->notDeletedClause('s') . '
                ORDER BY name ASC, s.id ASC
                LIMIT ' . $limit . ' OFFSET ' . $offset;

        return $this->fetchAll($sql, $this->localeParams($locale));
    }

    public function create(array $data): string
    {
        throw new \LogicException('Supplier write operations are not exposed in Phase 2.2 read APIs.');
    }

    public function update(string $uuid, array $data): bool
    {
        throw new \LogicException('Supplier write operations are not exposed in Phase 2.2 read APIs.');
    }

    public function softDelete(string $uuid, ?int $actorId = null): bool
    {
        throw new \LogicException('Supplier write operations are not exposed in Phase 2.2 read APIs.');
    }
}
