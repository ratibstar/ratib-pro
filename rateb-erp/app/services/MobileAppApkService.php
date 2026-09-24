<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Company;

/**
 * The three RATEB mobile apps (HR / ERP / Customer), handled the same way from platform oversight:
 * enable per company, one signed APK per company (built for that company's server) with a stable
 * public link + QR, and a shared build used by companies that live on the platform server.
 *
 * Slot keys (storage/mobile-apks/<key>.json + <key>.apk, outside the web root):
 *   <cid>           HR app for company (kept for existing uploads)
 *   erp-<cid>       ERP app for company
 *   customer-<cid>  Customer app for company
 *   hr|erp|customer shared build (platform server)
 */
final class MobileAppApkService
{
    public const CHUNK_BYTES = 2 * 1024 * 1024;
    public const MAX_BYTES = 250 * 1024 * 1024;

    public const APPS = ['hr', 'erp', 'customer'];

    /** @var array<int, array<string, mixed>>|null */
    private ?array $agencyByCompany = null;

    public static function normalizeApp(string $app): string
    {
        $app = strtolower(trim($app));

        return in_array($app, self::APPS, true) ? $app : 'hr';
    }

    public function slotKey(string $app, int $companyId): string
    {
        $app = self::normalizeApp($app);

        return $app === 'hr' ? (string) $companyId : $app . '-' . $companyId;
    }

    /** @return array{app:string, company_id:int}|null */
    public function parseKey(string $key): ?array
    {
        if (in_array($key, self::APPS, true)) {
            return ['app' => $key, 'company_id' => 0];
        }
        if (preg_match('/^[1-9][0-9]{0,9}$/', $key)) {
            return ['app' => 'hr', 'company_id' => (int) $key];
        }
        if (preg_match('/^(erp|customer)-([1-9][0-9]{0,9})$/', $key, $m)) {
            return ['app' => $m[1], 'company_id' => (int) $m[2]];
        }

        return null;
    }

    /** @return array{package:string, build_dir:string, file_prefix:string} */
    public function appInfo(string $app): array
    {
        $app = self::normalizeApp($app);
        if ($app === 'erp') {
            return ['package' => 'sa.rateb.erp', 'build_dir' => 'rateb-erp\\capacitor', 'file_prefix' => 'rateb-erp'];
        }
        if ($app === 'customer') {
            return ['package' => 'com.ratib.rateb_mobile', 'build_dir' => 'rateb_mobile', 'file_prefix' => 'rateb-customer'];
        }

        return ['package' => 'sa.rateb.hr.mobile', 'build_dir' => 'ratib_hr_mobile', 'file_prefix' => 'rateb-hr'];
    }

    /** Server the shared (platform) build of this app talks to. */
    public function platformServer(string $app): string
    {
        $app = self::normalizeApp($app);
        if ($app === 'erp') {
            return rtrim(rateb_public_url('admin'), '/');
        }
        if ($app === 'customer') {
            return rtrim(rateb_site_origin(), '/') . '/api';
        }

        return rtrim(rateb_public_url(''), '/');
    }

    /** Server this company's build of the app must talk to. */
    public function serverForCompany(string $app, array $company): string
    {
        $app = self::normalizeApp($app);
        $erpBase = $this->erpBaseUrlForCompany($company);
        if ($app === 'erp') {
            return $erpBase . '/admin';
        }
        if ($app === 'customer') {
            $host = strtolower((string) parse_url($erpBase, PHP_URL_HOST));
            $platformHost = strtolower((string) parse_url(rateb_site_origin(), PHP_URL_HOST));
            if ($host === '' || $host === $platformHost || $host === 'www.' . $platformHost) {
                return $this->platformServer('customer');
            }

            return (string) parse_url($erpBase, PHP_URL_SCHEME) . '://' . $host . '/api';
        }

        return $erpBase;
    }

