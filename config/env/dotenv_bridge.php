<?php
/**
 * Load project-root .env into getenv() — shared by main app and control panel.
 */
declare(strict_types=1);

if (!function_exists('rateb_env_load_bridge_dotenv')) {
    /**
     * @param string $path Absolute path to .env
     */
    function rateb_env_load_bridge_dotenv(string $path): void
    {
        if ($path === '' || !is_readable($path)) {
            return;
        }
        $allowed = [
            'DB_HOST',
            'DB_PORT',
            'DB_USER',
            'DB_PASS',
            'DB_NAME',
            'CONTROL_DB_HOST',
            'CONTROL_DB_PORT',
            'CONTROL_DB_USER',
            'CONTROL_DB_PASS',
            'CONTROL_PANEL_DB_NAME',
            'CONTROL_DB_NAME',
            'CONTROL_PANEL_DB_USER',
            'CONTROL_PANEL_DB_PASS',
            'RATEB_ERP_DB_NAME',
            'RATEB_ERP_DB_USER',
            'RATEB_ERP_DB_PASS',
            'RATEB_PRO_DB_NAME',
            'RATEB_DB_PREFIX',
            'RATEB_SITE_CONTENT_DB_HOST',
            'RATEB_SITE_CONTENT_DB_PORT',
            'RATEB_SITE_CONTENT_DB_USER',
            'RATEB_SITE_CONTENT_DB_PASS',
            'RATEB_SITE_CONTENT_DB_NAME',
            'RATEB_SITE_CONTENT_CACHE_FILE',
            'RATEB_SITE_CONTENT_DIAG_SECRET',
            'RATEB_SITE_CONTENT_PUBLIC_SOURCE',
            'RATEB_SITE_CONTENT_SKIP_DISK_JSON_CACHE',
            'RATEB_INFRA_SECRET_KEY',
            'RATEB_INFRA_PROVIDER_SECRET_KEY',
            'RATEB_INFRA_CPANEL_BASE_URL',
            'RATEB_INFRA_CPANEL_USERNAME',
            'RATEB_INFRA_CPANEL_API_TOKEN',
            'RATEB_INFRA_NAMECHEAP_API_USER',
            'RATEB_INFRA_NAMECHEAP_API_KEY',
            'RATEB_INFRA_NAMECHEAP_USERNAME',
            'RATEB_INFRA_NAMECHEAP_CLIENT_IP',
            'RATEB_INFRA_CLOUDFLARE_API_TOKEN',
            'RATEB_INFRA_MARKETPLACE_ENABLED',
            'MOBILE_AUTH_SECRET',
            'RATEB_ERP_MIGRATE_TOKEN',
            'CPANEL_API_TOKEN',
            'RATIB_CC_DB_NAME',
            'RATIB_CC_DB_USER',
            'RATIB_CC_DB_PASS',
            'RATIB_CC_DB_HOST',
            'RATIB_CC_DB_PORT',
            'RATEB_CC_DB_PASS',
            'RATIB_CC_WS_HOST',
            'RCC_REALTIME_HUB_HOST',
            'RCC_REALTIME_HUB_PORT',
            'RCC_REALTIME_MODE',
            'RCC_WEBSOCKET_HOST',
            'RCC_WEBSOCKET_PORT',
            'RCC_WEBSOCKET_PUBLIC_URL',
            'RATIB_CC_WS_URL',
            'RCC_AI_ASSISTANT_ENABLED',
            'RCC_AI_PROVIDER',
            'RCC_SIP_WSS_URI',
            'RCC_SIP_DOMAIN',
            'RCC_SIP_DEFAULT_PASS',
            'RATEB_ERP_SMTP_HOST',
            'RATEB_ERP_SMTP_PORT',
            'RATEB_ERP_SMTP_ENCRYPTION',
            'RATEB_ERP_SMTP_USER',
            'RATEB_ERP_SMTP_FROM_EMAIL',
            'RATEB_ERP_SMTP_FROM_NAME',
            'RATEB_ERP_SMTP_PASS',
        ];
        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (strncasecmp($line, 'export ', 7) === 0) {
                $line = trim(substr($line, 7));
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            $parts = explode('=', $line, 2);
            $key = trim((string) ($parts[0] ?? ''));
            $val = trim((string) ($parts[1] ?? ''));
            if ($key === '' || !in_array($key, $allowed, true)) {
                continue;
            }
            $len = strlen($val);
            if ($len >= 2) {
                $q0 = $val[0];
                $q1 = $val[$len - 1];
                if (($q0 === '"' && $q1 === '"') || ($q0 === "'" && $q1 === "'")) {
                    $val = substr($val, 1, -1);
                }
            }
            putenv($key . '=' . $val);
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }
}

if (!function_exists('rateb_bootstrap_project_dotenv')) {
    function rateb_bootstrap_project_dotenv(string $projectRoot): void
    {
        $root = rtrim($projectRoot, "/\\");
        if ($root === '') {
            return;
        }
        rateb_env_load_bridge_dotenv($root . DIRECTORY_SEPARATOR . '.env');
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $doc = rtrim((string) $_SERVER['DOCUMENT_ROOT'], "/\\");
            if ($doc !== '') {
                rateb_env_load_bridge_dotenv($doc . DIRECTORY_SEPARATOR . '.env');
            }
        }
    }
}
