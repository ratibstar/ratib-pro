<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SessionRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function recordSessionActivity(int $userId, string $ipAddress, string $deviceInfo, string $sessionId): void
    {
        if ($userId <= 0 || $sessionId === '') {
            return;
        }
        $sql = "INSERT INTO user_session_audit (session_id, user_id, ip_address, device_info, login_time, last_activity)
                VALUES (:session_id, :user_id, :ip_address, :device_info, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    ip_address = VALUES(ip_address),
                    device_info = VALUES(device_info),
                    last_activity = NOW()";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':session_id' => $sessionId,
            ':user_id' => $userId,
            ':ip_address' => $this->clipUtf8(trim($ipAddress), 64),
            ':device_info' => $this->clipUtf8(trim($deviceInfo), 500),
        ]);
    }

    /** Avoid fatal errors when ext-mbstring is disabled. */
    private function clipUtf8(string $value, int $maxLen): string
    {
        if ($maxLen < 1) {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLen, 'UTF-8');
        }

        return strlen($value) <= $maxLen ? $value : substr($value, 0, $maxLen);
    }
}
