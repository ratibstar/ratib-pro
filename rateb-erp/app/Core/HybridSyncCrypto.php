<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Phase C — payload encryption + HMAC signatures (Core only).
 */
final class HybridSyncCrypto
{
    public static function hashPayload(string $json): string
    {
        return hash('sha256', $json);
    }

    public static function sign(string $payloadHash, string $batchUuid = ''): string
    {
        return hash_hmac('sha256', $payloadHash . '|' . $batchUuid, HybridSyncConfig::signingKey());
    }

    public static function verify(string $payloadHash, string $signature, string $batchUuid = ''): bool
    {
        $expected = self::sign($payloadHash, $batchUuid);

        return hash_equals($expected, $signature);
    }

    /** Encrypt JSON for transit. Prefers OpenSSL AES-256-CBC; HMAC keystream fallback otherwise. */
    public static function encrypt(string $plaintext): string
    {
        $iv = random_bytes(16);
        $key = HybridSyncConfig::encryptionKey();
        if (function_exists('openssl_encrypt')) {
            $cipher = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($cipher === false) {
                throw new \RuntimeException('Hybrid sync encrypt failed');
            }

            return base64_encode('o1' . $iv . $cipher);
        }

        return base64_encode('h1' . $iv . self::hmacKeystreamXor($plaintext, $key, $iv));
    }

    public static function decrypt(string $blob): string
    {
        $raw = base64_decode($blob, true);
        if ($raw === false || strlen($raw) < 18) {
            throw new \RuntimeException('Hybrid sync decrypt failed');
        }
        $ver = substr($raw, 0, 2);
        $iv = substr($raw, 2, 16);
        $cipher = substr($raw, 18);
        $key = HybridSyncConfig::encryptionKey();
        if ($ver === 'o1' && function_exists('openssl_decrypt')) {
            $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($plain === false) {
                throw new \RuntimeException('Hybrid sync decrypt failed');
            }

            return $plain;
        }
        if ($ver === 'h1') {
            return self::hmacKeystreamXor($cipher, $key, $iv);
        }
        throw new \RuntimeException('Hybrid sync decrypt failed');
    }

    private static function hmacKeystreamXor(string $data, string $key, string $iv): string
    {
        $out = '';
        $len = strlen($data);
        for ($i = 0; $i < $len; $i += 32) {
            $block = hash_hmac('sha256', $iv . pack('N', (int) ($i / 32)), $key, true);
            $chunk = substr($data, $i, 32);
            $out .= $chunk ^ substr($block, 0, strlen($chunk));
        }

        return $out;
    }

    public static function uuidV4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
