<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\SessionManager;

/**
 * Company name/logo on the ERP login page for apps opened by activation code (?company_code=).
 * Shared hosts (e.g. admin.rateb.sa) serve many companies, so the code is resolved through the
 * platform activation authority and shown only when that company's ERP lives on this host.
 */
final class LoginCompanyHintService
{
    public const COOKIE = 'rateb_login_co';
    private const SESSION_KEY = '_rateb_login_company_hint';
    private const TTL = 1800;

    /** @return array{name:string, logo:string}|null */
    public function fromRequest(): ?array
    {
        $fromQuery = MobileAppActivationService::normalize((string) ($_GET['company_code'] ?? ''));
        $code = $fromQuery !== ''
            ? $fromQuery
            : MobileAppActivationService::normalize((string) ($_COOKIE[self::COOKIE] ?? ''));
        if ($code === '') {
            return null;
        }
        $hint = $this->cached($code) ?? $this->resolve($code);
        if ($hint === null) {
            if ($fromQuery === '') {
                $this->forgetCookie();
            }
            return null;
        }
        if ($fromQuery !== '') {
            $this->rememberCookie(MobileAppActivationService::format($code));
        }

        return $hint;
    }

    /** @return array{name:string, logo:string}|null */
    private function cached(string $code): ?array
    {
        $row = SessionManager::get(self::SESSION_KEY);
        if (!is_array($row) || ($row['code'] ?? '') !== $code || (int) ($row['at'] ?? 0) < time() - self::TTL) {
            return null;
        }

        return ['name' => (string) ($row['name'] ?? ''), 'logo' => $this->sameOriginLogo((string) ($row['logo'] ?? ''))];
    }

    /** The login page CSP only allows same-origin images, so serve the platform logo from this host. */
    private function sameOriginLogo(string $logo): string
    {
        $path = (string) (parse_url($logo, PHP_URL_PATH) ?? '');
        $at = strpos($path, '/public/');
        if ($at === false) {
            return '';
        }
        $relative = ltrim(substr($path, $at + strlen('/public/')), '/');
        if ($relative === '' || str_contains($relative, '..')
            || !is_file(rtrim(str_replace('\\', '/', (string) RATEB_ROOT), '/') . '/public/' . $relative)) {
            return '';
        }

        return rateb_url($relative);
    }

    /** @return array{name:string, logo:string}|null */
    private function resolve(string $code): ?array
    {
        $body = rateb_is_platform_oversight_host()
            ? (new MobileAppActivationService())->resolve($code, 'erp')['body']
            : $this->fetchFromPlatform($code);
        if (!is_array($body) || empty($body['success'])) {
            return null;
        }
        $erpHost = strtolower((string) (parse_url((string) ($body['erp_base_url'] ?? ''), PHP_URL_HOST) ?? ''));
        $thisHost = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
        if ($erpHost === '' || $erpHost !== $thisHost) {
            return null;
        }
        $company = is_array($body['company'] ?? null) ? $body['company'] : [];
        $name = trim((string) ($company[rateb_locale() === 'ar' ? 'name_ar' : 'name_en'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($company['name'] ?? ''));
        }
        if ($name === '') {
            return null;
        }
        $logo = (string) ($company['logo_url'] ?? '');
        if ($logo !== '' && !preg_match('#^https://#i', $logo)) {
            $logo = '';
        }
        SessionManager::set(self::SESSION_KEY, ['code' => $code, 'name' => $name, 'logo' => $logo, 'at' => time()]);

        return ['name' => $name, 'logo' => $this->sameOriginLogo($logo)];
    }

    /** @return array<string, mixed>|null */
    private function fetchFromPlatform(string $code): ?array
    {
        $url = rateb_platform_oversight_public_url('api/v1/mobile/activation')
            . '?' . http_build_query(['code' => $code, 'app' => 'erp']);
        $ctx = stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => 4, 'ignore_errors' => true, 'header' => "Accept: application/json\r\n"],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function rememberCookie(string $code): void
    {
        $this->writeCookie($code, time() + 86400 * 365);
        $_COOKIE[self::COOKIE] = $code;
    }

    private function forgetCookie(): void
    {
        $this->writeCookie('', time() - 3600);
        unset($_COOKIE[self::COOKIE]);
    }

    private function writeCookie(string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie(self::COOKIE, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
