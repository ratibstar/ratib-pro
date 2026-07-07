<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CompletenessRuleReadRepositoryInterface;

final class MysqlCompletenessRuleReadRepository extends BaseRepository implements CompletenessRuleReadRepositoryInterface
{
    protected function table(): string
    {
        return 'completeness_rules';
    }

    public function listActive(?string $entityType = 'product'): array
    {
        $where = ['status = :status', $this->notDeletedClause()];
        $params = ['status' => 'active'];
        if ($entityType !== null && $entityType !== '') {
            $where[] = 'entity_type = :entity_type';
            $params['entity_type'] = $entityType;
        }

        return $this->mapRules($this->fetchAll(
            'SELECT code, entity_type, locale, required_fields, is_blocking, weight, status
             FROM completeness_rules
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY code ASC',
            $params
        ));
    }

    public function listAll(): array
    {
        return $this->mapRules($this->fetchAll(
            'SELECT code, entity_type, locale, required_fields, is_blocking, weight, status
             FROM completeness_rules
             WHERE ' . $this->notDeletedClause() . '
             ORDER BY code ASC'
        ));
    }

    public function findByCode(string $code): ?array
    {
        $row = $this->fetchOne(
            'SELECT code, entity_type, locale, required_fields, is_blocking, weight, status
             FROM completeness_rules
             WHERE code = :code AND ' . $this->notDeletedClause() . '
             LIMIT 1',
            ['code' => $code]
        );
        if ($row === null) {
            return null;
        }

        return $this->mapRule($row);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function mapRules(array $rows): array
    {
        return array_map(fn (array $row): array => $this->mapRule($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapRule(array $row): array
    {
        $fields = json_decode((string) ($row['required_fields'] ?? '[]'), true);
        $row['required_fields'] = is_array($fields) ? $fields : [];
        $row['is_blocking'] = (bool) ($row['is_blocking'] ?? false);

        return $row;
    }
}
