<?php
declare(strict_types=1);

namespace Rateb\App\Payment;

use Rateb\App\Core\Database;
use PDO;

final class PaymentConfigService
{
    private const GATEWAY = 'moyasar';

    /** @var array<string, string>|null */
    private static ?array $fileSecrets = null;

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $configFile = $root . '/config/payment.php';
        if (is_file($configFile)) {
            $cfg = require $configFile;

            return is_array($cfg) ? $cfg : [];
        }

        return [];
    }

    public function isEnabled(string $slug = self::GATEWAY, ?int $companyId = null): bool
    {
        $row = $this->settingsRow($slug, $companyId);
        if ($row !== null) {
            return (int) ($row['enabled'] ?? 0) === 1;
        }

        return (bool) filter_var($this->fileSecrets()['MOYASAR_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public function mode(string $slug = self::GATEWAY, ?int $companyId = null): string
    {
        $row = $this->settingsRow($slug, $companyId);
        if ($row !== null && !empty($row['mode'])) {
            return (string) $row['mode'];
        }

        return (string) ($this->fileSecrets()['MOYASAR_MODE'] ?? 'sandbox');
    }

    public function secretKey(string $slug = self::GATEWAY, ?int $companyId = null): string
    {
        $row = $this->settingsRow($slug, $companyId);
        if ($row !== null && !empty($row['secret_key_enc'])) {
            $dec = $this->decrypt((string) $row['secret_key_enc']);
            if ($dec !== '') {
                return $dec;
            }
        }

        return (string) ($this->fileSecrets()['MOYASAR_SECRET_KEY'] ?? '');
    }

    public function publishableKey(string $slug = self::GATEWAY, ?int $companyId = null): string
    {
        $row = $this->settingsRow($slug, $companyId);
        if ($row !== null && !empty($row['publishable_key_enc'])) {
            $dec = $this->decrypt((string) $row['publishable_key_enc']);
            if ($dec !== '') {
                return $dec;
            }
        }

        return (string) ($this->fileSecrets()['MOYASAR_PUBLISHABLE_KEY'] ?? '');
    }

    public function webhookSecret(string $slug = self::GATEWAY, ?int $companyId = null): string
    {
        $row = $this->settingsRow($slug, $companyId);
        if ($row !== null && !empty($row['webhook_secret_enc'])) {
            $dec = $this->decrypt((string) $row['webhook_secret_enc']);
            if ($dec !== '') {
                return $dec;
            }
        }

        return (string) ($this->fileSecrets()['MOYASAR_WEBHOOK_SECRET'] ?? '');
    }

    public function callbackUrl(?int $companyId = null): string
    {
        $row = $this->settingsRow(self::GATEWAY, $companyId);
        if ($row !== null && !empty($row['callback_url'])) {
            return (string) $row['callback_url'];
        }
        $defaults = $this->defaults();
        $template = (string) ($defaults['callback_url'] ?? 'site/customer/finance/payment/callback');

        return function_exists('rateb_url') ? rateb_url($template) : $template;
    }

    public function webhookUrl(?int $companyId = null): string
    {
        $row = $this->settingsRow(self::GATEWAY, $companyId);
        if ($row !== null && !empty($row['webhook_url'])) {
            return (string) $row['webhook_url'];
        }
        $defaults = $this->defaults();
        $template = (string) ($defaults['webhook_url'] ?? 'api/v1/payments/webhooks/moyasar');

        return function_exists('rateb_url') ? rateb_url($template) : $template;
    }

    public function maskSecret(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (strlen($value) <= 8) {
            return '****';
        }

        return '****' . substr($value, -4);
    }

    public function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        $key = $this->encryptionKey();
        if ($key === '') {
            return base64_encode($plain);
        }
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return base64_encode($plain);
        }

        return base64_encode($iv . $cipher);
    }

    public function decrypt(string $encoded): string
    {
        if ($encoded === '') {
            return '';
        }
        $raw = base64_decode($encoded, true);
        if ($raw === false) {
            return '';
        }
        $key = $this->encryptionKey();
        if ($key === '' || strlen($raw) < 17) {
            return is_string($raw) ? $raw : '';
        }
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return is_string($plain) ? $plain : '';
    }

    /** @param array<string, mixed> $data */
    public function saveSettings(?int $companyId, array $data): void
    {
        $db = Database::connection();
        $existing = $this->settingsRow(self::GATEWAY, $companyId);
        $fields = [
            'enabled' => !empty($data['enabled']) ? 1 : 0,
            'mode' => in_array(($data['mode'] ?? 'sandbox'), ['sandbox', 'production'], true) ? $data['mode'] : 'sandbox',
            'callback_url' => (string) ($data['callback_url'] ?? $this->callbackUrl($companyId)),
            'webhook_url' => (string) ($data['webhook_url'] ?? $this->webhookUrl($companyId)),
        ];
        if (!empty($data['publishable_key'])) {
            $fields['publishable_key_enc'] = $this->encrypt((string) $data['publishable_key']);
        }
        if (!empty($data['secret_key'])) {
            $fields['secret_key_enc'] = $this->encrypt((string) $data['secret_key']);
        }
        if (!empty($data['webhook_secret'])) {
            $fields['webhook_secret_enc'] = $this->encrypt((string) $data['webhook_secret']);
        }

        if ($existing === null) {
            $cols = ['company_id', 'gateway_slug'];
            $vals = [':company_id', ':gateway_slug'];
            $params = ['company_id' => $companyId, 'gateway_slug' => self::GATEWAY];
            foreach ($fields as $k => $v) {
                $cols[] = $k;
                $vals[] = ':' . $k;
                $params[$k] = $v;
            }
            $sql = 'INSERT INTO rateb_payment_gateway_settings (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
            $db->prepare($sql)->execute($params);
            return;
        }

        $sets = [];
        $params = ['id' => (int) $existing['id']];
        foreach ($fields as $k => $v) {
            $sets[] = $k . ' = :' . $k;
            $params[$k] = $v;
        }
        $db->prepare('UPDATE rateb_payment_gateway_settings SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
    }

    public function updateHealth(string $status, ?int $companyId = null): void
    {
        $row = $this->settingsRow(self::GATEWAY, $companyId);
        if ($row === null) {
            return;
        }
        $db = Database::connection();
        $db->prepare(
            'UPDATE rateb_payment_gateway_settings SET health_status = :st, last_health_check_at = NOW() WHERE id = :id'
        )->execute(['st' => $status, 'id' => (int) $row['id']]);
    }

    /** @return array<string, mixed>|null */
    public function settingsRow(string $slug, ?int $companyId): ?array
    {
        $db = Database::connection();
        if ($companyId !== null) {
            $stmt = $db->prepare(
                'SELECT * FROM rateb_payment_gateway_settings WHERE gateway_slug = :slug AND company_id <=> :cid LIMIT 1'
            );
            $stmt->execute(['slug' => $slug, 'cid' => $companyId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $row;
            }
        }
        $stmt = $db->prepare(
            'SELECT * FROM rateb_payment_gateway_settings WHERE gateway_slug = :slug AND company_id IS NULL LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public function publicSettings(?int $companyId = null): array
    {
        $row = $this->settingsRow(self::GATEWAY, $companyId);

        return [
            'gateway' => self::GATEWAY,
            'enabled' => $this->isEnabled(self::GATEWAY, $companyId),
            'mode' => $this->mode(self::GATEWAY, $companyId),
            'publishable_key_masked' => $this->maskSecret($this->publishableKey(self::GATEWAY, $companyId)),
            'secret_key_masked' => $this->maskSecret($this->secretKey(self::GATEWAY, $companyId)),
            'webhook_secret_masked' => $this->maskSecret($this->webhookSecret(self::GATEWAY, $companyId)),
            'callback_url' => $this->callbackUrl($companyId),
            'webhook_url' => $this->webhookUrl($companyId),
            'health_status' => (string) ($row['health_status'] ?? 'unknown'),
            'last_health_check_at' => $row['last_health_check_at'] ?? null,
        ];
    }

    /** @return array<string, string> */
    private function fileSecrets(): array
    {
        if (self::$fileSecrets !== null) {
            return self::$fileSecrets;
        }
        self::$fileSecrets = [];
        $paths = [];
        if (defined('RATEB_ROOT')) {
            $paths[] = dirname(RATEB_ROOT) . '/config/env/moyasar.secrets.php';
        }
        $paths[] = dirname(__DIR__, 3) . '/config/env/moyasar.secrets.php';
        $paths[] = dirname(__DIR__, 3) . '/config/moyasar.secrets.php';
        foreach ($paths as $path) {
            if (is_file($path)) {
                $data = require $path;
                if (is_array($data)) {
                    foreach ($data as $k => $v) {
                        if (is_string($k) && (is_string($v) || is_numeric($v))) {
                            self::$fileSecrets[$k] = (string) $v;
                        }
                    }
                }
                break;
            }
        }

        return self::$fileSecrets;
    }

    private function encryptionKey(): string
    {
        $secret = (string) ($this->fileSecrets()['MOYASAR_ENCRYPTION_KEY'] ?? '');
        if ($secret !== '') {
            return hash('sha256', $secret, true);
        }
        if (defined('RATEB_DB_PASS') && RATEB_DB_PASS !== '') {
            return hash('sha256', 'rateb_payment:' . RATEB_DB_PASS, true);
        }

        return '';
    }
}
