<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AttributeReadRepositoryInterface;

final class MysqlAttributeReadRepository extends BaseRepository implements AttributeReadRepositoryInterface
{
    protected function table(): string
    {
        return 'attributes';
    }

    public function findByUuid(string $uuid, LocaleContext $locale): ?array
    {
        $sql = 'SELECT a.uuid, a.code, a.input_type, a.is_variant_defining, a.is_filterable,
                       a.is_visible, a.sort_order, a.status, a.created_at, a.updated_at,
                       ' . $this->translationSelect('at', 'name') . ',
                       COALESCE(at_loc.language_code, at_fb.language_code) AS resolved_language_code
                FROM attributes a
                ' . $this->translationJoin('a', 'id', 'attribute_translations', 'at', 'attribute_id') . '
                WHERE a.uuid = :uuid AND ' . $this->notDeletedClause('a') . '
                LIMIT 1';

        return $this->fetchOne($sql, array_merge(['uuid' => $uuid], $this->localeParams($locale)));
    }

    public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        $sql = 'SELECT a.uuid, a.code, a.input_type, a.is_variant_defining, a.is_filterable,
                       a.is_visible, a.sort_order, a.status,
                       ' . $this->translationSelect('at', 'name') . ',
                       COALESCE(at_loc.language_code, at_fb.language_code) AS resolved_language_code
                FROM attributes a
                ' . $this->translationJoin('a', 'id', 'attribute_translations', 'at', 'attribute_id') . '
                WHERE ' . $this->notDeletedClause('a') . '
                ORDER BY a.sort_order ASC, a.id ASC
                LIMIT ' . $limit . ' OFFSET ' . $offset;

        return $this->fetchAll($sql, $this->localeParams($locale));
    }

    public function listValuesForAttribute(string $attributeUuid, LocaleContext $locale): array
    {
        $sql = 'SELECT av.uuid, av.sort_order, av.status,
                       ' . $this->translationSelect('avt', 'value') . ',
                       COALESCE(avt_loc.language_code, avt_fb.language_code) AS resolved_language_code
                FROM attribute_values av
                INNER JOIN attributes a ON a.id = av.attribute_id AND a.deleted_at IS NULL
                LEFT JOIN attribute_value_translations avt_loc ON avt_loc.attribute_value_id = av.id
                    AND avt_loc.language_code = :locale AND avt_loc.deleted_at IS NULL
                LEFT JOIN attribute_value_translations avt_fb ON avt_fb.attribute_value_id = av.id
                    AND avt_fb.language_code = :fallback AND avt_fb.deleted_at IS NULL
                WHERE a.uuid = :attribute_uuid AND ' . $this->notDeletedClause('av') . '
                ORDER BY av.sort_order ASC, av.id ASC';

        return $this->fetchAll($sql, array_merge(
            ['attribute_uuid' => $attributeUuid],
            $this->localeParams($locale)
        ));
    }
}
