<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\User;

final class RememberMeService
{
    private const COOKIE = 'rateb_remember';

    public function issue(int $userId, ?string $deviceName = null): void
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $days = AutomationSettings::rememberMeDays();
        $expires = date('Y-m-d H:i:s', time() + ($days * 86400));
        $db = Database::connection();
        $db->prepare(
            'INSERT INTO rateb_remember_tokens (user_id, token_hash, device_name, expires_at)
             VALUES (:uid, :hash, :dev, :exp)'
        )->execute([
            'uid' => $userId,
            'hash' => $hash,
            'dev' => $deviceName,
            'exp' => $expires,
        ]);
        $this->setCookie($userId . ':' . $token, $days * 86400);
    }

    public function tryLogin(): ?array
    {
        $raw = $_COOKIE[self::COOKIE] ?? '';
        if ($raw === '' || strpos($raw, ':') === false) {
            return null;
        }
        [$userId, $token] = explode(':', $raw, 2);
        $userId = (int) $userId;
        if ($userId < 1 || $token === '') {
            return null;
        }
        $hash = hash('sha256', $token);
        $db = Database::connection();
        $row = $db->prepare(
            'SELECT * FROM rateb_remember_tokens
             WHERE user_id = :uid AND token_hash = :hash AND revoked_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $row->execute(['uid' => $userId, 'hash' => $hash]);
        $remember = $row->fetch();
        if (!$remember) {
            $this->clearCookie();
            return null;
        }
        $user = (new User())->find($userId);
        if (!$user || (string) ($user['status'] ?? '') !== 'active') {
            $this->revokeToken((int) $remember['id']);
            return null;
        }
        $db->prepare('UPDATE rateb_remember_tokens SET last_used_at = NOW() WHERE id = :id')
            ->execute(['id' => (int) $remember['id']]);
        $this->rotateToken((int) $remember['id'], $userId);
        return $user;
    }

    public function revokeAllForUser(int $userId): void
    {
        Database::connection()->prepare(
            'UPDATE rateb_remember_tokens SET revoked_at = NOW() WHERE user_id = :uid AND revoked_at IS NULL'
        )->execute(['uid' => $userId]);
        $this->clearCookie();
    }

    public function revokeToken(int $tokenId): void
    {
        Database::connection()->prepare(
            'UPDATE rateb_remember_tokens SET revoked_at = NOW() WHERE id = :id'
        )->execute(['id' => $tokenId]);
    }

    private function rotateToken(int $oldId, int $userId): void
    {
        $this->revokeToken($oldId);
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
        $this->issue($userId, $ua);
    }

    private function setCookie(string $value, int $lifetime): void
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(self::COOKIE, $value, [
            'expires' => time() + $lifetime,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function clearCookie(): void
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(self::COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
