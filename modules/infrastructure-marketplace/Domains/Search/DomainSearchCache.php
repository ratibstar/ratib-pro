<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Domains\Search;

final class DomainSearchCache
{
    public function __construct(
        private readonly \PDO $pdo
    ) {}

    /**
     * @return list<array<string, mixed>>|null
     */
    public function get(string $cacheKey): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT result_json
             FROM ratib_infra_domain_search_cache
             WHERE cache_key = :cache_key
               AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['cache_key' => $cacheKey]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $decoded = json_decode((string) ($row['result_json'] ?? '[]'), true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param list<array<string, mixed>> $result
     */
    public function put(string $cacheKey, array $result, int $ttlSeconds = 120): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ratib_infra_domain_search_cache (cache_key, result_json, expires_at, created_at, updated_at)
             VALUES (:cache_key, :result_json, DATE_ADD(NOW(), INTERVAL :ttl SECOND), NOW(), NOW())
             ON DUPLICATE KEY UPDATE result_json = VALUES(result_json), expires_at = VALUES(expires_at), updated_at = NOW()'
        );
        $stmt->execute([
            'cache_key' => $cacheKey,
            'result_json' => json_encode($result, JSON_UNESCAPED_SLASHES),
            'ttl' => $ttlSeconds,
        ]);
    }
}

