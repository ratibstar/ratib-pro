<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CategoryRepositoryInterface;

final class MysqlCategoryRepository extends BaseRepository implements CategoryRepositoryInterface
{
    protected function table(): string
    {
        return 'categories';
    }

    public function listFlat(LocaleContext $locale): array
    {
        $sql = 'SELECT c.id, c.uuid, c.parent_id, c.slug, c.depth, c.path, c.sort_order, c.image_path, c.status,
                       ' . $this->translationSelect('ct', 'name') . ',
                       ' . $this->translationSelect('ct', 'description') . ',
                       COALESCE(ct_loc.language_code, ct_fb.language_code) AS resolved_language_code
                FROM categories c
                ' . $this->translationJoin('c', 'id', 'category_translations', 'ct', 'category_id') . '
                WHERE ' . $this->notDeletedClause('c') . '
                ORDER BY c.depth ASC, c.sort_order ASC, c.id ASC';

        return $this->fetchAll($sql, $this->localeParams($locale));
    }

    public function findByUuid(string $uuid, LocaleContext $locale): ?array
    {
        $sql = 'SELECT c.id, c.uuid, c.parent_id, c.slug, c.depth, c.path, c.sort_order, c.image_path, c.status,
                       c.created_at, c.updated_at,
                       ' . $this->translationSelect('ct', 'name') . ',
                       ' . $this->translationSelect('ct', 'description') . ',
                       COALESCE(ct_loc.language_code, ct_fb.language_code) AS resolved_language_code
                FROM categories c
                ' . $this->translationJoin('c', 'id', 'category_translations', 'ct', 'category_id') . '
                WHERE c.uuid = :uuid AND ' . $this->notDeletedClause('c') . '
                LIMIT 1';

        $params = array_merge(['uuid' => $uuid], $this->localeParams($locale));

        return $this->fetchOne($sql, $params);
    }

    public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
    {
        $rows = $this->listFlat($locale);

        return array_slice($rows, $offset, $limit);
    }

    public function create(array $data): string
    {
        throw new \LogicException('Category write operations are not exposed in Phase 2.2 read APIs.');
    }

    public function update(string $uuid, array $data): bool
    {
        throw new \LogicException('Category write operations are not exposed in Phase 2.2 read APIs.');
    }

    public function softDelete(string $uuid, ?int $actorId = null): bool
    {
        throw new \LogicException('Category write operations are not exposed in Phase 2.2 read APIs.');
    }
}
