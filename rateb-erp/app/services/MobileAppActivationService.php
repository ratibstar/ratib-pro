<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\Company;

/**
 * Company activation code for the shared RATEB mobile apps (HR / ERP / Customer).
 *
 * One code per company (rateb_companies.settings.mobile_activation_code). A shared app build
 * resolves the code once to learn which server the company lives on — so any company, on the
 * platform or on its own host, can use any enabled app without a per-company build.
 * The resolver returns public routing data only: never users, tokens or credentials.
 */
final class MobileAppActivationService
{
    public const SETTINGS_KEY = 'mobile_activation_code';
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const LENGTH = 8;

    private MobileAppApkService $apks;

    public function __construct(?MobileAppApkService $apks = null)
    {
        $this->apks = $apks ?? new MobileAppApkService();
    }

    /** Accepts "ABCD-2345", "abcd2345" or an activation URL/QR payload; returns "ABCD2345" or "". */
    public static function normalize(string $input): string
    {
        $input = trim($input);
        if (($q = strpos($input, '?')) !== false) {
            $input = substr($input, 0, $q);
        }
        if (preg_match('#(?:app-activate|m/activate)/([A-Za-z0-9\-]+)#', $input, $m)) {
            $input = $m[1];
        } elseif (preg_match('#[?&]code=([A-Za-z0-9\-]+)#', $input, $m)) {
            $input = $m[1];
        }
        $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $input));
        if (strlen($code) !== self::LENGTH || strspn($code, self::ALPHABET) !== self::LENGTH) {
            return '';
        }

        return $code;
    }

    public static function format(string $code): string
    {
        return strlen($code) === self::LENGTH ? substr($code, 0, 4) . '-' . substr($code, 4) : $code;
    }

    public function codeFor(array $company): string
    {
        $settings = json_decode((string) ($company['settings'] ?? ''), true);

        return is_array($settings) ? self::normalize((string) ($settings[self::SETTINGS_KEY] ?? '')) : '';
    }

    /** Existing code for the company, or a new unique one saved to its settings. */
    public function ensureCode(int $companyId): string
    {
        $company = (new Company())->find($companyId);
        if (!is_array($company)) {
            return '';
        }
        $code = $this->codeFor($company);

        return $code !== '' ? $code : $this->storeNewCode($company);
    }

    /** Replace the company's code (the old code stops working immediately). */
    public function regenerate(int $companyId): string
    {
        $company = (new Company())->find($companyId);

        return is_array($company) ? $this->storeNewCode($company) : '';
    }

    public function activationUrl(string $code): string
    {
        return $this->publicActivationUrl($code);
    }

    /** Canonical HTTPS link encoded in admin QR (always rateb.sa on platform oversight). */
    public function publicActivationUrl(string $code): string
    {
        $code = self::normalize($code);
        if ($code === '') {
            return '';
        }
        $path = 'm/activate/' . self::format($code);
        if (function_exists('rateb_is_platform_oversight_host')
            && rateb_is_platform_oversight_host()
            && function_exists('rateb_platform_oversight_public_url')) {
            return rateb_platform_oversight_public_url($path);
        }

        return rateb_public_url($path);
    }

    /**
     * Admin QR payload: HTTPS activation page (camera-friendly). Page auto-opens the HR app via Android intent;
     * the installed app also handles this URL directly.
     */
    /**
     * @param array<string, mixed>|null $company When set and on the unified model, QR opens the HR APK directly.
     */
    public function qrActivationPayload(string $code, ?array $company = null): string
    {
        if ($company !== null && !$this->apks->companyHasBrandedBuild('hr', $company)) {
            $handoff = $this->mobileAppHandoffUrl($code);
            if ($handoff !== '') {
                return $handoff;
            }
        }
        $url = $this->publicActivationUrl($code);
        if ($url === '') {
            return '';
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . 'setup=1';
    }

    /** Link shown in Admin + encoded in QR for unified companies (opens sa.rateb.hr.mobile). */
    public function activationUrlForCompany(string $code, array $company, string $app = 'hr'): string
    {
        $app = MobileAppApkService::normalizeApp($app);
        if (!$this->apks->companyHasBrandedBuild($app, $company)) {
            return $this->mobileAppHandoffUrl($code);
        }

        return $this->publicActivationUrl($code);
    }

    /**
     * HTTPS link the unified HR APK intercepts (Android intent-filter).
     * Must use /rateb-erp/public/app-activate — not rateb.sa/m/activate (browser-only on production)
     * and not ratebapp:// (legacy branded APKs may still claim that scheme).
     */
    public function mobileAppHandoffUrl(string $code): string
    {
        $code = self::normalize($code);
        if ($code === '') {
            return '';
        }
        $origin = rtrim(rateb_site_origin(), '/');

        return $origin . '/rateb-erp/public/app-activate/' . self::format($code) . '?setup=1';
    }

    /**
     * Every company with a stored code (for admin audit — no codes are minted here).
     *
     * @return list<array{company_id:int, company_name:string, code:string, activation_url:string, erp_host:string}>
     */
    public function listActivationAssignments(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, name, settings FROM rateb_companies ORDER BY id ASC'
        );
        $out = [];
        $apkSvc = $this->apks;
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $code = $this->codeFor($row);
            if ($code === '') {
                continue;
            }
            $erpBase = $apkSvc->erpBaseUrlForCompany($row);
            $host = (string) (parse_url($erpBase, PHP_URL_HOST) ?? $erpBase);
            $out[] = [
                'company_id' => (int) ($row['id'] ?? 0),
                'company_name' => (string) ($row['name'] ?? ''),
                'code' => self::format($code),
                'activation_url' => $this->publicActivationUrl($code),
                'erp_host' => $host,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{code:string, company_ids:list<int>}>
     */
    public function findDuplicateCodes(): array
    {
        $byCode = [];
        foreach ($this->listActivationAssignments() as $row) {
            $norm = self::normalize($row['code']);
            if ($norm === '') {
                continue;
            }
            $byCode[$norm]['company_ids'][] = (int) $row['company_id'];
        }
        $dupes = [];
        foreach ($byCode as $norm => $meta) {
            $ids = array_values(array_unique($meta['company_ids'] ?? []));
            if (count($ids) > 1) {
                $dupes[] = ['code' => self::format($norm), 'company_ids' => $ids];
            }
        }

        return $dupes;
    }

    /** @return array<string, mixed>|null */
    public function findCompanyByCode(string $code): ?array
    {
        $code = self::normalize($code);
        if ($code === '') {
            return null;
        }
        $formatted = self::format($code);
        $db = Database::connection();
        try {
            $stmt = $db->prepare(
                "SELECT * FROM rateb_companies
                 WHERE JSON_UNQUOTE(JSON_EXTRACT(settings, '$.mobile_activation_code')) IN (:raw, :fmt)
                 LIMIT 5"
            );
            $stmt->execute(['raw' => $code, 'fmt' => $formatted]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                if (hash_equals($this->codeFor($row), $code)) {
                    return $row;
                }
            }
        } catch (\Throwable $e) {
            // Older MariaDB without JSON_EXTRACT on settings text — fall back below.
        }

        return $this->findCompanyByCodeScan($code);
    }

    /** @return array<string, mixed>|null */
    private function findCompanyByCodeScan(string $code): ?array
    {
        $stmt = Database::connection()->query('SELECT * FROM rateb_companies ORDER BY id ASC');
        if ($stmt === false) {
            return null;
        }
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            if (hash_equals($this->codeFor($row), $code)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Routing data for one app of the company behind $code.
     *
     * @return array{status:int, body:array<string, mixed>}
     */
    public function resolve(string $code, string $app): array
    {
        $app = MobileAppApkService::normalizeApp($app);
        $company = $this->findCompanyByCode($code);
        if ($company === null || (string) ($company['status'] ?? 'active') !== 'active') {
            return ['status' => 404, 'body' => ['success' => false, 'code' => 'invalid_code', 'message' => __('mobile_activation_invalid')]];
        }
        $this->apks->reconcileCompanySlotPolicy($app, $company);
        if (!$this->apks->isEnabled($app, $company)) {
            return ['status' => 403, 'body' => ['success' => false, 'code' => 'app_not_enabled', 'message' => __('mobile_activation_app_disabled')]];
        }
        $erpBase = $this->apks->erpBaseUrlForCompany($company);
        $branded = new MobileAppBrandedService($this->apks);
        $names = $branded->names($company);
        $logo = $branded->iconUrl($company, (new MobileAppConfigService())->findByCompanyId((int) $company['id']), $erpBase);

        return ['status' => 200, 'body' => [
            'success' => true,
            'app' => $app,
            'company' => [
                'id' => (int) ($company['id'] ?? 0),
                'name' => (string) ($company['name'] ?? ''),
                'name_ar' => $names['ar'],
                'name_en' => $names['en'],
                'logo_url' => $logo,
                'slug' => $this->apks->companySlug($company),
            ],
            'erp_base_url' => $erpBase,
            'admin_url' => $erpBase . '/admin',
            'api_base_url' => $this->apks->serverForCompany('customer', $company),
            'agency_id' => $this->apks->agencyIdForCompany($company),
        ]];
    }

    /**
     * Apps enabled for the company, each with its download link and an Android link that opens
     * the installed app already activated (or falls back to the download).
     *
     * @return list<array{app:string, url:string, open:string}>
     */
    public function enabledApps(array $company): array
    {
        $code = $this->codeFor($company);
        $out = [];
        foreach (MobileAppApkService::APPS as $app) {
            if (!$this->apks->isEnabled($app, $company)) {
                continue;
            }
            $url = $this->apks->activationDownloadUrl($app, $company);
            $package = $this->apks->packageForActivationLink($app, $company);
            $published = $this->apks->publishedBuild($app);
            $fallback = $url;
            if ($code !== '' && !$this->apks->servesBrandedApk($app, $company)) {
                $fallback = $app === 'hr' && is_array($published) && ($published['url'] ?? '') !== ''
                    ? (string) $published['url']
                    : $this->qrActivationPayload($code, $company);
            }
            $open = '';
            if ($code !== '') {
                if ($app === 'hr' && !$this->apks->servesBrandedApk($app, $company)) {
                    $open = $this->mobileAppHandoffUrl($code);
                } else {
                    $open = $this->appLink($package, $code, $fallback);
                }
            }
            $out[] = ['app' => $app, 'url' => $url, 'open' => $open];
        }

        return $out;
    }

    /** Android intent link: ratebapp://activate?code=… in the given package, download when not installed. */
    public function appLink(string $package, string $code, string $fallbackUrl): string
    {
        $formatted = self::format(self::normalize($code));

        return 'intent://activate?code=' . rawurlencode($formatted)
            . '#Intent;scheme=ratebapp;package=' . $package
            . ';S.browser_fallback_url=' . rawurlencode($fallbackUrl) . ';end';
    }

    private function storeNewCode(array $company): string
    {
        $companyId = (int) $company['id'];
        // Company::find() is memoized per request; read settings fresh so no concurrent change is lost.
        $stmt = Database::connection()->prepare('SELECT settings FROM rateb_companies WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $companyId]);
        $settings = json_decode((string) ($stmt->fetchColumn() ?: ''), true);
        $settings = is_array($settings) ? $settings : [];
        $code = '';
        for ($i = 0; $i < 20; $i++) {
            $candidate = $this->randomCode();
            if ($this->findCompanyByCode($candidate) === null) {
                $code = $candidate;
                break;
            }
        }
        if ($code === '') {
            return '';
        }
        $settings[self::SETTINGS_KEY] = $code;
        $json = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!(new Company())->update($companyId, ['settings' => $json])) {
            return '';
        }
        if (function_exists('rateb_ops_company_request_state')) {
            $state = &rateb_ops_company_request_state();
            if (isset($state['rows'][$companyId]) && is_array($state['rows'][$companyId])) {
                $state['rows'][$companyId]['settings'] = $json;
            }
        }

        return $code;
    }

    private function randomCode(): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }
}
