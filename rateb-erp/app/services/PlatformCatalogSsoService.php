<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\SessionManager;

/** Issues signed SSO tokens for rateb-platform-catalog admin (same server). */
final class PlatformCatalogSsoService
{
    public function issueTokenFromSession(): ?string
    {
        $erpUserId = (int) SessionManager::get('rateb_user_id');
        if ($erpUserId < 1) {
            return null;
        }

        return $this->issueToken([
            'erp_user_id' => $erpUserId,
            'email' => (string) SessionManager::get('rateb_user_email'),
            'super_admin' => !empty(SessionManager::get('rateb_is_super_admin')),
            'portal' => (string) SessionManager::get('rateb_portal'),
        ]);
    }

    /**
     * @param array{erp_user_id:int,email:string,super_admin:bool,portal:string} $claims
     */
    public function issueToken(array $claims): ?string
    {
        if ((int) ($claims['erp_user_id'] ?? 0) < 1) {
            return null;
        }

        $secret = $this->sharedSecret();
        if ($secret === '') {
            return null;
        }

        $payload = [
            'erp_user_id' => (int) $claims['erp_user_id'],
            'email' => strtolower(trim((string) ($claims['email'] ?? ''))),
            'super_admin' => !empty($claims['super_admin']),
            'portal' => (string) ($claims['portal'] ?? ''),
            'exp' => time() + 120,
            'nonce' => bin2hex(random_bytes(8)),
        ];

        try {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $body = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $body, $secret);

        return $body . '.' . $sig;
    }

    public static function catalogAuthCallbackUrl(string $token, string $return = ''): string
    {
        $base = function_exists('rateb_platform_catalog_admin_url')
            ? rtrim((string) rateb_platform_catalog_admin_url(), '/')
            : 'https://rateb.sa/rateb-platform-catalog/admin';
        $url = $base . '/auth/erp-sso?token=' . rawurlencode($token);
        if ($return !== '') {
            $url .= '&return=' . rawurlencode($return);
        }

        return $url;
    }

    public function sharedSecret(): string
    {
        $env = getenv('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_SECRET');
        if (is_string($env) && trim($env) !== '') {
            return trim($env);
        }

        if (defined('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_SECRET')
            && trim((string) RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_SECRET) !== '') {
            return trim((string) RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_SECRET);
        }

        $erpToken = getenv('RATEB_ERP_MIGRATE_TOKEN');
        if (is_string($erpToken) && trim($erpToken) !== '') {
            return trim($erpToken);
        }

        if (defined('RATEB_ERP_MIGRATE_TOKEN') && trim((string) RATEB_ERP_MIGRATE_TOKEN) !== '') {
            return trim((string) RATEB_ERP_MIGRATE_TOKEN);
        }

        foreach ($this->secretFileCandidates() as $file) {
            if (!is_file($file)) {
                continue;
            }
            $token = trim((string) @file_get_contents($file));
            if ($token !== '') {
                return $token;
            }
        }

        // Must match ErpCatalogSsoToken::secret() last resort on catalog side.
        return hash('sha256', 'rateb-platform-catalog-sso|rateb.sa');
    }

    /** @return list<string> */
    private function secretFileCandidates(): array
    {
        $root = defined('RATEB_ROOT') ? rtrim((string) RATEB_ROOT, '/\\') : '';
        $candidates = [];
        if ($root !== '') {
            $candidates[] = $root . '/storage/deploy-migrate-token';
            $candidates[] = $root . '/storage/.deploy-migrate-token';
            $candidates[] = dirname($root) . '/rateb-platform-catalog/storage/deploy-migrate-token';
            $candidates[] = dirname($root) . '/rateb-platform-catalog/storage/.deploy-migrate-token';
        }

        return $candidates;
    }
}