    public function buildCommand(string $app, string $server, string $slug): string
    {
        $app = self::normalizeApp($app);
        if ($app === 'erp') {
            return '.\\build-company-apk.ps1 -AdminUrl "' . $server . '" -Slug "' . $slug . '"';
        }
        if ($app === 'customer') {
            return '.\\tool\\build_company_apk.ps1 -ApiBaseUrl "' . $server . '" -Slug "' . $slug . '"';
        }

        return '.\\tool\\build_company_apk.ps1 -ErpBaseUrl "' . $server . '" -Slug "' . $slug . '"';
    }

    public function storageDir(): string
    {
        $dir = rtrim(str_replace('\\', '/', (string) RATEB_ROOT), '/') . '/storage/mobile-apks';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function apkPath(string $key): string
    {
        return $this->storageDir() . '/' . $key . '.apk';
    }

    private function metaPath(string $key): string
    {
        return $this->storageDir() . '/' . $key . '.json';
    }

    /** Slot record (token + last upload info) even when no APK is stored. */
    public function slot(string $key): ?array
    {
        $parsed = $this->parseKey($key);
        $metaFile = $this->metaPath($key);
        if ($parsed === null || !is_file($metaFile)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($metaFile), true);
        if (!is_array($data) || !preg_match('/^[a-f0-9]{32}$/', (string) ($data['token'] ?? ''))) {
            return null;
        }

        return $data + $parsed + ['key' => $key, 'has_apk' => is_file($this->apkPath($key))];
    }

    /** Stored APK info for the slot, or null when nothing is uploaded. */
    public function meta(string $key): ?array
    {
        $slot = $this->slot($key);

        return ($slot !== null && $slot['has_apk']) ? $slot : null;
    }

    /** Stable per-slot link token (created on first use, survives APK replace/delete). */
    public function ensureToken(string $key): string
    {
        $slot = $this->slot($key);
        if ($slot !== null) {
            return (string) $slot['token'];
        }
        $token = bin2hex(random_bytes(16));
        $this->writeSlot($key, ['token' => $token]);

        return $token;
    }

    public function downloadUrlForToken(string $token): string
    {
        return rateb_public_url('hr-app/' . $token);
    }

    public function qrImageUrl(string $url, int $size = 220): string
    {
        return rateb_local_qr_url($url, max(120, min(500, $size)), true);
    }

    /** Shared build this company falls back to (same server as the platform build), if any. */
    public function sharedFallback(string $app, array $company): ?array
    {
        if ($this->serverForCompany($app, $company) !== $this->platformServer($app)) {
            return null;
        }

        return $this->meta(self::normalizeApp($app));
    }

    /**
     * @return array{key:string, app:string, company_id:int, path:string, shared:bool}|null
     */
    public function resolveToken(string $token): ?array
    {
        $token = strtolower(trim($token));
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        foreach (glob($this->storageDir() . '/*.json') ?: [] as $metaFile) {
            $key = basename($metaFile, '.json');
            $slot = $this->slot($key);
            if ($slot === null || !hash_equals((string) $slot['token'], $token)) {
                continue;
            }
            $base = ['key' => $key, 'app' => $slot['app'], 'company_id' => (int) $slot['company_id']];
            if ($slot['has_apk']) {
                return $base + ['path' => $this->apkPath($key), 'shared' => false];
            }
            if ($slot['company_id'] > 0) {
                $company = (new Company())->find((int) $slot['company_id']);
                if (is_array($company) && $this->sharedFallback($slot['app'], $company) !== null) {
                    return $base + ['path' => $this->apkPath($slot['app']), 'shared' => true];
                }
            }

            return null;
        }

        return null;
    }

    public function isEnabled(string $app, array $company, ?array $hrConfig = null): bool
    {
        $app = self::normalizeApp($app);
        if ($app === 'hr') {
            if ($hrConfig === null) {
                $hrConfig = (new MobileAppConfigService())->findByCompanyId((int) ($company['id'] ?? 0));
            }

            return is_array($hrConfig) && (string) ($hrConfig['status'] ?? '') === MobileAppConfigService::STATUS_ACTIVE;
        }
        $settings = json_decode((string) ($company['settings'] ?? ''), true);

        return is_array($settings)
            && (string) ($settings['mobile_apps'][$app] ?? '') === MobileAppConfigService::STATUS_ACTIVE;
    }

    /** ERP / Customer enablement lives in rateb_companies.settings.mobile_apps (HR uses its config table). */
    public function setEnabledInSettings(string $app, int $companyId, bool $enabled): bool
    {
        $model = new Company();
        $company = $model->find($companyId);
        if (!is_array($company) || !in_array($app, ['erp', 'customer'], true)) {
            return false;
        }
        $settings = json_decode((string) ($company['settings'] ?? ''), true);
        if (!is_array($settings)) {
            $settings = [];
        }
        if (!isset($settings['mobile_apps']) || !is_array($settings['mobile_apps'])) {
            $settings['mobile_apps'] = [];
        }
        $settings['mobile_apps'][$app] = $enabled ? MobileAppConfigService::STATUS_ACTIVE : MobileAppConfigService::STATUS_INACTIVE;

        return (bool) $model->update($companyId, ['settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    public function companySlug(array $company): string
    {
        $slug = strtolower(trim((string) ($company['slug'] ?? '')));
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $slug), '-');

        return $slug !== '' ? $slug : 'company-' . (int) ($company['id'] ?? 0);
    }

    /**
     * ERP public base for this company (agency dedicated host or platform).
     */
    public function erpBaseUrlForCompany(array $company): string
    {
        $cid = (int) ($company['id'] ?? $company['company_id'] ?? 0);
        $agency = $this->agencyForCompany($cid, $company);
        if (is_array($agency) && function_exists('rateb_agency_erp_public_base')) {
            $base = rateb_agency_erp_public_base((string) ($agency['site_url'] ?? ''));
            if ($base !== '') {
                return rtrim($base, '/');
            }
        }
        $settings = json_decode((string) ($company['settings'] ?? ''), true);
        $site = is_array($settings) ? trim((string) ($settings['site_url'] ?? '')) : '';
        if ($site !== '' && function_exists('rateb_agency_erp_public_base')) {
            $base = rateb_agency_erp_public_base($site);
            if ($base !== '') {
                return rtrim($base, '/');
            }
        }

        return rtrim(rateb_public_url(''), '/');
    }

    /** @return array<string, mixed>|null */
    private function agencyForCompany(int $companyId, array $company): ?array
    {
        if ($companyId < 1) {
            return null;
        }
        if ($this->agencyByCompany === null) {
            $this->agencyByCompany = [];
            try {
                foreach ((new AgencyErpMigrationService())->listControlAgencies(false) as $row) {
                    $linked = (int) ($row['erp_company_id'] ?? 0);
                    if ($linked > 0) {
                        $this->agencyByCompany[$linked] = $row;
                    }
                    $this->agencyByCompany[-(int) ($row['id'] ?? 0)] = $row;
                }
            } catch (\Throwable $e) {
                error_log('MobileAppApkService agencies: ' . $e->getMessage());
            }
        }
        if (isset($this->agencyByCompany[$companyId])) {
            return $this->agencyByCompany[$companyId];
        }
        $settings = json_decode((string) ($company['settings'] ?? ''), true);
        $aid = is_array($settings) ? (int) ($settings['control_agency_id'] ?? 0) : 0;

        return $aid > 0 ? ($this->agencyByCompany[-$aid] ?? null) : null;
    }

    /**
     * Append one chunk; on the last chunk validate + publish the APK.
     *
     * @param array<string, mixed> $file $_FILES entry
     * @return array{ok:bool, done:bool, message:string}
     */
    public function storeChunk(string $key, string $uploadId, int $index, int $total, array $file, string $originalName, int $userId): array
    {
        $parsed = $this->parseKey($key);
        if ($parsed === null || ($parsed['company_id'] > 0 && (new Company())->find($parsed['company_id']) === null)) {
            return ['ok' => false, 'done' => false, 'message' => __('not_found')];
        }
        if (!preg_match('/^[a-f0-9]{32}$/', $uploadId) || $total < 1 || $index < 0 || $index >= $total
            || $total > (int) ceil(self::MAX_BYTES / self::CHUNK_BYTES)) {
            return ['ok' => false, 'done' => false, 'message' => __('mobile_apps_apk_upload_invalid')];
        }
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return ['ok' => false, 'done' => false, 'message' => $this->uploadErrorMessage($err)];
        }

        $tmpDir = $this->storageDir() . '/tmp';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }
        $part = $tmpDir . '/' . $key . '-' . $uploadId . '.part';
        if ($index === 0) {
            @unlink($part);
            $this->purgeStaleParts($tmpDir);
        } elseif (!is_file($part) || filesize($part) !== $index * self::CHUNK_BYTES) {
            @unlink($part);

            return ['ok' => false, 'done' => false, 'message' => __('mobile_apps_apk_upload_invalid')];
        }

        $in = fopen((string) $file['tmp_name'], 'rb');
        $out = fopen($part, 'ab');
        if ($in === false || $out === false) {
            return ['ok' => false, 'done' => false, 'message' => __('mobile_apps_apk_upload_failed')];
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
        clearstatcache(true, $part);

        if ($index < $total - 1) {
            return ['ok' => true, 'done' => false, 'message' => ''];
        }

        return $this->publish($key, $parsed, $part, $originalName, $userId);
    }

    /**
     * @param array{app:string, company_id:int} $parsed
     * @return array{ok:bool, done:bool, message:string}
     */
    private function publish(string $key, array $parsed, string $part, string $originalName, int $userId): array
    {
        $size = (int) filesize($part);
        $fh = fopen($part, 'rb');
        $magic = $fh !== false ? (string) fread($fh, 4) : '';
        if ($fh !== false) {
            fclose($fh);
        }
        if ($size < 1024 || $size > self::MAX_BYTES || $magic !== "PK\x03\x04") {
            @unlink($part);

            return ['ok' => false, 'done' => true, 'message' => __('mobile_apps_apk_not_apk')];
        }

        $apk = $this->apkPath($key);
        @unlink($apk);
        if (!@rename($part, $apk)) {
            @unlink($part);

            return ['ok' => false, 'done' => true, 'message' => __('mobile_apps_apk_upload_failed')];
        }

        $server = $this->platformServer($parsed['app']);
        if ($parsed['company_id'] > 0) {
            $company = (new Company())->find($parsed['company_id']) ?? ['id' => $parsed['company_id']];
            $server = $this->serverForCompany($parsed['app'], $company);
        }
        $this->writeSlot($key, [
            'token' => $this->ensureToken($key),
            'size' => $size,
            'sha256' => (string) hash_file('sha256', $apk),
            'original_name' => mb_substr(basename(str_replace('\\', '/', $originalName)), 0, 150),
            'uploaded_at' => date('Y-m-d H:i:s'),
            'uploaded_by' => $userId,
            'server' => $server,
        ]);

        return ['ok' => true, 'done' => true, 'message' => __('mobile_apps_apk_uploaded')];
    }

    /** Delete the stored APK; the slot keeps its token so the company link stays the same. */
    public function remove(string $key): void
    {
        if ($this->parseKey($key) === null) {
            return;
        }
        @unlink($this->apkPath($key));
        $slot = $this->slot($key);
        if ($slot !== null) {
            $this->writeSlot($key, ['token' => (string) $slot['token']]);
        }
    }

    /** @param array<string, mixed> $data */
    private function writeSlot(string $key, array $data): void
    {
        file_put_contents(
            $this->metaPath($key),
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
    }

    private function purgeStaleParts(string $tmpDir): void
    {
        foreach (glob($tmpDir . '/*.part') ?: [] as $stale) {
            if (@filemtime($stale) < time() - 86400) {
                @unlink($stale);
            }
        }
    }

    private function uploadErrorMessage(int $err): string
    {
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return __('mobile_apps_apk_upload_too_large');
        }
        if ($err === UPLOAD_ERR_NO_FILE) {
            return __('mobile_apps_apk_upload_invalid');
        }

        return __('mobile_apps_apk_upload_failed');
    }
}
