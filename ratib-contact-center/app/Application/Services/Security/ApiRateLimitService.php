<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Security;

use Ratib\ContactCenter\App\Core\Database;

final class ApiRateLimitService
{
    public function check(int $tenantId, string $clientKey, string $endpoint, int $maxPerMinute = 120): bool
    {
        $window = gmdate('Y-m-d H:i:00');
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, request_count FROM rcc_api_rate_limits
             WHERE tenant_id = :tid AND client_key = :ck AND endpoint = :ep AND window_start = :w LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'ck' => $clientKey, 'ep' => $endpoint, 'w' => $window]);
        $row = $stmt->fetch();
        if ($row === false) {
            $pdo->prepare(
                'INSERT INTO rcc_api_rate_limits (tenant_id, client_key, endpoint, window_start, request_count) VALUES (:tid, :ck, :ep, :w, 1)'
            )->execute(['tid' => $tenantId, 'ck' => $clientKey, 'ep' => $endpoint, 'w' => $window]);
            return true;
        }
        $count = (int) $row['request_count'];
        if ($count >= $maxPerMinute) {
            return false;
        }
        $pdo->prepare('UPDATE rcc_api_rate_limits SET request_count = request_count + 1 WHERE id = :id')
            ->execute(['id' => (int) $row['id']]);
        return true;
    }

    public function isIpAllowed(int $tenantId, string $ip): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT cidr, rule_type FROM rcc_ip_restrictions WHERE tenant_id = :tid AND is_active = 1'
        );
        $stmt->execute(['tid' => $tenantId]);
        $rules = $stmt->fetchAll() ?: [];
        if ($rules === []) {
            return true;
        }
        foreach ($rules as $rule) {
            if ($this->ipInCidr($ip, (string) $rule['cidr'])) {
                return (string) $rule['rule_type'] === 'allow';
            }
        }
        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$subnet, $mask] = explode('/', $cidr, 2);
        $mask = (int) $mask;
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        $wildcard = (1 << (32 - $mask)) - 1;
        return ($ipLong & ~$wildcard) === ($subnetLong & ~$wildcard);
    }
}
