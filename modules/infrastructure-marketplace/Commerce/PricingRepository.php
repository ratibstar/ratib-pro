<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Commerce;

/**
 * Regional / tenant / agency pricing overlays for rateb_infra_pricing.
 */
final class PricingRepository
{
    public function __construct(private \PDO $pdo) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForPlan(int $planId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM rateb_infra_pricing WHERE plan_id = :p AND active = 1 ORDER BY id ASC'
        );
        $stmt->execute(['p' => $planId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Pick best-matching overlay: more specific scope wins (tenant+agency+region > tenant > agency > global).
     *
     * @return array<string, mixed>|null
     */
    public function resolveEffective(int $planId, ?int $tenantId, ?int $agencyId, ?string $regionCode): ?array
    {
        $rows = $this->listForPlan($planId);
        $best = null;
        $bestScore = -1;
        $rc = $regionCode !== null && $regionCode !== '' ? strtoupper(trim($regionCode)) : null;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $t = isset($row['tenant_id']) && $row['tenant_id'] !== null ? (int) $row['tenant_id'] : null;
            $a = isset($row['agency_id']) && $row['agency_id'] !== null ? (int) $row['agency_id'] : null;
            $r = isset($row['region_code']) && $row['region_code'] !== null && (string) $row['region_code'] !== ''
                ? strtoupper(trim((string) $row['region_code'])) : null;

            $tenantMatch = ($t === null) || ($tenantId !== null && $t === $tenantId);
            $agencyMatch = ($a === null) || ($agencyId !== null && $a === $agencyId);
            $regionMatch = ($r === null) || ($rc !== null && $r === $rc);
            if (!$tenantMatch || !$agencyMatch || !$regionMatch) {
                continue;
            }
            $score = 0;
            if ($t !== null) {
                $score += 4;
            }
            if ($a !== null) {
                $score += 2;
            }
            if ($r !== null) {
                $score += 1;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row;
            }
        }

        return $best;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insert(array $row): int
    {
        $cols = [
            'plan_id', 'tenant_id', 'agency_id', 'region_code', 'currency', 'recurring_price', 'setup_price',
            'override_flags_json', 'active',
        ];
        $fields = [];
        $params = [];
        foreach ($cols as $c) {
            if (!array_key_exists($c, $row)) {
                continue;
            }
            $fields[] = $c;
            $params[$c] = $row[$c];
        }
        if ($fields === []) {
            throw new \InvalidArgumentException('No columns for insert.');
        }
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $fields);
        $sql = 'INSERT INTO rateb_infra_pricing (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $this->pdo->lastInsertId();
    }
}
