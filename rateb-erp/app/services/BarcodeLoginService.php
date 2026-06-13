<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\User;

final class BarcodeLoginService
{
    public const PREFIX = 'RATEBERP:';
    private const PAIR_TTL = 600;

    public function normalizeBarcode(string $raw): string
    {
        $raw = strtoupper(trim($raw));
        $prefix = self::PREFIX;
        if (substr($raw, 0, strlen($prefix)) === $prefix) {
            $raw = substr($raw, strlen($prefix));
        }
        return preg_replace('/[^A-Z0-9]/', '', $raw) ?? '';
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

    public function qrImageUrl(string $payload, int $size = 200): string
    {
        $size = max(120, min(400, $size));
        return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
            . '&data=' . rawurlencode($payload);
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
        return ['ok' => true, 'status' => (string) ($pair['status'] ?? 'pending')];
    }

    /** @return array{ok:bool, message?:string, user?:array<string,mixed>} */
    public function pairSubmit(string $token, string $barcode): array
    {
        $pair = $this->pairRead($token);
        if ($pair === null) {
            return ['ok' => false, 'message' => __('barcode_pair_expired')];
        }
        if (($pair['status'] ?? '') !== 'pending') {
            return ['ok' => false, 'message' => __('barcode_pair_used')];
        }
        $user = $this->findUserByBarcode($barcode);
        if (!$user) {
            return ['ok' => false, 'message' => __('barcode_invalid')];
        }
        $db = Database::connection();
        $db->prepare(
            'UPDATE rateb_login_barcode_pairs SET status = \'complete\', user_id = :uid WHERE token = :t AND status = \'pending\''
        )->execute(['uid' => (int) $user['id'], 't' => $token]);
        return ['ok' => true, 'user' => $user];
    }

    public function pairConsumeUser(string $token): ?array
    {
        $pair = $this->pairRead($token);
        if ($pair === null || ($pair['status'] ?? '') !== 'complete') {
            return null;
        }
        $userId = (int) ($pair['user_id'] ?? 0);
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
        $token = preg_replace('/[^a-f0-9]/', '', strtolower($token)) ?? '';
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
}
