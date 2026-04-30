<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class RateLimiterService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function enforce(string $action, string $ipAddress, int $userId = 0): void
    {
        $ipLimit = (int) (getenv('SEC_RATE_LIMIT_IP_MAX') ?: 120);
        $userLimit = (int) (getenv('SEC_RATE_LIMIT_USER_MAX') ?: 300);
        $windowSec = (int) (getenv('SEC_RATE_LIMIT_WINDOW_SEC') ?: 60);
        if ($windowSec < 1) {
            $windowSec = 60;
        }

        $now = time();
        $windowStart = $now - $windowSec;
        $ipCount = $this->hitAndCount($action, 'ip', $ipAddress, $windowStart, $now);
        if ($ipCount > $ipLimit) {
            throw new RuntimeException('Rate limit exceeded for IP', 429);
        }
        if ($userId > 0) {
            $userCount = $this->hitAndCount($action, 'user', (string) $userId, $windowStart, $now);
            if ($userCount > $userLimit) {
                throw new RuntimeException('Rate limit exceeded for user', 429);
            }
        }
    }

    public function enforceExternal(string $action, string $ipAddress, string $clientKey): void
    {
        $ipLimit = (int) (getenv('EXT_API_RATE_LIMIT_IP_MAX') ?: 300);
        $clientLimit = (int) (getenv('EXT_API_RATE_LIMIT_CLIENT_MAX') ?: 600);
        $windowSec = (int) (getenv('EXT_API_RATE_LIMIT_WINDOW_SEC') ?: 60);
        if ($windowSec < 1) {
            $windowSec = 60;
        }
        $now = time();
        $windowStart = $now - $windowSec;
        $ipCount = $this->hitAndCount('ext.' . $action, 'ip', $ipAddress, $windowStart, $now);
        if ($ipCount > $ipLimit) {
            throw new RuntimeException('External rate limit exceeded for IP', 429);
        }
        $cCount = $this->hitAndCount('ext.' . $action, 'client', $clientKey, $windowStart, $now);
        if ($cCount > $clientLimit) {
            throw new RuntimeException('External rate limit exceeded for client', 429);
        }
    }

    private function hitAndCount(string $action, string $scopeType, string $scopeValue, int $windowStart, int $now): int
    {
        $sql = "INSERT INTO security_rate_limit_counters (action_key, scope_type, scope_value, window_start, hit_count, updated_at)
                VALUES (:action_key, :scope_type, :scope_value, :window_start, 1, :updated_at)
                ON DUPLICATE KEY UPDATE
                    hit_count = CASE
                        WHEN window_start < VALUES(window_start) THEN 1
                        ELSE hit_count + 1
                    END,
                    window_start = CASE
                        WHEN window_start < VALUES(window_start) THEN VALUES(window_start)
                        ELSE window_start
                    END,
                    updated_at = VALUES(updated_at)";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':action_key' => mb_substr(trim($action), 0, 120),
            ':scope_type' => $scopeType,
            ':scope_value' => mb_substr(trim($scopeValue), 0, 191),
            ':window_start' => $windowStart,
            ':updated_at' => $now,
        ]);

        $sel = $this->db->prepare("SELECT hit_count FROM security_rate_limit_counters
                                   WHERE action_key = :action_key
                                     AND scope_type = :scope_type
                                     AND scope_value = :scope_value
                                     AND window_start >= :window_start
                                   LIMIT 1");
        $sel->execute([
            ':action_key' => mb_substr(trim($action), 0, 120),
            ':scope_type' => $scopeType,
            ':scope_value' => mb_substr(trim($scopeValue), 0, 191),
            ':window_start' => $windowStart,
        ]);

        return (int) ($sel->fetchColumn() ?: 0);
    }
}
