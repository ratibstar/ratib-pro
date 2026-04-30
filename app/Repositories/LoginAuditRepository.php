<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class LoginAuditRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function logAttempt(?int $userId, string $username, bool $success, string $ipAddress): void
    {
        $sql = "INSERT INTO user_login_audit (user_id, username, success, ip_address, created_at)
                VALUES (:user_id, :username, :success, :ip_address, NOW())";
        $st = $this->db->prepare($sql);
        $st->bindValue(':user_id', $userId, $userId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->bindValue(':username', mb_substr(trim($username), 0, 191), PDO::PARAM_STR);
        $st->bindValue(':success', $success ? 1 : 0, PDO::PARAM_INT);
        $st->bindValue(':ip_address', mb_substr(trim($ipAddress), 0, 64), PDO::PARAM_STR);
        $st->execute();
    }
}
