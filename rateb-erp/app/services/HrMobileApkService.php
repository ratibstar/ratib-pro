<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Company;

/**
 * Signed mobile APK slots managed from platform oversight.
 * - HR app (RATEB HR): one slot per company, key = company id — each build targets that company's ERP host.
 * - ERP / Customer apps: one platform-wide slot each, key = 'erp' | 'customer'.
 * Files live outside the web root: storage/mobile-apks/<key>.apk + <key>.json.
 */
final class HrMobileApkService
{
    public const CHUNK_BYTES = 2 * 1024 * 1024;
    public const MAX_BYTES = 250 * 1024 * 1024;

    public const PLATFORM_APPS = ['erp', 'customer'];

    /** @var array<int, array<string, mixed>>|null */
    private ?array $agencyByCompany = null;

    /**
     * Static facts for the platform-wide apps (shown on their oversight tab).
     *
     * @return array{package:string, server:string, file:string, build_dir:string, build_command:string, output:string}|null
     */
    public function platformAppInfo(string $app): ?array
    {
        if ($app === 'erp') {
            return [
                'package' => 'sa.rateb.erp',
                'server' => rtrim(rateb_public_url('admin'), '/'),
                'file' => 'rateb-erp.apk',
                'build_dir' => 'rateb-erp\\capacitor',
                'build_command' => 'npm run cap:sync; cd android; .\\gradlew assembleRelease',
                'output' => 'android\\app\\build\\outputs\\apk\\release\\app-release.apk',
            ];
        }
        if ($app === 'customer') {
            return [
                'package' => 'com.ratib.rateb_mobile',
                'server' => 'https://rateb.sa/api',
                'file' => 'rateb-customer.apk',
                'build_dir' => 'rateb_mobile',
                'build_command' => 'C:\\flutter_sdk\\bin\\flutter.bat build apk --release',
                'output' => 'build\\app\\outputs\\flutter-apk\\app-release.apk',
            ];
        }

        return null;
    }

    public function storageDir(): string
    {
        $dir = rtrim(str_replace('\\', '/', (string) RATEB_ROOT), '/') . '/storage/mobile-apks';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function validKey(string $key): bool
    {
        return in_array($key, self::PLATFORM_APPS, true)
            || (preg_match('/^[1-9][0-9]{0,9}$/', $key) === 1);
    }

    private function apkPath(string $key): string
    {
        return $this->storageDir() . '/' . $key . '.apk';
    }

    private function metaPath(string $key): string
    {
        return $this->storageDir() . '/' . $key . '.json';
    }

    /** @return array<string, mixed>|null */
    public function meta(string $key): ?array
    {
        if (!$this->validKey($key)) {
            return null;
        }
        $metaFile = $this->metaPath($key);
        if (!is_file($metaFile) || !is_file($this->apkPath($key))) {
            return null;
        }
        $data = json_decode((string) file_get_contents($metaFile), true);
        if (!is_array($data) || !preg_match('/^[a-f0-9]{32}$/', (string) ($data['token'] ?? ''))) {
            return null;
        }
        $data['key'] = $key;
        $data['company_id'] = ctype_digit($key) ? (int) $key : 0;

        return $data;
    }

    public function downloadUrl(array $meta): string
    {
        return rateb_public_url('hr-app/' . (string) ($meta['token'] ?? ''));
    }

    public function qrImageUrl(string $url, int $size = 220): string
    {
        return rateb_local_qr_url($url, max(120, min(500, $size)), true);
    }

    /** @return array{key:string, company_id:int, path:string, meta:array<string,mixed>}|null */
    public function findByToken(string $token): ?array
    {
        $token = strtolower(trim($token));
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        foreach (glob($this->storageDir() . '/*.json') ?: [] as $metaFile) {
            $key = basename($metaFile, '.json');
            $meta = $this->meta($key);
            if ($meta !== null && hash_equals((string) $meta['token'], $token)) {
                return [
                    'key' => $key,
                    'company_id' => (int) $meta['company_id'],
                    'path' => $this->apkPath($key),
                    'meta' => $meta,
                ];
            }
        }

        return null;
    }

    public function companySlug(array $company): string
    {
        $slug = strtolower(trim((string) ($company['slug'] ?? '')));
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $slug), '-');

        return $slug !== '' ? $slug : 'company-' . (int) ($company['id'] ?? 0);
    }

    /**
     * ERP public base the HR app must call for this company (agency dedicated host or platform).
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

    public function buildCommand(array $company): string
    {
        return '.\\tool\\build_company_apk.ps1 -ErpBaseUrl "' . $this->erpBaseUrlForCompany($company)
            . '" -Slug "' . $this->companySlug($company) . '"';
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
                error_log('HrMobileApkService agencies: ' . $e->getMessage());
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
     * @return array{ok:bool, done:bool, message:string, meta?:array<string,mixed>}
     */
    public function storeChunk(string $key, string $uploadId, int $index, int $total, array $file, string $originalName, int $userId): array
    {
        if (!$this->validKey($key) || (ctype_digit($key) && (new Company())->find((int) $key) === null)) {
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

        return $this->publish($key, $part, $originalName, $userId);
    }

    /** @return array{ok:bool, done:bool, message:string, meta?:array<string,mixed>} */
    private function publish(string $key, string $part, string $originalName, int $userId): array
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

        $previous = $this->meta($key);
        $meta = [
            'token' => is_array($previous) ? (string) $previous['token'] : bin2hex(random_bytes(16)),
            'size' => $size,
            'sha256' => (string) hash_file('sha256', $apk),
            'original_name' => mb_substr(basename(str_replace('\\', '/', $originalName)), 0, 150),
            'uploaded_at' => date('Y-m-d H:i:s'),
            'uploaded_by' => $userId,
        ];
        if (ctype_digit($key)) {
            $company = (new Company())->find((int) $key) ?? ['id' => (int) $key];
            $meta['erp_base_url'] = $this->erpBaseUrlForCompany($company);
        }
        file_put_contents($this->metaPath($key), (string) json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return ['ok' => true, 'done' => true, 'message' => __('mobile_apps_apk_uploaded'), 'meta' => $meta];
    }

    public function remove(string $key): void
    {
        if (!$this->validKey($key)) {
            return;
        }
        @unlink($this->apkPath($key));
        @unlink($this->metaPath($key));
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
