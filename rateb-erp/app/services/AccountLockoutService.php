<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\User;

final class AccountLockoutService
{
    public function isLocked(?array $user): bool
    {
        if (!$user) {
            return false;
        }
        $lockedUntil = (string) ($user['locked_until'] ?? '');
        if ($lockedUntil === '') {
            return false;
        }
        if (strtotime($lockedUntil) > time()) {
            return true;
        }
        $this->clearLock((int) $user['id']);
        return false;
    }

    public function recordFailure(string $email): void
    {
        $user = (new User())->findByEmail(trim($email));
        if (!$user || (int) ($user['is_super_admin'] ?? 0) === 1) {
            return;
        }
        $userId = (int) $user['id'];
        $max = AutomationSettings::lockoutMaxAttempts();
        $attempts = (int) ($user['failed_attempts'] ?? 0) + 1;
        $db = Database::connection();
        if ($attempts >= $max) {
            $minutes = AutomationSettings::lockoutDurationMinutes();
            $lockedUntil = date('Y-m-d H:i:s', time() + ($minutes * 60));
            $db->prepare('UPDATE rateb_users SET failed_attempts = :a, locked_until = :lu WHERE id = :id')
                ->execute(['a' => $attempts, 'lu' => $lockedUntil, 'id' => $userId]);
            (new AuditService())->log('account_locked', 'user', $userId, [
                'attempts' => $attempts,
                'locked_until' => $lockedUntil,
            ]);
            return;
        }
        $db->prepare('UPDATE rateb_users SET failed_attempts = :a WHERE id = :id')
            ->execute(['a' => $attempts, 'id' => $userId]);
    }

    public function clearLock(int $userId): void
    {
        if ($userId < 1) {
            return;
        }
        Database::connection()->prepare(
            'UPDATE rateb_users SET failed_attempts = 0, locked_until = NULL WHERE id = :id'
        )->execute(['id' => $userId]);
    }

    public function unlockExpired(): int
    {
        $stmt = Database::connection()->prepare(
            'UPDATE rateb_users SET failed_attempts = 0, locked_until = NULL
             WHERE locked_until IS NOT NULL AND locked_until <= NOW()'
        );
        $stmt->execute();
        return $stmt->rowCount();
    }
}
