<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Security\Secrets;

/**
 * Symmetric crypto helper for provider secret persistence.
 * Ciphertext format is base64(JSON{v,iv,ct}) to keep migration-safe decoding.
 */
final class ProviderSecretCipher
{
    private const CIPHER = 'aes-256-cbc';

    public function encrypt(string $plain): string
    {
        if ($plain === '') {
            throw new \RuntimeException('Secret value cannot be empty.');
        }
        if (!function_exists('openssl_encrypt')) {
            throw new \RuntimeException('OpenSSL extension is required for provider secret encryption.');
        }
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        if (!is_int($ivLen) || $ivLen <= 0) {
            throw new \RuntimeException('Unable to determine OpenSSL IV length.');
        }
        $iv = random_bytes($ivLen);
        $ct = openssl_encrypt($plain, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv);
        if (!is_string($ct) || $ct === '') {
            throw new \RuntimeException('Secret encryption failed.');
        }
        $payload = [
            'v' => 1,
            'iv' => base64_encode($iv),
            'ct' => base64_encode($ct),
        ];

        return base64_encode((string) json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    public function decrypt(string $encoded): ?string
    {
        if (!function_exists('openssl_decrypt') || trim($encoded) === '') {
            return null;
        }
        $json = base64_decode(trim($encoded), true);
        if (!is_string($json) || $json === '') {
            return null;
        }
        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return null;
        }
        $ivRaw = isset($payload['iv']) ? base64_decode((string) $payload['iv'], true) : false;
        $ctRaw = isset($payload['ct']) ? base64_decode((string) $payload['ct'], true) : false;
        if (!is_string($ivRaw) || $ivRaw === '' || !is_string($ctRaw) || $ctRaw === '') {
            return null;
        }
        $plain = openssl_decrypt($ctRaw, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $ivRaw);
        if (!is_string($plain) || $plain === '') {
            return null;
        }

        return $plain;
    }

    private function key(): string
    {
        if (class_exists('RATEB\\InfrastructureMarketplace\\Infrastructure\\InfraEnvBootstrap')) {
            \RATEB\InfrastructureMarketplace\Infrastructure\InfraEnvBootstrap::load();
        }
        $raw = getenv('RATEB_INFRA_SECRET_KEY');
        if (!is_string($raw) || trim($raw) === '') {
            $raw = getenv('RATEB_INFRA_PROVIDER_SECRET_KEY');
        }
        if (!is_string($raw) || trim($raw) === '') {
            throw new \RuntimeException('Provider secret encryption key is missing (RATEB_INFRA_SECRET_KEY).');
        }

        return hash('sha256', trim($raw), true);
    }
}
