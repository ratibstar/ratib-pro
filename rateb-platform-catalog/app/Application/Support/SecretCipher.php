<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

final class SecretCipher
{
    public static function encrypt(string $plainText): string
    {
        if (function_exists('sodium_crypto_secretbox')) {
            $key = self::sodiumKey();
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($plainText, $nonce, $key);

            return 'sodium:' . base64_encode($nonce . $cipher);
        }

        if (function_exists('openssl_encrypt')) {
            $key = self::deriveKey();
            $iv = random_bytes(16);
            $cipher = openssl_encrypt($plainText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($cipher === false) {
                throw new \RuntimeException('Unable to encrypt secret');
            }

            return 'openssl:' . base64_encode($iv . $cipher);
        }

        return 'plain:' . base64_encode($plainText);
    }

    public static function decrypt(string $encrypted): string
    {
        if (str_starts_with($encrypted, 'sodium:') && function_exists('sodium_crypto_secretbox_open')) {
            $raw = base64_decode(substr($encrypted, 7), true);
            if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
                throw new \RuntimeException('Invalid encrypted secret');
            }
            $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plain = sodium_crypto_secretbox_open($cipher, $nonce, self::sodiumKey());
            if ($plain === false) {
                throw new \RuntimeException('Unable to decrypt secret');
            }

            return $plain;
        }

        if (str_starts_with($encrypted, 'openssl:') && function_exists('openssl_decrypt')) {
            $raw = base64_decode(substr($encrypted, 8), true);
            if ($raw === false || strlen($raw) < 17) {
                throw new \RuntimeException('Invalid encrypted secret');
            }
            $iv = substr($raw, 0, 16);
            $cipher = substr($raw, 16);
            $plain = openssl_decrypt($cipher, 'AES-256-CBC', self::deriveKey(), OPENSSL_RAW_DATA, $iv);
            if ($plain === false) {
                throw new \RuntimeException('Unable to decrypt secret');
            }

            return $plain;
        }

        if (str_starts_with($encrypted, 'plain:')) {
            $decoded = base64_decode(substr($encrypted, 6), true);

            return $decoded === false ? '' : $decoded;
        }

        throw new \RuntimeException('Unsupported encrypted secret format');
    }

    private static function deriveKey(): string
    {
        $material = (string) (getenv('CATALOG_SECRET_KEY') ?: (defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT : 'rateb-catalog'));
        $hash = hash('sha256', $material, true);
        if ($hash === false) {
            throw new \RuntimeException('Unable to derive secret key');
        }

        return $hash;
    }

    private static function sodiumKey(): string
    {
        return substr(hash('sha256', self::material(), true), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    private static function material(): string
    {
        return (string) (getenv('CATALOG_SECRET_KEY') ?: (defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT : 'rateb-catalog'));
    }
}
