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
$ratibNgeniusEnvKeys = array(
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
    'NGENIUS_REDIRECT_URL' => 'https://out.ratib.sa/api/verify.php',
    'NGENIUS_CANCEL_URL' => 'https://out.ratib.sa/api/verify.php',
    'NGENIUS_CHECKOUT_CURRENCY' => 'SAR',
    'NGENIUS_USD_TO_SAR' => '3.75',
);

/* Dotenv-style files (KEY=value). Merge NGENIUS_* only. Checked: project root and DOCUMENT_ROOT/.env (never reads Designed/). */
$ratibDotenvPaths = array();
$ratibProjectRoot = dirname(__DIR__);
$ratibDotenvPaths[] = $ratibProjectRoot . DIRECTORY_SEPARATOR . '.env';
if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $ratibDoc = rtrim((string) $_SERVER['DOCUMENT_ROOT'], "/\\");
    if ($ratibDoc !== '') {
        $ratibDotenvPaths[] = $ratibDoc . DIRECTORY_SEPARATOR . '.env';
    }
}
foreach ($ratibDotenvPaths as $ratibDotenvPath) {
    if (!is_readable($ratibDotenvPath)) {
        continue;
    }
    $ratibDotLines = @file($ratibDotenvPath, FILE_IGNORE_NEW_LINES);
    if (!is_array($ratibDotLines)) {
        continue;
    }
    foreach ($ratibDotLines as $ratibDotLine) {
        $ratibDotLine = trim((string) $ratibDotLine);
        if ($ratibDotLine === '' || $ratibDotLine[0] === '#') {
            continue;
        }
        if (strncasecmp($ratibDotLine, 'export ', 7) === 0) {
            $ratibDotLine = trim(substr($ratibDotLine, 7));
        }
        if (strpos($ratibDotLine, '=') === false) {
            continue;
        }
        $ratibDotParts = explode('=', $ratibDotLine, 2);
        $ratibDotKey = trim((string) ($ratibDotParts[0] ?? ''));
        $ratibDotVal = trim((string) ($ratibDotParts[1] ?? ''));
        if (!in_array($ratibDotKey, $ratibNgeniusEnvKeys, true)) {
            continue;
        }
        $ratibDotVal = trim($ratibDotVal);
        if ($ratibDotVal === '') {
            continue;
        }
        $len = strlen($ratibDotVal);
        if ($len >= 2) {
            $q0 = $ratibDotVal[0];
            $q1 = $ratibDotVal[$len - 1];
            if (($q0 === '"' && $q1 === '"') || ($q0 === "'" && $q1 === "'")) {
                $ratibDotVal = substr($ratibDotVal, 1, -1);
            }
        }
        if ($ratibDotVal !== '') {
            $APP_ENV_DEFAULTS[$ratibDotKey] = $ratibDotVal;
        }
    }
}

/* Optional secrets files (return a PHP array; do not commit real keys). Checked: config/ then config/env/. */
$ratibNgeniusSecretPaths = array(
    __DIR__ . DIRECTORY_SEPARATOR . 'ngenius.secrets.php',
    __DIR__ . DIRECTORY_SEPARATOR . 'env' . DIRECTORY_SEPARATOR . 'ngenius.secrets.php',
);
foreach ($ratibNgeniusSecretPaths as $ratibNgeniusSecretsPath) {
    if (!is_readable($ratibNgeniusSecretsPath)) {
        continue;
    }
    try {
        $ratibNgeniusSecrets = require $ratibNgeniusSecretsPath;
    } catch (Throwable $ratibNgeniusLoadErr) {
        @error_log(
            'ratib: could not load ' . $ratibNgeniusSecretsPath . ' - ' . $ratibNgeniusLoadErr->getMessage()
        );
        continue;
    }
    if (!is_array($ratibNgeniusSecrets)) {
        continue;
    }
    // Common copy/edit typo fallback: map legacy/misspelled outlet keys to canonical name.
    if (
        (!isset($ratibNgeniusSecrets['NGENIUS_OUTLET_ID']) || trim((string) $ratibNgeniusSecrets['NGENIUS_OUTLET_ID']) === '')
        && isset($ratibNgeniusSecrets['NGENIUS_OUTIFT_ID'])
        && is_string($ratibNgeniusSecrets['NGENIUS_OUTIFT_ID'])
        && trim($ratibNgeniusSecrets['NGENIUS_OUTIFT_ID']) !== ''
    ) {
        $ratibNgeniusSecrets['NGENIUS_OUTLET_ID'] = $ratibNgeniusSecrets['NGENIUS_OUTIFT_ID'];
    }
    foreach ($ratibNgeniusEnvKeys as $ratibNgeniusKey) {
        if (
            isset($ratibNgeniusSecrets[$ratibNgeniusKey])
            && is_string($ratibNgeniusSecrets[$ratibNgeniusKey])
            && trim($ratibNgeniusSecrets[$ratibNgeniusKey]) !== ''
        ) {
            $APP_ENV_DEFAULTS[$ratibNgeniusKey] = trim($ratibNgeniusSecrets[$ratibNgeniusKey]);
        }
    }
}

