<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CompletenessRuleWriteRepositoryInterface;

final class MysqlCompletenessRuleWriteRepository extends BaseRepository implements CompletenessRuleWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'completeness_rules';
    }

    public function updateByCode(string $code, array $data): bool
    {
        $sets = [];
        $params = ['code' => $code];
        if (array_key_exists('required_fields', $data)) {
            $sets[] = 'required_fields = :required_fields';
            $params['required_fields'] = json_encode($data['required_fields'], JSON_UNESCAPED_UNICODE) ?: '[]';
        }
        if (array_key_exists('is_blocking', $data)) {
            $sets[] = 'is_blocking = :is_blocking';
            $params['is_blocking'] = (int) (bool) $data['is_blocking'];
        }
        if (array_key_exists('weight', $data)) {
            $sets[] = 'weight = :weight';
            $params['weight'] = $data['weight'];
        }
        if (array_key_exists('status', $data)) {
            $sets[] = 'status = :status';
            $params['status'] = (string) $data['status'];
        }
        if ($sets === []) {
            return false;
        }
        $sets[] = 'updated_at = CURRENT_TIMESTAMP(6)';

        $stmt = $this->writePdo->prepare(
            'UPDATE completeness_rules SET ' . implode(', ', $sets) . '
             WHERE code = :code AND deleted_at IS NULL'
        );
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }
}
