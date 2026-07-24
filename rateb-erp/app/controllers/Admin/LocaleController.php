<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;

/**
 * Public + ERP locale switch (`/locale/{en|ar}`).
 * Own file so WebsiteKernel initWebsite can require it (AdminControllers is ERP-only).
 */
final class LocaleController extends Controller
{
    public function switch(array $params): void
    {
        $locale = $params['locale'] ?? 'en';
        if (in_array($locale, RATEB_SUPPORTED_LOCALES, true)) {
            $_SESSION['rateb_locale'] = $locale;
            if (function_exists('rateb_set_locale_cookie')) {
                rateb_set_locale_cookie($locale);
            }
        }
        // Locale pages must never be cached (SW / soft-nav HTML would keep old language).
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }
        Response::redirect($this->localeRedirectTarget());
    }

    private function localeRedirectTarget(): string
    {
        $next = trim((string) ($_GET['next'] ?? ''));
        if ($next !== '' && $this->isSafeInternalPath($next)) {
            $url = rateb_url($next);
            $companyId = (int) ($_GET['company_id'] ?? 0);
            if ($companyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
                $companyId = (int) rateb_resolve_ops_company_id();
            }
            if ($companyId > 0 && strpos($url, 'company_id=') === false) {
                $url .= (strpos($url, '?') === false ? '?' : '&') . 'company_id=' . $companyId;
            }

            return $url;
        }

        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($ref !== '' && $this->isSameSiteUrl($ref) && strpos($ref, '/locale/') === false) {
            return $ref;
        }

        if (Auth::check()) {
            return rateb_url(Auth::homePath());
        }

        return rateb_url('site');
    }

    private function isSafeInternalPath(string $path): bool
    {
        if ($path === '' || strpos($path, '://') !== false || strpos($path, '//') === 0) {
            return false;
        }
        // Reject route-template leftovers like site/careers/job/{slug}
        if (str_contains($path, '{') || str_contains($path, '}')) {
            return false;
        }
        $path = ltrim($path, '/');

        return $path !== '' && strpos($path, 'locale/') !== 0;
    }

    private function isSameSiteUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (!is_array($parsed)) {
            return false;
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $refHost = (string) ($parsed['host'] ?? '');
        if ($refHost === '' || strcasecmp($refHost, $host) !== 0) {
            return false;
        }
        $path = (string) ($parsed['path'] ?? '');
        // Platform marketing (rateb.sa/) uses empty public prefix — accept any same-host path.
        if (function_exists('rateb_erp_public_prefix') && rateb_erp_public_prefix() === '') {
            return $path !== '' && strpos($path, '/locale/') === false;
        }
        $base = defined('RATEB_BASE_URL') ? rtrim((string) RATEB_BASE_URL, '/') : '/rateb-erp/public';

        return strpos($path, $base) !== false;
    }
}
