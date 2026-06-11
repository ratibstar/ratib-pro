<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\User;

final class PasswordResetService
{
    public function createTokenForEmail(string $email): ?string
    {
        $user = (new User())->findByEmail(trim($email));
        if (!$user || (string) ($user['status'] ?? '') !== 'active') {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + 3600);

        $db = \Rateb\App\Core\Database::connection();
        $db->prepare('UPDATE rateb_password_resets SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL')
            ->execute(['uid' => (int) $user['id']]);
        $db->prepare(
            'INSERT INTO rateb_password_resets (user_id, token_hash, expires_at) VALUES (:uid, :hash, :exp)'
        )->execute(['uid' => (int) $user['id'], 'hash' => $hash, 'exp' => $expires]);

        $resetUrl = rateb_url('password/reset/' . $token);
        (new MailService())->sendTemplate((string) $user['email'], 'password_reset', [
            'name' => (string) ($user['name'] ?? ''),
            'reset_url' => $resetUrl,
            'link' => $resetUrl,
        ]);

        return $token;
    }

    public function resetWithToken(string $token, string $newPassword): bool
    {
        if (strlen($newPassword) < 8) {
            return false;
        }
        $hash = hash('sha256', $token);
        $db = \Rateb\App\Core\Database::connection();
        $row = $db->prepare(
            'SELECT * FROM rateb_password_resets WHERE token_hash = :h AND used_at IS NULL AND expires_at > NOW() LIMIT 1'
        );
        $row->execute(['h' => $hash]);
        $reset = $row->fetch();
        if (!$reset) {
            return false;
        }

        $userId = (int) $reset['user_id'];
        (new User())->update($userId, ['password' => password_hash($newPassword, PASSWORD_DEFAULT)]);
        $db->prepare('UPDATE rateb_password_resets SET used_at = NOW() WHERE id = :id')->execute(['id' => (int) $reset['id']]);
        return true;
    }

    public function validateToken(string $token): bool
    {
        $hash = hash('sha256', $token);
        $db = \Rateb\App\Core\Database::connection();
        $stmt = $db->prepare(
            'SELECT id FROM rateb_password_resets WHERE token_hash = :h AND used_at IS NULL AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute(['h' => $hash]);
        return (bool) $stmt->fetch();
    }
}
