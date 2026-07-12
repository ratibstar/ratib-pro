<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\User;

final class BarcodeLoginService
{
    public const PREFIX = 'RATEBERP:';
    private const PAIR_TTL = 600;
    private const PAIR_COOKIE = 'rateb_pair';

    public function normalizeBarcode(string $raw): string
    {
        $raw = $this->extractScanPayload($raw);
        $raw = strtoupper(trim($raw));
        $prefix = self::PREFIX;
        if (substr($raw, 0, strlen($prefix)) === $prefix) {
            $raw = substr($raw, strlen($prefix));
        }
        return preg_replace('/[^A-Z0-9]/', '', $raw) ?? '';
    }

    public function extractScanPayload(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $raw)) {
            $query = parse_url($raw, PHP_URL_QUERY);
            if (is_string($query) && $query !== '') {
                parse_str($query, $params);
                if (!empty($params['d'])) {
                    return trim((string) $params['d']);
                }
                if (!empty($params['badge'])) {
                    return trim((string) $params['badge']);
                }
            }
        }
        return $raw;
    }

    public function isPairingQr(string $raw): bool
    {
        $v = trim($raw);
        if ($v === '') {
            return false;
        }
        if (preg_match('/login[-\\/]scan/i', $v)) {
            return true;
        }
        if (!preg_match('/^https?:\/\//i', $v)) {
            return false;
        }
        $parts = parse_url($v);
        if (!is_array($parts)) {
            return false;
        }
        $path = (string) ($parts['path'] ?? '');
        $query = (string) ($parts['query'] ?? '');
        if ($query !== '' && strpos($query, 'token=') !== false && preg_match('/login/i', $path)) {
            return true;
        }
        return (bool) preg_match('/login[-\\/]scan/i', $path);
    }

    public function generateBarcodeValue(int $userId, string $name = ''): string
    {
        $seed = strtoupper(preg_replace('/[^A-Z0-9]/', '', $name) ?? '');
        $seed = $seed !== '' ? substr($seed, 0, 4) : 'USR';
        return sprintf('ERP%06d%s', max(1, $userId), $seed);
    }

    public function ensureUserBarcode(int $userId): ?string
    {
        $user = (new User())->find($userId);
        if (!$user) {
            return null;
        }
        $existing = trim((string) ($user['login_barcode'] ?? ''));
        if ($existing !== '') {
            return $existing;
        }
        $barcode = $this->generateBarcodeValue($userId, (string) ($user['name'] ?? ''));
        $db = Database::connection();
        $stmt = $db->prepare('UPDATE rateb_users SET login_barcode = :bc WHERE id = :id AND (login_barcode IS NULL OR login_barcode = \'\')');
        $stmt->execute(['bc' => $barcode, 'id' => $userId]);
        return $barcode;
    }

    public function findUserByBarcode(string $raw): ?array
    {
        if ($this->isPairingQr($raw)) {
            return null;
        }
        $code = $this->normalizeBarcode($raw);
        if ($code === '' || strlen($code) < 6) {
            return null;
        }
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rateb_users WHERE login_barcode = :bc AND status = \'active\' LIMIT 1'
        );
        $stmt->execute(['bc' => $code]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function badgePayload(string $barcode): string
    {
        return self::PREFIX . $this->normalizeBarcode($barcode);
    }

    public function badgeLoginUrl(string $barcode): string
    {
        return rateb_url('login/badge') . '?d=' . rawurlencode($this->badgePayload($barcode));
    }

    /** Large high-contrast QR for phone camera scan (short payload = faster read). */
    public function badgeScanQrUrl(string $barcode, int $size = 420): string
    {
        return $this->qrImageUrl($this->badgePayload($barcode), $size);
    }

    public function qrImageUrl(string $payload, int $size = 200): string
    {
        $size = max(120, min(500, $size));

        return rateb_local_qr_url($payload, $size, true);
    }

    public function setPairCookie(string $token): void
    {
        $token = preg_replace('/[^a-f0-9]/', '', strtolower($token)) ?? '';
        if (strlen($token) !== 32) {
            return;
        }
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
                && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
        setcookie(self::PAIR_COOKIE, $token, [
            'expires' => time() + self::PAIR_TTL,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public function readPairCookie(): string
    {
        $token = preg_replace('/[^a-f0-9]/', '', strtolower((string) ($_COOKIE[self::PAIR_COOKIE] ?? ''))) ?? '';
        return strlen($token) === 32 ? $token : '';
    }

    /** @return array{ok:bool, token?:string, message?:string} */
    public function pairCreate(): array
    {
        $token = bin2hex(random_bytes(16));
        $now = time();
        $db = Database::connection();
        $db->prepare(
            'INSERT INTO rateb_login_barcode_pairs (token, status, context_json, expires_at, created_at)
             VALUES (:t, \'pending\', :ctx, :exp, :crt)'
        )->execute([
            't' => $token,
            'ctx' => json_encode(['portal' => 'erp'], JSON_UNESCAPED_UNICODE),
            'exp' => $now + self::PAIR_TTL,
            'crt' => $now,
        ]);
        return ['ok' => true, 'token' => $token];
    }

    /** @return array{ok:bool, status:string} */
    public function pairPoll(string $token): array
    {
        $pair = $this->pairRead($token);
        if ($pair === null) {
            return ['ok' => false, 'status' => 'expired'];
        }
        $status = (string) ($pair['status'] ?? 'pending');
        if ($status === 'complete') {
            $status = 'approved';
        }
        return ['ok' => true, 'status' => $status];
    }

    /** @return array{ok:bool, message?:string, user?:array<string,mixed>} */
    public function pairApprove(string $token, int $userId): array
    {
        $pair = $this->pairRead($token);
        if ($pair === null) {
            return ['ok' => false, 'message' => __('barcode_pair_expired')];
        }
        if (($pair['status'] ?? '') !== 'pending') {
            return ['ok' => false, 'message' => __('barcode_pair_used')];
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE rateb_login_barcode_pairs SET status = \'approved\', user_id = :uid WHERE token = :t AND status = \'pending\''
        );
        $stmt->execute(['uid' => $userId, 't' => $this->cleanToken($token)]);
        if ($stmt->rowCount() < 1) {
            return ['ok' => false, 'message' => __('barcode_pair_used')];
        }
        $user = (new User())->find($userId);
        return ['ok' => true, 'user' => $user ?: null];
    }

    /** @return array{ok:bool, message?:string, user?:array<string,mixed>} */
    public function pairSubmit(string $token, string $barcode): array
    {
        $user = $this->findUserByBarcode($barcode);
        if (!$user) {
            return ['ok' => false, 'message' => __('barcode_invalid')];
        }
        $approved = $this->pairApprove($token, (int) $user['id']);
        if (!$approved['ok']) {
            return $approved;
        }
        return ['ok' => true, 'user' => $user];
    }

    public function pairConsumeForLogin(string $token): ?array
    {
        $token = $this->cleanToken($token);
        if (strlen($token) !== 32) {
            return null;
        }
        $this->purgeExpiredPairs();
        $stmt = Database::connection()->prepare(
            'SELECT status, user_id, expires_at FROM rateb_login_barcode_pairs WHERE token = :t LIMIT 1'
        );
        $stmt->execute(['t' => $token]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if ((int) ($row['expires_at'] ?? 0) < time()) {
            Database::connection()->prepare('DELETE FROM rateb_login_barcode_pairs WHERE token = :t')->execute(['t' => $token]);
            return null;
        }
        $status = (string) ($row['status'] ?? '');
        if ($status !== 'approved' && $status !== 'complete') {
            return null;
        }
        $userId = (int) ($row['user_id'] ?? 0);
        if ($userId < 1) {
            return null;
        }
        Database::connection()->prepare('DELETE FROM rateb_login_barcode_pairs WHERE token = :t')->execute(['t' => $token]);
        return (new User())->find($userId);
    }

    public function purgeExpiredPairs(): void
    {
        Database::connection()->prepare('DELETE FROM rateb_login_barcode_pairs WHERE expires_at < :now')
            ->execute(['now' => time()]);
    }

    /** @return array{status:string, user_id?:int}|null */
    private function pairRead(string $token): ?array
    {
        $token = $this->cleanToken($token);
        if (strlen($token) !== 32) {
            return null;
        }
        $this->purgeExpiredPairs();
        $stmt = Database::connection()->prepare(
            'SELECT status, user_id, expires_at FROM rateb_login_barcode_pairs WHERE token = :t LIMIT 1'
        );
        $stmt->execute(['t' => $token]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if ((int) ($row['expires_at'] ?? 0) < time()) {
            Database::connection()->prepare('DELETE FROM rateb_login_barcode_pairs WHERE token = :t')->execute(['t' => $token]);
            return null;
        }
        return $row;
    }

    private function cleanToken(string $token): string
    {
        return preg_replace('/[^a-f0-9]/', '', strtolower($token)) ?? '';
    }
}
