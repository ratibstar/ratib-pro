<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\User;

final class TwoFactorService
{
    /** @return array{secret:string,uri:string,backup_codes:array<int,string>} */
    public function beginSetup(int $userId): array
    {
        $user = (new User())->find($userId);
        if (!$user) {
            throw new \RuntimeException('User not found');
        }
        $secret = TotpHelper::generateSecret();
        $uri = TotpHelper::provisioningUri((string) $user['email'], $secret);
        $_SESSION['_rateb_2fa_pending_secret'] = $secret;
        $_SESSION['_rateb_2fa_pending_user'] = $userId;
        return ['secret' => $secret, 'uri' => $uri, 'backup_codes' => []];
    }

    public function confirmSetup(int $userId, string $code): array
    {
        $secret = (string) ($_SESSION['_rateb_2fa_pending_secret'] ?? '');
        if ($secret === '' || (int) ($_SESSION['_rateb_2fa_pending_user'] ?? 0) !== $userId) {
            throw new \RuntimeException('2FA setup not started');
        }
        if (!TotpHelper::verify($secret, $code)) {
            throw new \RuntimeException('Invalid verification code');
        }
        (new User())->update($userId, [
            'two_factor_secret' => $secret,
            'two_factor_enabled' => 1,
        ]);
        unset($_SESSION['_rateb_2fa_pending_secret'], $_SESSION['_rateb_2fa_pending_user']);
        $backupCodes = $this->generateBackupCodes($userId);
        (new AuditService())->log('2fa_enabled', 'user', $userId);
        return $backupCodes;
    }

    public function disable(int $userId): void
    {
        (new User())->update($userId, [
            'two_factor_secret' => null,
            'two_factor_enabled' => 0,
        ]);
        $db = Database::connection();
        $db->prepare('DELETE FROM rateb_two_factor_backup_codes WHERE user_id = :uid')->execute(['uid' => $userId]);
        (new AuditService())->log('2fa_disabled', 'user', $userId);
    }

    public function verifyLogin(array $user, string $code): bool
    {
        if ((int) ($user['two_factor_enabled'] ?? 0) !== 1) {
            return true;
        }
        $secret = (string) ($user['two_factor_secret'] ?? '');
        if ($secret !== '' && TotpHelper::verify($secret, $code)) {
            return true;
        }
        return $this->consumeBackupCode((int) $user['id'], $code);
    }

    public function needsVerification(array $user): bool
    {
        return (int) ($user['two_factor_enabled'] ?? 0) === 1;
    }

    /** @return array<int,string> */
    public function generateBackupCodes(int $userId): array
    {
        $db = Database::connection();
        $db->prepare('DELETE FROM rateb_two_factor_backup_codes WHERE user_id = :uid')->execute(['uid' => $userId]);
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $plain = strtoupper(bin2hex(random_bytes(4)));
            $codes[] = $plain;
            $db->prepare(
                'INSERT INTO rateb_two_factor_backup_codes (user_id, code_hash) VALUES (:uid, :hash)'
            )->execute(['uid' => $userId, 'hash' => hash('sha256', $plain)]);
        }
        return $codes;
    }

    private function consumeBackupCode(int $userId, string $code): bool
    {
        $normalized = strtoupper(preg_replace('/\s+/', '', $code) ?? '');
        $hash = hash('sha256', $normalized);
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id FROM rateb_two_factor_backup_codes
             WHERE user_id = :uid AND code_hash = :hash AND used_at IS NULL LIMIT 1'
        );
        $stmt->execute(['uid' => $userId, 'hash' => $hash]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        $db->prepare('UPDATE rateb_two_factor_backup_codes SET used_at = NOW() WHERE id = :id')
            ->execute(['id' => (int) $row['id']]);
        (new AuditService())->log('2fa_backup_used', 'user', $userId);
        return true;
    }
}
