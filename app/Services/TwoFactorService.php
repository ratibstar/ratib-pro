<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class TwoFactorService
{
    /** @var array<string, bool> */
    private array $colCache = [];

    public function __construct(private readonly PDO $db)
    {
    }

    /** @param array<string, mixed> $user */
    public function roleRequires2FA(array $user): bool
    {
        $roleId = (int) ($user['role_id'] ?? 0);
        if ($roleId <= 0) {
            return false;
        }
        if (!$this->columnExists('roles', 'id') || !$this->columnExists('roles', 'require_2fa')) {
            return false;
        }
        $st = $this->db->prepare('SELECT require_2fa FROM roles WHERE id = :id LIMIT 1');
        $st->execute([':id' => $roleId]);
        return ((int) ($st->fetchColumn() ?: 0)) === 1;
    }

    /** @param array<string, mixed> $user */
    public function verifyFromRequest(array $user): bool
    {
        $userId = (int) ($user['id'] ?? $user['user_id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }
        $secret = $this->loadUserTotpSecret($userId);
        if ($secret === '') {
            return false;
        }
        $code = trim((string) ($_SERVER['HTTP_X_TOTP_CODE'] ?? ''));
        if ($code === '' && isset($_POST['totp_code'])) {
            $code = trim((string) $_POST['totp_code']);
        }
        if ($code === '') {
            $raw = json_decode((string) file_get_contents('php://input'), true);
            if (is_array($raw) && isset($raw['totp_code'])) {
                $code = trim((string) $raw['totp_code']);
            }
        }
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        return $this->verifyTotpCode($secret, $code);
    }

    public function verifyTotpCode(string $base32Secret, string $code): bool
    {
        $secret = $this->decodeBase32($base32Secret);
        if ($secret === '') {
            return false;
        }
        $ts = (int) floor(time() / 30);
        for ($i = -1; $i <= 1; $i++) {
            $counter = pack('N*', 0, $ts + $i);
            $hash = hash_hmac('sha1', $counter, $secret, true);
            $offset = ord(substr($hash, -1)) & 0x0F;
            $slice = substr($hash, $offset, 4);
            $value = unpack('N', $slice)[1] & 0x7FFFFFFF;
            $otp = str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
            if (hash_equals($otp, $code)) {
                return true;
            }
        }

        return false;
    }

    private function loadUserTotpSecret(int $userId): string
    {
        if (!$this->columnExists('user_security_profiles', 'user_id') || !$this->columnExists('user_security_profiles', 'totp_secret')) {
            return '';
        }
        $st = $this->db->prepare('SELECT totp_secret FROM user_security_profiles WHERE user_id = :user_id LIMIT 1');
        $st->execute([':user_id' => $userId]);
        return trim((string) ($st->fetchColumn() ?: ''));
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->colCache)) {
            return $this->colCache[$key];
        }
        $st = $this->db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name');
        $st->execute([':table_name' => $table, ':column_name' => $column]);
        $exists = ((int) $st->fetchColumn()) > 0;
        $this->colCache[$key] = $exists;
        return $exists;
    }

    private function decodeBase32(string $input): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper(preg_replace('/[^A-Z2-7]/', '', $input) ?? '');
        if ($input === '') {
            return '';
        }
        $bits = '';
        $len = strlen($input);
        for ($i = 0; $i < $len; $i++) {
            $val = strpos($alphabet, $input[$i]);
            if ($val === false) {
                return '';
            }
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        $bitLen = strlen($bits);
        for ($i = 0; $i + 8 <= $bitLen; $i += 8) {
            $out .= chr(bindec(substr($bits, $i, 8)));
        }

        return $out;
    }
}
