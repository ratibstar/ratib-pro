<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\SystemSetting;

/** Resolves SMTP settings from DB, project .env, and optional mail.secrets.php */
final class MailConfigService
{
    private static bool $repairEnabled = true;

    /** Read-only mode for diagnostic tools; does not affect normal requests. */
    public static function setRepairEnabled(bool $enabled): void
    {
        self::$repairEnabled = $enabled;
    }

    /** @var list<string> */
    private const MAIL_ENV_KEYS = [
        'RATEB_ERP_SMTP_HOST',
        'RATEB_ERP_SMTP_PORT',
        'RATEB_ERP_SMTP_ENCRYPTION',
        'RATEB_ERP_SMTP_USER',
        'RATEB_ERP_SMTP_FROM_EMAIL',
        'RATEB_ERP_SMTP_FROM_NAME',
        'RATEB_ERP_SMTP_PASS',
        'SMTP_PASS',
    ];

    /** @return array{host:string,port:int,encryption:string,user:string,pass:string,from_email:string,from_name:string} */
    public function resolve(): array
    {
        $this->bootstrapMailEnvFromDotenvFile();
        $settings = new SystemSetting();
        if (self::$repairEnabled) {
            $this->repairSwappedSmtpInDb($settings);
            $this->repairMailHostFromEnv($settings);
        }
        return $this->buildConfig($settings);
    }

    /**
     * Read-only variant that also reports the source of each resolved value.
     *
     * @return array{config:array<string,mixed>,sources:array<string,string>}
     */
    public function resolveWithSources(): array
    {
        $this->bootstrapMailEnvFromDotenvFile();
        $settings = new SystemSetting();

        $config = $this->buildConfig($settings);
        $sources = [
            'host' => $this->sourceOf('RATEB_ERP_SMTP_HOST', 'smtp_host', $settings, 'mail.rateb.sa'),
            'port' => $this->sourceOf('RATEB_ERP_SMTP_PORT', 'smtp_port', $settings, '587'),
            'encryption' => $this->sourceOf('RATEB_ERP_SMTP_ENCRYPTION', 'smtp_encryption', $settings, 'tls'),
            'user' => $this->sourceOf('RATEB_ERP_SMTP_USER', 'smtp_user', $settings, 'info@rateb.sa'),
            'from_email' => $this->sourceOf('RATEB_ERP_SMTP_FROM_EMAIL', 'smtp_from_email', $settings, 'info@rateb.sa'),
            'from_name' => $this->sourceOf('RATEB_ERP_SMTP_FROM_NAME', 'smtp_from_name', $settings, 'Rateb ERP'),
            'password' => $this->passwordSource($settings),
        ];

        return ['config' => $config, 'sources' => $sources];
    }

    /** @return array{host:string,port:int,encryption:string,user:string,pass:string,from_email:string,from_name:string} */
    private function buildConfig(SystemSetting $settings): array
    {
        $host = $this->envOrSetting('RATEB_ERP_SMTP_HOST', 'smtp_host', $settings, 'mail.rateb.sa');
        $port = (int) $this->envOrSetting('RATEB_ERP_SMTP_PORT', 'smtp_port', $settings, '587');
        $encryption = strtolower($this->envOrSetting('RATEB_ERP_SMTP_ENCRYPTION', 'smtp_encryption', $settings, 'tls'));
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            $encryption = 'tls';
        }
        $user = $this->envOrSetting('RATEB_ERP_SMTP_USER', 'smtp_user', $settings, 'info@rateb.sa');
        $fromEmail = $this->envOrSetting('RATEB_ERP_SMTP_FROM_EMAIL', 'smtp_from_email', $settings, 'info@rateb.sa');
        $fromName = $this->envOrSetting('RATEB_ERP_SMTP_FROM_NAME', 'smtp_from_name', $settings, 'Rateb ERP');
        $pass = $this->resolvePassword($settings);

