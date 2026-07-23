<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * In-memory subscription engine rows for scheduler unit tests.
 */
final class InMemorySubscriptionEngineStore implements SubscriptionEngineStore
{
    /** @var list<array<string, mixed>> */
    private array $rows;

    /** @param list<array<string, mixed>> $rows */
    public function __construct(array $rows = [])
    {
        usort($rows, static fn (array $a, array $b): int => ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0)));
        $this->rows = array_values($rows);
    }

    public function findByCompanyId(int $companyId): ?array
    {
        foreach ($this->rows as $row) {
            if ((int) ($row['company_id'] ?? 0) === $companyId) {
                return $row;
            }
        }
        return null;
    }

    public function listEngineRowsAfterId(int $afterId, int $limit): array
    {
        $out = [];
        foreach ($this->rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= $afterId) {
                continue;
            }
            $out[] = $row;
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * Patch an existing company row (test / renewal harness).
     *
     * @param array<string, mixed> $patch
     */
    public function patchByCompanyId(int $companyId, array $patch): bool
    {
        foreach ($this->rows as $i => $row) {
            if ((int) ($row['company_id'] ?? 0) !== $companyId) {
                continue;
            }
            $this->rows[$i] = array_merge($row, $patch);
            return true;
        }
        return false;
    }
}
