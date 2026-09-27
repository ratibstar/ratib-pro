<?php
declare(strict_types=1);

/**
 * Automated company-branded mobile builds (GitHub Actions reads specs from the platform API).
 *
 * Set in server env (not in git):
 *   RATEB_MOBILE_BUILD_SECRET   — shared secret for GET /api/v1/mobile/branded/specs
 *   RATEB_GITHUB_DISPATCH_TOKEN — optional; PAT with workflow scope to trigger builds from Admin
 */
if (!function_exists('rateb_mobile_build_secret')) {
    function rateb_mobile_build_secret(): string
    {
        return trim((string) (getenv('RATEB_MOBILE_BUILD_SECRET') ?: ''));
    }
}

if (!function_exists('rateb_github_dispatch_mobile_build')) {
    /** Trigger the mobile-branded-build workflow (returns false when token is not configured). */
    function rateb_github_dispatch_mobile_build(string $app = 'all'): bool
    {
        $token = trim((string) (getenv('RATEB_GITHUB_DISPATCH_TOKEN') ?: ''));
        if ($token === '') {
            return false;
        }
        $app = strtolower(trim($app));
        if ($app !== 'all') {
            $app = MobileAppApkService::normalizeApp($app);
        }
        $repo = trim((string) (getenv('RATEB_GITHUB_REPOSITORY') ?: 'ratibstar/ratib-pro'));
        $url = 'https://api.github.com/repos/' . $repo . '/actions/workflows/mobile-branded-build.yml/dispatches';
        $body = json_encode([
            'ref' => 'main',
            'inputs' => ['app' => $app === '' ? 'all' : $app],
        ], JSON_UNESCAPED_UNICODE);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Authorization: Bearer {$token}\r\n"
                    . "Accept: application/vnd.github+json\r\n"
                    . "Content-Type: application/json\r\n"
                    . "User-Agent: rateb-erp-mobile-build\r\n",
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 15,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return false;
        }
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('/\d{3}/', (string) $http_response_header[0], $m)) {
            $code = (int) $m[0];
        }

        return $code >= 200 && $code < 300;
    }
}
