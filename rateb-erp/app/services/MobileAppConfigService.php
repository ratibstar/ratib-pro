<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Company;
use Rateb\App\Models\MobileAppConfig;

/**
 * Mobile Apps Management — tenant config only (no HR business rules).
 * Shared Flutter/Workforce client reads branding via GET /api/mobile/config.
 */
final class MobileAppConfigService
{
    public const FEATURE_KEYS = [
        'attendance',
        'leave',
        'profile',
        'documents',
        'payroll',
        'notifications',
        'requests',
        'ratings',
        'inquiries',
        'payments',
        'settings',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    /** @return array<string, bool> */
    public static function defaultFeatures(): array
    {
        return [
            'attendance' => true,
            'leave' => true,
            'profile' => true,
            'documents' => true,
            'payroll' => false,
            'notifications' => true,
            'requests' => true,
            'ratings' => true,
            'inquiries' => true,
            'payments' => false,
            'settings' => true,
        ];
    }

    /**
     * @param array<string, mixed>|null $raw
     * @return array<string, bool>
     */
    public function normalizeFeatures(?array $raw): array
    {
        $out = self::defaultFeatures();
        if (!is_array($raw)) {
            return $out;
        }
        foreach (self::FEATURE_KEYS as $key) {
            if (array_key_exists($key, $raw)) {
                $out[$key] = filter_var($raw[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $out;
    }

    /** @param mixed $json */
    public function decodeFeatures($json): array
    {
        if (is_array($json)) {
            return $this->normalizeFeatures($json);
        }
        if (!is_string($json) || $json === '') {
            return self::defaultFeatures();
        }
        $decoded = json_decode($json, true);

        return $this->normalizeFeatures(is_array($decoded) ? $decoded : null);
    }

    public function encodeFeatures(array $features): string
    {
        return (string) json_encode($this->normalizeFeatures($features), JSON_UNESCAPED_UNICODE);
    }

    public function findByCompanyId(int $companyId): ?array
    {
        if ($companyId < 1) {
            return null;
        }
        $row = (new MobileAppConfig())->queryOne(
            'SELECT * FROM rateb_mobile_app_configs WHERE company_id = :cid LIMIT 1',
            ['cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Companies with optional joined mobile config (platform list).
     *
     * @return list<array<string, mixed>>
     */
    public function listCompaniesWithConfig(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT c.id AS company_id, c.name AS company_name, c.status AS company_status,'
            . ' m.id AS config_id, m.app_name, m.logo_path, m.icon_path, m.splash_path,'
            . ' m.theme_color, m.status AS mobile_status, m.enabled_features, m.updated_at'
            . ' FROM rateb_companies c'
            . ' LEFT JOIN rateb_mobile_app_configs m ON m.company_id = c.id'
            . ' ORDER BY c.name ASC'
            . ' LIMIT ' . (int) $limit;

        $rows = (new Company())->query($sql, []);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Upsert tenant mobile config. Caller must enforce mobile_apps.manage.
     *
     * @param array{
     *   app_name?: string,
     *   logo_path?: string|null,
     *   icon_path?: string|null,
     *   splash_path?: string|null,
     *   theme_color?: string,
     *   status?: string,
     *   enabled_features?: array<string, bool|int|string>
     * } $input
     * @return array{ok: bool, message: string, config: ?array}
     */
    public function upsertForCompany(int $companyId, array $input): array
    {
        if ($companyId < 1) {
            return ['ok' => false, 'message' => 'invalid_company', 'config' => null];
        }
        $company = (new Company())->find($companyId);
        if (!$company) {
            return ['ok' => false, 'message' => 'company_not_found', 'config' => null];
        }

        $status = strtolower(trim((string) ($input['status'] ?? self::STATUS_INACTIVE)));
        if (!in_array($status, [self::STATUS_ACTIVE, self::STATUS_INACTIVE], true)) {
            $status = self::STATUS_INACTIVE;
        }

        $theme = trim((string) ($input['theme_color'] ?? '#0D6EFD'));
        if ($theme === '' || !preg_match('/^#[0-9A-Fa-f]{3,8}$/', $theme)) {
            $theme = '#0D6EFD';
        }

        $features = $this->normalizeFeatures(
            isset($input['enabled_features']) && is_array($input['enabled_features'])
                ? $input['enabled_features']
                : null
        );

        $payload = [
            'company_id' => $companyId,
            'app_name' => mb_substr(trim((string) ($input['app_name'] ?? ($company['name'] ?? ''))), 0, 150),
            'logo_path' => $this->nullablePath($input['logo_path'] ?? null),
            'icon_path' => $this->nullablePath($input['icon_path'] ?? null),
            'splash_path' => $this->nullablePath($input['splash_path'] ?? null),
            'theme_color' => $theme,
            'status' => $status,
            'enabled_features' => $this->encodeFeatures($features),
        ];

        $model = new MobileAppConfig();
        $existing = $this->findByCompanyId($companyId);
        if ($existing) {
            $model->update((int) $existing['id'], $payload);
            $row = $this->findByCompanyId($companyId);
        } else {
            $model->create($payload);
            $row = $this->findByCompanyId($companyId);
        }

        return ['ok' => true, 'message' => 'saved', 'config' => $row];
    }

    /**
     * Public mobile client payload for authenticated tenant token.
     * Never accepts client-supplied company_id.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function apiConfigForCompany(int $companyId): array
    {
        if ($companyId < 1) {
            return [
                'status' => 401,
                'body' => ['success' => false, 'message' => 'Unauthorized'],
            ];
        }

        $company = (new Company())->find($companyId);
        if (!$company) {
            return [
                'status' => 404,
                'body' => ['success' => false, 'message' => 'Company not found'],
            ];
        }

        $row = $this->findByCompanyId($companyId);
        if (!$row || (string) ($row['status'] ?? '') !== self::STATUS_ACTIVE) {
            $features = self::defaultFeatures();
            $companyName = trim((string) ($company['name'] ?? ''));
            $appName = $this->isPlaceholderAppName($companyName, $companyName)
                ? 'راتب — الموارد البشرية'
                : ($companyName !== '' ? $companyName : 'راتب — الموارد البشرية');

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'company_id' => $companyId,
                    'company_name' => $companyName,
                    'app_name' => $appName,
                    'logo' => '',
                    'icon' => '',
                    'splash' => '',
                    'theme_color' => '#0D6EFD',
                    'mobile_active' => true,
                    'features' => [
                        'attendance' => !empty($features['attendance']),
                        'leave' => !empty($features['leave']),
                        'profile' => !empty($features['profile']),
                        'documents' => !empty($features['documents']),
                        'payroll' => !empty($features['payroll']),
                        'notifications' => !empty($features['notifications']),
                        'requests' => !empty($features['requests']),
                        'ratings' => !empty($features['ratings']),
                        'inquiries' => !empty($features['inquiries']),
                        'payments' => !empty($features['payments']),
                        'settings' => !empty($features['settings']),
                    ],
                ],
            ];
        }

        $features = $this->decodeFeatures($row['enabled_features'] ?? null);
        $appName = trim((string) ($row['app_name'] ?? ''));
        $companyName = trim((string) ($company['name'] ?? ''));
        if ($appName === '' || $this->isPlaceholderAppName($appName, $companyName)) {
            $appName = 'راتب — الموارد البشرية';
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'company_id' => $companyId,
                'company_name' => $companyName,
                'app_name' => $appName,
                'logo' => (string) ($row['logo_path'] ?? ''),
                'icon' => (string) ($row['icon_path'] ?? ''),
                'splash' => (string) ($row['splash_path'] ?? ''),
                'theme_color' => (string) ($row['theme_color'] ?? '#0D6EFD'),
                'features' => [
                    'attendance' => !empty($features['attendance']),
                    'leave' => !empty($features['leave']),
                    'profile' => !empty($features['profile']),
                    'documents' => !empty($features['documents']),
                    'payroll' => !empty($features['payroll']),
                    'notifications' => !empty($features['notifications']),
                    'requests' => !empty($features['requests']),
                    'ratings' => !empty($features['ratings']),
                    'inquiries' => !empty($features['inquiries']),
                    'payments' => !empty($features['payments']),
                    'settings' => !empty($features['settings']),
                ],
            ],
        ];
    }

    /** @param mixed $path */
    private function nullablePath($path): ?string
    {
        $s = trim((string) ($path ?? ''));
        if ($s === '') {
            return null;
        }

        return mb_substr($s, 0, 500);
    }

    /** Test / stub company names should not become the mobile app title. */
    private function isPlaceholderAppName(string $appName, string $companyName): bool
    {
        $name = mb_strtolower(trim($appName));
        if ($name === '') {
            return true;
        }
        if (in_array($name, ['aaa', 'test', 'demo', 'tmp', 'xx', 'xyz'], true)) {
            return true;
        }
        if (mb_strlen($name) <= 3 && mb_strtolower(trim($companyName)) === $name) {
            return true;
        }

        return false;
    }
}
