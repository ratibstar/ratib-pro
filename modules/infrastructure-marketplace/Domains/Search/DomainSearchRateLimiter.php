<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Domains\Search;

final class DomainSearchRateLimiter
{
    public function __construct(
        private readonly \PDO $pdo
    ) {}

    public function allow(string $scopeKey, int $maxPerMinute = 30): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ratib_infra_domain_search_rate (scope_key, minute_bucket, request_count, created_at, updated_at)
             VALUES (:scope_key, DATE_FORMAT(NOW(), "%Y%m%d%H%i"), 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE request_count = request_count + 1, updated_at = NOW()'
        );
        $stmt->execute(['scope_key' => substr($scopeKey, 0, 120)]);

        $check = $this->pdo->prepare(
            'SELECT request_count FROM ratib_infra_domain_search_rate
             WHERE scope_key = :scope_key AND minute_bucket = DATE_FORMAT(NOW(), "%Y%m%d%H%i")
             LIMIT 1'
        );
        $check->execute(['scope_key' => substr($scopeKey, 0, 120)]);
        $row = $check->fetch(\PDO::FETCH_ASSOC);
        $count = is_array($row) ? (int) ($row['request_count'] ?? 0) : 0;
        return $count <= $maxPerMinute;
    }
}

