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
        if (preg_match('#app-activate/([A-Za-z0-9\-]+)#', $input, $m)) {
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
        return rateb_public_url('app-activate/' . self::format($code));
    }

    /** @return array<string, mixed>|null */
    public function findCompanyByCode(string $code): ?array
    {
        $code = self::normalize($code);
        if ($code === '') {
            return null;
        }
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rateb_companies WHERE settings LIKE :needle LIMIT 5'
        );
        $stmt->execute(['needle' => '%' . $code . '%']);
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
        if (!$this->apks->isEnabled($app, $company)) {
            return ['status' => 403, 'body' => ['success' => false, 'code' => 'app_not_enabled', 'message' => __('mobile_activation_app_disabled')]];
        }
        $erpBase = $this->apks->erpBaseUrlForCompany($company);

        return ['status' => 200, 'body' => [
            'success' => true,
            'app' => $app,
            'company' => [
                'name' => (string) ($company['name'] ?? ''),
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
            $key = $this->apks->slotKey($app, (int) $company['id']);
            $url = $this->apks->downloadUrlForToken($this->apks->ensureToken($key));
            $out[] = ['app' => $app, 'url' => $url, 'open' => $code !== '' ? $this->appLink($app, $code, $url) : ''];
        }

        return $out;
    }

    /** Android intent link: ratebapp://activate?code=… in the app's package, download when not installed. */
    public function appLink(string $app, string $code, string $fallbackUrl): string
    {
        return 'intent://activate?code=' . rawurlencode($code)
            . '#Intent;scheme=ratebapp;package=' . $this->apks->appInfo($app)['package']
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
        for ($i = 0; $i < 10; $i++) {
            $code = $this->randomCode();
            if ($this->findCompanyByCode($code) === null) {
                break;
            }
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
