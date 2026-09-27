<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\Company;

/**
 * Company-branded builds of the RATEB mobile apps: the company's own icon, name and package
 * (base package + ".c<company id>"), so it installs next to other companies' apps and gets its own
 * update channel.
 *
 * Super Admin requests a build per company/app (rateb_companies.settings.mobile_branded[app] = key).
 * The build is made with scripts/build-branded-app.ps1 and shipped by deploy under
 * public/downloads/company/<key>.apk + <key>.json; the next Mobile Apps page view copies it into the
 * company's slot, so its existing link/QR serves the branded APK. The key is unguessable, so the
 * public files cannot be enumerated.
 */
final class MobileAppBrandedService
{
    public const SETTINGS_KEY = 'mobile_branded';
    public const PUBLIC_DIR = 'company';

    private MobileAppApkService $apks;

    public function __construct(?MobileAppApkService $apks = null)
    {
        $this->apks = $apks ?? new MobileAppApkService();
    }

    public static function validKey(string $key): bool
    {
        return (bool) preg_match('/^(hr|erp|customer)-[1-9][0-9]{0,9}-[a-f0-9]{10}$/', $key);
    }

    /** Build key requested for this company's app, or "" when it uses the shared build. */
    public function keyFor(string $app, array $company): string
    {
        $app = MobileAppApkService::normalizeApp($app);
        $settings = json_decode((string) ($company['settings'] ?? ''), true);
        $key = is_array($settings) ? (string) ($settings[self::SETTINGS_KEY][$app] ?? '') : '';
        $prefix = $app . '-' . (int) ($company['id'] ?? 0) . '-';

        return self::validKey($key) && str_starts_with($key, $prefix) ? $key : '';
    }

    /** Request (or cancel) the branded build; cancelling puts the company back on the shared build. */
    public function setRequested(string $app, int $companyId, bool $requested): bool
    {
        $app = MobileAppApkService::normalizeApp($app);
        $stmt = Database::connection()->prepare('SELECT settings FROM rateb_companies WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $companyId]);
        $raw = $stmt->fetchColumn();
        if ($raw === false) {
            return false;
        }
        $settings = json_decode((string) $raw, true);
        $settings = is_array($settings) ? $settings : [];
        $branded = is_array($settings[self::SETTINGS_KEY] ?? null) ? $settings[self::SETTINGS_KEY] : [];
        if ($requested) {
            if (!self::validKey((string) ($branded[$app] ?? ''))) {
                $branded[$app] = $app . '-' . $companyId . '-' . bin2hex(random_bytes(5));
            }
        } else {
            unset($branded[$app]);
            $this->apks->remove($this->apks->slotKey($app, $companyId));
        }
        $settings[self::SETTINGS_KEY] = $branded;
        $json = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!(new Company())->update($companyId, ['settings' => $json])) {
            return false;
        }
        if (function_exists('rateb_ops_company_request_state')) {
            $state = &rateb_ops_company_request_state();
            if (isset($state['rows'][$companyId]) && is_array($state['rows'][$companyId])) {
                $state['rows'][$companyId]['settings'] = $json;
            }
        }

        return true;
    }

    public function packageFor(string $app, int $companyId): string
    {
        return $this->apks->appInfo($app)['package'] . '.c' . $companyId;
    }

    /** Company icon (HR white-label icon, then logo, then company logo) as an absolute https URL. */
    public function iconUrl(array $company, ?array $hrConfig): string
    {
        foreach ([$hrConfig['icon_path'] ?? '', $hrConfig['logo_path'] ?? '', $company['logo_path'] ?? ''] as $path) {
            $path = trim((string) $path);
            if ($path === '') {
                continue;
            }
            if (preg_match('#^https://[^\s"]+$#i', $path)) {
                return $path;
            }
            if (!preg_match('#^[a-z]+:#i', $path) && !str_contains($path, '..')) {
                return rateb_public_url(ltrim($path, '/'));
            }
        }

        return '';
    }

    public function displayName(string $app, array $company, ?array $hrConfig): string
    {
        $name = $app === 'hr' ? trim((string) ($hrConfig['app_name'] ?? '')) : '';
        if ($name === '') {
            $name = trim((string) ($company['name'] ?? ''));
        }

        return mb_substr((string) preg_replace('/["`$\r\n]+/', '', $name), 0, 30);
    }

    /** Build command for scripts/build-branded-app.ps1 (run from the repository root). */
    public function buildCommand(string $app, array $company, ?array $hrConfig, string $activationCode): string
    {
        $key = $this->keyFor($app, $company);
        if ($key === '') {
            return '';
        }
        $icon = $this->iconUrl($company, $hrConfig);
        $arg = static fn (string $v): string => '"' . str_replace('"', '', $v) . '"';

        return '.\\scripts\\build-branded-app.ps1'
            . ' -App ' . $app
            . ' -Key ' . $arg($key)
            . ' -Package ' . $arg($this->packageFor($app, (int) $company['id']))
            . ' -Name ' . $arg($this->displayName($app, $company, $hrConfig))
            . ($icon !== '' ? ' -IconUrl ' . $arg($icon) : '')
            . ' -Server ' . $arg($this->apks->serverForCompany($app, $company))
            . ' -Code ' . $arg($activationCode);
    }

    /**
     * Branded build shipped by deploy for this key.
     *
     * @return array{path:string, sha256:string, size:int, package:string, version:string, version_code:int}|null
     */
    public function published(string $key): ?array
    {
        if (!self::validKey($key)) {
            return null;
        }
        $dir = rtrim(str_replace('\\', '/', (string) RATEB_ROOT), '/') . '/public/downloads/' . self::PUBLIC_DIR;
        $meta = json_decode((string) @file_get_contents($dir . '/' . $key . '.json'), true);
        $path = $dir . '/' . $key . '.apk';
        if (!is_array($meta) || !is_file($path) || !preg_match('/^[a-f0-9]{64}$/', (string) ($meta['sha256'] ?? ''))) {
            return null;
        }

        return [
            'path' => $path,
            'sha256' => (string) $meta['sha256'],
            'size' => (int) filesize($path),
            'package' => (string) ($meta['package'] ?? ''),
            'version' => (string) ($meta['version'] ?? ''),
            'version_code' => (int) ($meta['version_code'] ?? 0),
        ];
    }

    /**
     * Copy the newest published branded build into the company's slot.
     *
     * @return string none|current|updated|failed
     */
    public function sync(string $app, array $company, int $userId = 0): string
    {
        $key = $this->keyFor($app, $company);
        $pub = $key !== '' ? $this->published($key) : null;
        if ($pub === null) {
            return 'none';
        }
        $slotKey = $this->apks->slotKey($app, (int) $company['id']);
        $own = $this->apks->meta($slotKey);
        if ($own !== null && hash_equals((string) ($own['sha256'] ?? ''), $pub['sha256'])) {
            return 'current';
        }

        return $this->apks->installFile($slotKey, $pub['path'], $pub['sha256'], [
            'uploaded_by' => $userId,
            'server' => $this->apks->serverForCompany($app, $company),
            'source' => 'branded',
            'package' => $pub['package'],
            'version' => $pub['version'],
            'version_code' => $pub['version_code'],
        ]) ? 'updated' : 'failed';
    }
}
