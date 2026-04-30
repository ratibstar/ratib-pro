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
            ':ip_address' => mb_substr(trim($ipAddress), 0, 64),
            ':device_info' => mb_substr(trim($deviceInfo), 0, 500),
        ]);
    }
}