        // Agency DBs often keep smtp_host=localhost after sync — that accepts mail but
        // never delivers to Gmail. Prefer the shared rateb.sa MTA when credentials exist.
        $host = trim($host);
        if ($this->isLocalRelayHost($host) && $pass !== '' && trim($user) !== '') {
            $host = 'mail.rateb.sa';
            if ($port < 1 || $port === 25) {
                $port = 587;
            }
            if ($encryption === 'none') {
                $encryption = 'tls';
            }
        }

        return [
            'host' => $host,
            'port' => max(1, $port),
            'encryption' => $encryption,
            'user' => trim($user),
            'pass' => $pass,
            'from_email' => trim($fromEmail),
            'from_name' => trim($fromName),
        ];
    }

    public function isLocalRelayHost(string $host): bool
    {
        return in_array(strtolower(trim($host)), ['localhost', '127.0.0.1', '::1'], true);
    }

    public function isSmtpRelayHost(string $host): bool
    {
        $h = strtolower(trim($host));
        if ($h === '' || $this->isLocalRelayHost($h) || $h === 'mail.rateb.sa') {
            return false;
        }
        foreach (['sendgrid.net', 'mailgun.org', 'amazonaws.com', 'resend.com', 'smtp2go.com', 'elasticemail.com', 'brevo.com'] as $marker) {
            if (str_contains($h, $marker)) {
                return true;
            }
        }
        return false;
    }

    public function isReady(): bool
    {
        $cfg = $this->resolve();
        return $cfg['host'] !== '' && $cfg['user'] !== '' && $cfg['pass'] !== '';
    }

    private function bootstrapMailEnvFromDotenvFile(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;
        foreach ($this->dotenvPaths() as $path) {
            if (!is_readable($path)) {
                continue;
            }
            $lines = @file($path, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }
            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                if (strpos($line, '=') === false) {
                    continue;
                }
                [$key, $val] = explode('=', $line, 2);
                $key = trim($key);
                if ($key === '' || !in_array($key, self::MAIL_ENV_KEYS, true)) {
                    continue;
                }
                if (getenv($key) !== false && trim((string) getenv($key)) !== '') {
                    continue;
                }
                $val = trim($val, " \t\"'");
                if ($val !== '') {
                    putenv($key . '=' . $val);
                    $_ENV[$key] = $val;
                    $_SERVER[$key] = $val;
                }
            }
        }
    }

    /** @return list<string> */
    private function dotenvPaths(): array
    {
        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $parent = dirname($root);
        $paths = [$parent . '/.env', $root . '/.env'];
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $paths[] = rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') . '/.env';
        }
        return array_values(array_unique($paths));
    }

    private function envOrSetting(string $envKey, string $settingKey, SystemSetting $settings, string $default = ''): string
    {
        $env = getenv($envKey);
        if ($env !== false && trim((string) $env) !== '') {
            return trim((string) $env);
        }
        $dotenv = $this->readDotenvValue($envKey);
        if ($dotenv !== '') {
            return $dotenv;
        }
        $legacy = getenv('SMTP_' . strtoupper(substr($settingKey, 5)));
        if ($legacy !== false && trim((string) $legacy) !== '' && str_starts_with($settingKey, 'smtp_')) {
            return trim((string) $legacy);
        }
        $val = $settings->get($settingKey);
        if ($val !== null && trim((string) $val) !== '') {
            return trim((string) $val);
        }
        return $default;
    }

    private function readDotenvValue(string $key): string
    {
        foreach ($this->dotenvPaths() as $path) {
            if (!is_readable($path)) {
                continue;
            }
            $lines = @file($path, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }
            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                    continue;
                }
                [$k, $val] = explode('=', $line, 2);
                if (trim($k) !== $key) {
                    continue;
                }
                return trim($val, " \t\"'");
            }
        }
        return '';
    }

    private function sourceOf(string $envKey, string $settingKey, SystemSetting $settings, string $default = ''): string
    {
        $env = getenv($envKey);
        if ($env !== false && trim((string) $env) !== '') {
            if ($this->dotenvValueMatches($envKey, trim((string) $env))) {
                return '.env';
            }
            return 'env';
        }
        $legacy = getenv('SMTP_' . strtoupper(substr($settingKey, 5)));
        if ($legacy !== false && trim((string) $legacy) !== '' && str_starts_with($settingKey, 'smtp_')) {
            return 'env';
        }
        $val = $settings->get($settingKey);
        if ($val !== null && trim((string) $val) !== '') {
            return 'database';
        }
        if ($default !== '') {
            return 'default';
        }
        return 'missing';
    }

    private function passwordSource(SystemSetting $settings): string
    {
        $env = getenv('RATEB_ERP_SMTP_PASS');
        if ($env !== false && trim((string) $env) !== '') {
            if ($this->dotenvValueMatches('RATEB_ERP_SMTP_PASS', trim((string) $env))) {
                return '.env';
            }
            return 'env';
        }
        $legacy = getenv('SMTP_PASS');
        if ($legacy !== false && trim((string) $legacy) !== '') {
            return 'env';
        }
        $db = (string) ($settings->get('smtp_pass', '') ?? '');
        if (trim($db) !== '') {
            return 'database';
        }
        foreach ($this->secretPaths() as $path) {
            if (!is_file($path)) {
                continue;
            }
            $data = require $path;
            if (!is_array($data)) {
                continue;
            }
            $pass = trim((string) ($data['RATEB_ERP_SMTP_PASS'] ?? $data['SMTP_PASS'] ?? $data['smtp_pass'] ?? ''));
            if ($pass !== '') {
                return 'mail.secrets.php';
            }
        }
        return 'missing';
    }

    private function dotenvValueMatches(string $key, string $value): bool
    {
        foreach ($this->dotenvPaths() as $path) {
            if (!is_readable($path)) {
                continue;
            }
            $lines = @file($path, FILE_IGNORE_NEW_LINES);
            if (!is_array($lines)) {
                continue;
            }
            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                if (trim($k) !== $key) {
                    continue;
                }
                $v = trim($v, " \t\"'");
                if ($v === $value) {
                    return true;
                }
            }
        }
        return false;
    }

    private function resolvePassword(SystemSetting $settings): string
    {
        $fromEnv = getenv('RATEB_ERP_SMTP_PASS');
        if ($fromEnv !== false && trim((string) $fromEnv) !== '') {
            return trim((string) $fromEnv);
        }
        $fromDotenv = $this->readDotenvValue('RATEB_ERP_SMTP_PASS');
        if ($fromDotenv !== '') {
            return $fromDotenv;
        }
        $legacy = getenv('SMTP_PASS');
        if ($legacy !== false && trim((string) $legacy) !== '') {
            return trim((string) $legacy);
        }
        $legacyDotenv = $this->readDotenvValue('SMTP_PASS');
        if ($legacyDotenv !== '') {
            return $legacyDotenv;
        }
        $db = (string) ($settings->get('smtp_pass', '') ?? '');
        if (trim($db) !== '') {
            return trim($db);
        }
        foreach ($this->secretPaths() as $path) {
            if (!is_file($path)) {
                continue;
            }
            $data = require $path;
            if (!is_array($data)) {
                continue;
            }
            $pass = trim((string) ($data['RATEB_ERP_SMTP_PASS'] ?? $data['SMTP_PASS'] ?? $data['smtp_pass'] ?? ''));
            if ($pass !== '') {
                return $pass;
            }
        }
        return '';
    }

    private function repairMailHostFromEnv(SystemSetting $settings): void
    {
        $envHost = trim((string) (getenv('RATEB_ERP_SMTP_HOST') ?: $this->readDotenvValue('RATEB_ERP_SMTP_HOST')));
        if ($envHost === '' || $this->isLocalRelayHost($envHost)) {
            // No usable env host — still lift DB localhost → mail.rateb.sa when password exists.
            $dbHost = trim((string) ($settings->get('smtp_host', '') ?? ''));
            $pass = $this->resolvePassword($settings);
            $user = trim((string) ($settings->get('smtp_user', '') ?? 'info@rateb.sa'));
            if ($this->isLocalRelayHost($dbHost === '' ? 'localhost' : $dbHost) && $pass !== '' && $user !== '') {
                $this->upsertSetting($settings, 'smtp_host', 'mail.rateb.sa');
                $dbPort = trim((string) ($settings->get('smtp_port', '') ?? ''));
                if ($dbPort === '' || $dbPort === '25') {
                    $this->upsertSetting($settings, 'smtp_port', '587');
                }
                $dbEnc = strtolower(trim((string) ($settings->get('smtp_encryption', '') ?? '')));
                if ($dbEnc === '' || $dbEnc === 'none') {
                    $this->upsertSetting($settings, 'smtp_encryption', 'tls');
                }
            }
            return;
        }
        $dbHost = trim((string) ($settings->get('smtp_host', '') ?? ''));
        if ($dbHost !== '' && !$this->isLocalRelayHost($dbHost)) {
            return;
        }
        if ($dbHost === '' || $this->isLocalRelayHost($dbHost)) {
            $this->upsertSetting($settings, 'smtp_host', $envHost);
        }
        $envPort = trim((string) (getenv('RATEB_ERP_SMTP_PORT') ?: $this->readDotenvValue('RATEB_ERP_SMTP_PORT')));
        if ($envPort !== '' && ctype_digit($envPort)) {
            $dbPort = trim((string) ($settings->get('smtp_port', '') ?? ''));
            if ($dbPort === '' || ($dbPort === '465' && $envPort === '587')) {
                $this->upsertSetting($settings, 'smtp_port', $envPort);
            }
        }
        $envEnc = strtolower(trim((string) (getenv('RATEB_ERP_SMTP_ENCRYPTION') ?: $this->readDotenvValue('RATEB_ERP_SMTP_ENCRYPTION'))));
        if ($envEnc !== '' && in_array($envEnc, ['tls', 'ssl', 'none'], true)) {
            $dbEnc = strtolower(trim((string) ($settings->get('smtp_encryption', '') ?? '')));
            if ($dbEnc === '' || !in_array($dbEnc, ['tls', 'ssl', 'none'], true)) {
                $this->upsertSetting($settings, 'smtp_encryption', $envEnc);
            }
        }
    }

    private function repairSwappedSmtpInDb(SystemSetting $settings): void
    {
        static $repaired = false;
        if ($repaired) {
            return;
        }
        $repaired = true;
        $portRaw = trim((string) ($settings->get('smtp_port', '') ?? ''));
        $encRaw = trim((string) ($settings->get('smtp_encryption', '') ?? ''));
        $portIsEnc = in_array(strtolower($portRaw), ['tls', 'ssl', 'none'], true);
        $encIsPort = $encRaw !== '' && ctype_digit($encRaw);
        if ($portIsEnc && $encIsPort) {
            $this->upsertSetting($settings, 'smtp_port', $encRaw);
            $this->upsertSetting($settings, 'smtp_encryption', strtolower($portRaw));
            return;
        }
        if ($portRaw !== '' && !ctype_digit($portRaw)) {
            $this->upsertSetting($settings, 'smtp_port', '587');
        }
        if ($encRaw !== '' && !in_array(strtolower($encRaw), ['tls', 'ssl', 'none'], true)) {
            $this->upsertSetting($settings, 'smtp_encryption', 'tls');
        }
    }

    private function upsertSetting(SystemSetting $settings, string $key, string $value): void
    {
        $row = $settings->queryOne('SELECT id FROM rateb_system_settings WHERE setting_key = :k LIMIT 1', ['k' => $key]);
        if ($row) {
            $settings->update((int) $row['id'], ['setting_value' => $value]);
        } else {
            $settings->create(['setting_key' => $key, 'setting_value' => $value, 'setting_group' => 'mail']);
        }
    }

    /** @return list<string> */
    private function secretPaths(): array
    {
        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $parent = dirname($root);
        return [
            $root . '/config/mail.secrets.php',
            $parent . '/config/mail.secrets.php',
            $parent . '/config/env/mail.secrets.php',
        ];
    }
}