/* Host file (e.g. config/env/out_ratib_sa.php) is loaded before this file - merge define('NGENIUS_*') here. */
foreach ($ratibNgeniusEnvKeys as $ratibNgeniusKey) {
    if (!defined($ratibNgeniusKey)) {
        continue;
    }
    $ratibNgeniusConst = constant($ratibNgeniusKey);
    if (is_string($ratibNgeniusConst) && trim($ratibNgeniusConst) !== '') {
        $APP_ENV_DEFAULTS[$ratibNgeniusKey] = trim($ratibNgeniusConst);
    }
}

/*
 * Re-apply secrets as final priority for N-Genius values so one file
 * (config/ngenius.secrets.php) can be the single source of truth.
 */
foreach ($ratibNgeniusSecretPaths as $ratibNgeniusSecretsPath) {
    if (!is_readable($ratibNgeniusSecretsPath)) {
        continue;
    }
    try {
        $ratibNgeniusSecretsFinal = require $ratibNgeniusSecretsPath;
    } catch (Throwable $ratibNgeniusLoadErr) {
        @error_log(
            'ratib: could not load ' . $ratibNgeniusSecretsPath . ' - ' . $ratibNgeniusLoadErr->getMessage()
        );
        continue;
    }
    if (!is_array($ratibNgeniusSecretsFinal)) {
        continue;
    }
    if (
        (!isset($ratibNgeniusSecretsFinal['NGENIUS_OUTLET_ID']) || trim((string) $ratibNgeniusSecretsFinal['NGENIUS_OUTLET_ID']) === '')
        && isset($ratibNgeniusSecretsFinal['NGENIUS_OUTIFT_ID'])
        && is_string($ratibNgeniusSecretsFinal['NGENIUS_OUTIFT_ID'])
        && trim($ratibNgeniusSecretsFinal['NGENIUS_OUTIFT_ID']) !== ''
    ) {
        $ratibNgeniusSecretsFinal['NGENIUS_OUTLET_ID'] = $ratibNgeniusSecretsFinal['NGENIUS_OUTIFT_ID'];
    }
    foreach ($ratibNgeniusEnvKeys as $ratibNgeniusKey) {
        if (
            isset($ratibNgeniusSecretsFinal[$ratibNgeniusKey])
            && is_string($ratibNgeniusSecretsFinal[$ratibNgeniusKey])
            && trim($ratibNgeniusSecretsFinal[$ratibNgeniusKey]) !== ''
        ) {
            $APP_ENV_DEFAULTS[$ratibNgeniusKey] = trim($ratibNgeniusSecretsFinal[$ratibNgeniusKey]);
        }
    }
}

/*
 * Must NOT be named getEnv: PHP treats that the same as the built-in getenv() (case-insensitive),
 * so function_exists('getEnv') is true and this wrapper would never load - then getEnv('K','')
 * calls getenv with a string 2nd arg and throws TypeError on PHP 8+.
 */
if (!function_exists('ratib_env')) {
    /**
     * Read environment variable with safe fallback.
     * Lookup order: getenv() -> $_ENV -> $_SERVER -> $default.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function ratib_env(string $key, $default = null)
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

if (!function_exists('ratib_ngenius_env')) {
    /**
     * N-Genius settings: use merged app config ($APP_ENV_DEFAULTS from env.php, secrets, host defines)
     * before getenv()/$_SERVER. Prevents cPanel "Environment Variables" from overriding out_ratib_sa.php
     * with old global sandbox URLs (common cause of HTTP 400 badTokenRequest).
     */
    function ratib_ngenius_env(string $key, $default = null)
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
