<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/env.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/env.php`.
 */
declare(strict_types=1);

/**
 * Local application env defaults.
 *
 * Edit these values directly if you do not want to rely on cPanel/server env vars.
 * Server env vars still take priority over these defaults.
 */
// N-Genius: set credentials via env, ngenius.secrets.php, or host env file - never commit real secrets.
// Live-only N-Genius KSA defaults. Identity and order API must match the same live environment.
// KSA live: https://api-gateway.ksa.ngenius-payments.com with realm networkinternational.
$ratebNgeniusEnvKeys = array(
    'NGENIUS_OUTLET_ID',
    'NGENIUS_API_KEY',
    'NGENIUS_API_SECRET',
    'NGENIUS_REALM',
    'NGENIUS_IDENTITY_BASE',
    'NGENIUS_ORDER_BASE',
    'NGENIUS_API_BASE',
    'NGENIUS_TOKEN_URL',
    'NGENIUS_REDIRECT_URL',
    'NGENIUS_CANCEL_URL',
    'NGENIUS_CHECKOUT_CURRENCY',
    'NGENIUS_USD_TO_SAR',
);

$APP_ENV_DEFAULTS = array(
    'NGENIUS_OUTLET_ID' => '',
    'NGENIUS_API_KEY' => '',
    'NGENIUS_API_SECRET' => '',
    'NGENIUS_REALM' => 'networkinternational',
    'NGENIUS_IDENTITY_BASE' => 'https://api-gateway.ksa.ngenius-payments.com',
    'NGENIUS_ORDER_BASE' => 'https://api-gateway.ksa.ngenius-payments.com',
    'NGENIUS_API_BASE' => 'https://api-gateway.ksa.ngenius-payments.com',
    'NGENIUS_TOKEN_URL' => 'https://api-gateway.ksa.ngenius-payments.com/identity/auth/access-token',
    'NGENIUS_REDIRECT_URL' => 'https://rateb.sa/api/verify.php',
    'NGENIUS_CANCEL_URL' => 'https://rateb.sa/api/verify.php',
    'NGENIUS_CHECKOUT_CURRENCY' => 'SAR',
    'NGENIUS_USD_TO_SAR' => '3.75',
);

/* Dotenv-style files (KEY=value). Merge NGENIUS_* only. Checked: project root and DOCUMENT_ROOT/.env (never reads Designed/). */
$ratebDotenvPaths = array();
$ratebProjectRoot = dirname(__DIR__);
$ratebDotenvPaths[] = $ratebProjectRoot . DIRECTORY_SEPARATOR . '.env';
if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $ratebDoc = rtrim((string) $_SERVER['DOCUMENT_ROOT'], "/\\");
    if ($ratebDoc !== '') {
        $ratebDotenvPaths[] = $ratebDoc . DIRECTORY_SEPARATOR . '.env';
    }
}
foreach ($ratebDotenvPaths as $ratebDotenvPath) {
    if (!is_readable($ratebDotenvPath)) {
        continue;
    }
    $ratebDotLines = @file($ratebDotenvPath, FILE_IGNORE_NEW_LINES);
    if (!is_array($ratebDotLines)) {
        continue;
    }
    foreach ($ratebDotLines as $ratebDotLine) {
        $ratebDotLine = trim((string) $ratebDotLine);
        if ($ratebDotLine === '' || $ratebDotLine[0] === '#') {
            continue;
        }
        if (strncasecmp($ratebDotLine, 'export ', 7) === 0) {
            $ratebDotLine = trim(substr($ratebDotLine, 7));
        }
        if (strpos($ratebDotLine, '=') === false) {
            continue;
        }
        $ratebDotParts = explode('=', $ratebDotLine, 2);
        $ratebDotKey = trim((string) ($ratebDotParts[0] ?? ''));
        $ratebDotVal = trim((string) ($ratebDotParts[1] ?? ''));
        if (!in_array($ratebDotKey, $ratebNgeniusEnvKeys, true)) {
            continue;
        }
        $ratebDotVal = trim($ratebDotVal);
        if ($ratebDotVal === '') {
            continue;
        }
        $len = strlen($ratebDotVal);
        if ($len >= 2) {
            $q0 = $ratebDotVal[0];
            $q1 = $ratebDotVal[$len - 1];
            if (($q0 === '"' && $q1 === '"') || ($q0 === "'" && $q1 === "'")) {
                $ratebDotVal = substr($ratebDotVal, 1, -1);
            }
        }
        if ($ratebDotVal !== '') {
            $APP_ENV_DEFAULTS[$ratebDotKey] = $ratebDotVal;
        }
    }
}

/* Optional secrets files (return a PHP array; do not commit real keys). Checked: config/ then config/env/. */
$ratebNgeniusSecretPaths = array(
    __DIR__ . DIRECTORY_SEPARATOR . 'ngenius.secrets.php',
    __DIR__ . DIRECTORY_SEPARATOR . 'env' . DIRECTORY_SEPARATOR . 'ngenius.secrets.php',
);
foreach ($ratebNgeniusSecretPaths as $ratebNgeniusSecretsPath) {
    if (!is_readable($ratebNgeniusSecretsPath)) {
        continue;
    }
    try {
        $ratebNgeniusSecrets = require $ratebNgeniusSecretsPath;
    } catch (Throwable $ratebNgeniusLoadErr) {
        @error_log(
            'rateb: could not load ' . $ratebNgeniusSecretsPath . ' - ' . $ratebNgeniusLoadErr->getMessage()
        );
        continue;
    }
    if (!is_array($ratebNgeniusSecrets)) {
        continue;
    }
    // Common copy/edit typo fallback: map legacy/misspelled outlet keys to canonical name.
    if (
        (!isset($ratebNgeniusSecrets['NGENIUS_OUTLET_ID']) || trim((string) $ratebNgeniusSecrets['NGENIUS_OUTLET_ID']) === '')
        && isset($ratebNgeniusSecrets['NGENIUS_OUTIFT_ID'])
        && is_string($ratebNgeniusSecrets['NGENIUS_OUTIFT_ID'])
        && trim($ratebNgeniusSecrets['NGENIUS_OUTIFT_ID']) !== ''
    ) {
        $ratebNgeniusSecrets['NGENIUS_OUTLET_ID'] = $ratebNgeniusSecrets['NGENIUS_OUTIFT_ID'];
    }
    foreach ($ratebNgeniusEnvKeys as $ratebNgeniusKey) {
        if (
            isset($ratebNgeniusSecrets[$ratebNgeniusKey])
            && is_string($ratebNgeniusSecrets[$ratebNgeniusKey])
            && trim($ratebNgeniusSecrets[$ratebNgeniusKey]) !== ''
        ) {
            $APP_ENV_DEFAULTS[$ratebNgeniusKey] = trim($ratebNgeniusSecrets[$ratebNgeniusKey]);
        }
    }
}

/* Host file (e.g. config/env/rateb_sa.php) is loaded before this file - merge define('NGENIUS_*') here. */
foreach ($ratebNgeniusEnvKeys as $ratebNgeniusKey) {
    if (!defined($ratebNgeniusKey)) {
        continue;
    }
    $ratebNgeniusConst = constant($ratebNgeniusKey);
    if (is_string($ratebNgeniusConst) && trim($ratebNgeniusConst) !== '') {
        $APP_ENV_DEFAULTS[$ratebNgeniusKey] = trim($ratebNgeniusConst);
    }
}

/*
 * Re-apply secrets as final priority for N-Genius values so one file
 * (config/ngenius.secrets.php) can be the single source of truth.
 */
foreach ($ratebNgeniusSecretPaths as $ratebNgeniusSecretsPath) {
    if (!is_readable($ratebNgeniusSecretsPath)) {
        continue;
    }
    try {
        $ratebNgeniusSecretsFinal = require $ratebNgeniusSecretsPath;
    } catch (Throwable $ratebNgeniusLoadErr) {
        @error_log(
            'rateb: could not load ' . $ratebNgeniusSecretsPath . ' - ' . $ratebNgeniusLoadErr->getMessage()
        );
        continue;
    }
    if (!is_array($ratebNgeniusSecretsFinal)) {
        continue;
    }
    if (
        (!isset($ratebNgeniusSecretsFinal['NGENIUS_OUTLET_ID']) || trim((string) $ratebNgeniusSecretsFinal['NGENIUS_OUTLET_ID']) === '')
        && isset($ratebNgeniusSecretsFinal['NGENIUS_OUTIFT_ID'])
        && is_string($ratebNgeniusSecretsFinal['NGENIUS_OUTIFT_ID'])
        && trim($ratebNgeniusSecretsFinal['NGENIUS_OUTIFT_ID']) !== ''
    ) {
        $ratebNgeniusSecretsFinal['NGENIUS_OUTLET_ID'] = $ratebNgeniusSecretsFinal['NGENIUS_OUTIFT_ID'];
    }
    foreach ($ratebNgeniusEnvKeys as $ratebNgeniusKey) {
        if (
            isset($ratebNgeniusSecretsFinal[$ratebNgeniusKey])
            && is_string($ratebNgeniusSecretsFinal[$ratebNgeniusKey])
            && trim($ratebNgeniusSecretsFinal[$ratebNgeniusKey]) !== ''
        ) {
            $APP_ENV_DEFAULTS[$ratebNgeniusKey] = trim($ratebNgeniusSecretsFinal[$ratebNgeniusKey]);
        }
    }
}

/* Infrastructure marketplace secrets (config/infra.secrets.php — gitignored). */
$ratebInfraSecretPaths = [
    __DIR__ . DIRECTORY_SEPARATOR . 'infra.secrets.php',
    __DIR__ . DIRECTORY_SEPARATOR . 'env' . DIRECTORY_SEPARATOR . 'infra.secrets.php',
];
$ratebInfraEnvKeys = [
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
];
foreach ($ratebInfraSecretPaths as $ratebInfraSecretsPath) {
    if (!is_readable($ratebInfraSecretsPath)) {
        continue;
    }
    try {
        $ratebInfraSecrets = require $ratebInfraSecretsPath;
    } catch (Throwable $ratebInfraLoadErr) {
        @error_log('rateb: could not load ' . $ratebInfraSecretsPath . ' - ' . $ratebInfraLoadErr->getMessage());
        continue;
    }
    if (!is_array($ratebInfraSecrets)) {
        continue;
    }
    foreach ($ratebInfraEnvKeys as $ratebInfraKey) {
        if (
            isset($ratebInfraSecrets[$ratebInfraKey])
            && is_string($ratebInfraSecrets[$ratebInfraKey])
            && trim($ratebInfraSecrets[$ratebInfraKey]) !== ''
        ) {
            $APP_ENV_DEFAULTS[$ratebInfraKey] = trim($ratebInfraSecrets[$ratebInfraKey]);
        }
    }
    break;
}

/*
 * Must NOT be named getEnv: PHP treats that the same as the built-in getenv() (case-insensitive),
 * so function_exists('getEnv') is true and this wrapper would never load - then getEnv('K','')
 * calls getenv with a string 2nd arg and throws TypeError on PHP 8+.
 */
if (!function_exists('rateb_env')) {
    /**
     * Read environment variable with safe fallback.
     * Lookup order: getenv() -> $_ENV -> $_SERVER -> $default.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function rateb_env(string $key, $default = null)
    {
        global $APP_ENV_DEFAULTS;

        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return is_string($value) ? trim($value) : $value;
        }

        if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
            $envValue = $_ENV[$key];
            return is_string($envValue) ? trim($envValue) : $envValue;
        }

        if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== '') {
            $serverValue = $_SERVER[$key];
            return is_string($serverValue) ? trim($serverValue) : $serverValue;
        }

        if (is_array($APP_ENV_DEFAULTS) && array_key_exists($key, $APP_ENV_DEFAULTS)) {
            $localValue = $APP_ENV_DEFAULTS[$key];
            if ($localValue !== '' && $localValue !== null) {
                return is_string($localValue) ? trim($localValue) : $localValue;
            }
        }

        return $default;
    }
}

if (!function_exists('rateb_ngenius_env')) {
    /**
     * N-Genius settings: use merged app config ($APP_ENV_DEFAULTS from env.php, secrets, host defines)
     * before getenv()/$_SERVER. Prevents cPanel "Environment Variables" from overriding rateb_sa.php
     * with old global sandbox URLs (common cause of HTTP 400 badTokenRequest).
     */
    function rateb_ngenius_env(string $key, $default = null)
    {
        global $APP_ENV_DEFAULTS;

        if (is_array($APP_ENV_DEFAULTS) && array_key_exists($key, $APP_ENV_DEFAULTS)) {
            $localValue = $APP_ENV_DEFAULTS[$key];
            if ($localValue !== '' && $localValue !== null) {
                return is_string($localValue) ? trim($localValue) : $localValue;
            }
        }

        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return is_string($value) ? trim($value) : $value;
        }

        if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
            $envValue = $_ENV[$key];
            return is_string($envValue) ? trim($envValue) : $envValue;
        }

        if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== '') {
            $serverValue = $_SERVER[$key];
            return is_string($serverValue) ? trim($serverValue) : $serverValue;
        }

        return $default;
    }
}
