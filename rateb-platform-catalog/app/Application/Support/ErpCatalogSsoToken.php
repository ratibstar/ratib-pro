<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

/** Signed short-lived token for ERP → catalog admin SSO (same origin). */
final class ErpCatalogSsoToken
{
    private const TTL_SECONDS = 120;

    /**
     * @param array{erp_user_id:int,email:string,super_admin:bool,portal:string} $claims
     */
    public static function issue(array $claims): string
    {
        $payload = [
            'erp_user_id' => (int) ($claims['erp_user_id'] ?? 0),
            'email' => strtolower(trim((string) ($claims['email'] ?? ''))),
            'super_admin' => !empty($claims['super_admin']),
            'portal' => (string) ($claims['portal'] ?? ''),
            'exp' => time() + self::TTL_SECONDS,
            'nonce' => bin2hex(random_bytes(8)),
        ];
        $body = self::encode($payload);
        $sig = hash_hmac('sha256', $body, self::secret());

        return $body . '.' . $sig;
    }

    /**
     * @return array{erp_user_id:int,email:string,super_admin:bool,portal:string}|null
     */
    public static function verify(string $token): ?array
    {
        $token = trim($token);
        $dot = strrpos($token, '.');
        if ($dot === false || $dot < 1) {
            return null;
        }

        $body = substr($token, 0, $dot);
        $sig = substr($token, $dot + 1);
        if ($body === '' || $sig === '') {
            return null;
        }

        $secret = self::secret();
        if ($secret === '') {
            return null;
        }

        $expected = hash_hmac('sha256', $body, $secret);
        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $payload = self::decode($body);
        if ($payload === null) {
            return null;
        }

        $exp = (int) ($payload['exp'] ?? 0);
        if ($exp < time()) {
            return null;
        }

        $erpUserId = (int) ($payload['erp_user_id'] ?? 0);
        if ($erpUserId < 1) {
            return null;
        }

        return [
            'erp_user_id' => $erpUserId,
            'email' => strtolower(trim((string) ($payload['email'] ?? ''))),
            'super_admin' => !empty($payload['super_admin']),
            'portal' => (string) ($payload['portal'] ?? ''),
        ];
    }

    public static function secret(): string
    {
        $env = getenv('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_SECRET');
        if (is_string($env) && $env !== '') {
            return $env;
        }

        if (defined('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_SECRET')
            && (string) RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_SECRET !== '') {
            return (string) RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_SECRET;
        }

        $erpToken = getenv('RATEB_ERP_MIGRATE_TOKEN');
        if (is_string($erpToken) && $erpToken !== '') {
            return $erpToken;
        }

        if (defined('RATEB_ERP_MIGRATE_TOKEN') && (string) RATEB_ERP_MIGRATE_TOKEN !== '') {
            return (string) RATEB_ERP_MIGRATE_TOKEN;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function encode(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decode(string $body): ?array
    {
        $pad = strlen($body) % 4;
        if ($pad > 0) {
            $body .= str_repeat('=', 4 - $pad);
        }
        $json = base64_decode(strtr($body, '-_', '+/'), true);
        if (!is_string($json) || $json === '') {
            return null;
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
