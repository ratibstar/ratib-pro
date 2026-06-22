<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\SystemSetting;

/** Resolves SMTP settings from DB, project .env, and optional mail.secrets.php */
final class MailConfigService
{
    /** @return array{host:string,port:int,encryption:string,user:string,pass:string,from_email:string,from_name:string} */
    public function resolve(): array
    {
        $settings = new SystemSetting();
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

        return [
            'host' => trim($host),
            'port' => max(1, $port),
            'encryption' => $encryption,
            'user' => trim($user),
            'pass' => $pass,
            'from_email' => trim($fromEmail),
            'from_name' => trim($fromName),
        ];
    }

    public function isReady(): bool
    {
        $cfg = $this->resolve();
        return $cfg['host'] !== '' && $cfg['user'] !== '' && $cfg['pass'] !== '';
    }

    private function envOrSetting(string $envKey, string $settingKey, SystemSetting $settings, string $default = ''): string
    {
        $env = getenv($envKey);
        if ($env !== false && trim((string) $env) !== '') {
            return trim((string) $env);
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

    private function resolvePassword(SystemSetting $settings): string
    {
        $fromEnv = getenv('RATEB_ERP_SMTP_PASS');
        if ($fromEnv !== false && trim((string) $fromEnv) !== '') {
            return trim((string) $fromEnv);
        }
        $legacy = getenv('SMTP_PASS');
        if ($legacy !== false && trim((string) $legacy) !== '') {
            return trim((string) $legacy);
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
