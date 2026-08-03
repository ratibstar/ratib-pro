<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Shared;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Services\PlatformCatalogSsoService;

/** ERP-side SSO handoff into rateb-platform-catalog admin. */
final class PlatformCatalogSsoController extends Controller
{
    public function start(): void
    {
        if (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Platform catalog is not available on agency hosts.';

            return;
        }

        if (function_exists('rateb_is_platform_oversight_host') && !rateb_is_platform_oversight_host()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Platform catalog SSO is only available on rateb.sa.';

            return;
        }

        $return = $this->safeCatalogReturnUrl((string) ($_GET['return'] ?? ''));

        if (!Auth::check()) {
            $self = rateb_url('platform-catalog/sso');
            if ($return !== '') {
                $self .= (str_contains($self, '?') ? '&' : '?') . 'return=' . rawurlencode($return);
            }
            Response::redirect(rateb_url('login') . '?next=' . rawurlencode($self));

            return;
        }

        if (!function_exists('rateb_is_super_admin') || !rateb_is_super_admin()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Catalog access requires a platform super-admin ERP account.';

            return;
        }

        $token = (new PlatformCatalogSsoService())->issueTokenFromSession();
        if ($token === null) {
            http_response_code(503);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Catalog SSO is not configured (missing shared secret).';

            return;
        }

        Response::redirect(PlatformCatalogSsoService::catalogAuthCallbackUrl($token, $return));
    }

    private function safeCatalogReturnUrl(string $return): string
    {
        $return = trim($return);
        if ($return === '' || str_contains($return, '..')) {
            return '';
        }

        if (preg_match('#^https?://#i', $return)) {
            $host = strtolower((string) parse_url($return, PHP_URL_HOST));
            if (!in_array($host, ['rateb.sa', 'www.rateb.sa', 'localhost', '127.0.0.1'], true)) {
                return '';
            }
            if (!str_contains($return, '/rateb-platform-catalog/')) {
                return '';
            }

            return $return;
        }

        if (!str_starts_with($return, '/rateb-platform-catalog/')) {
            return '';
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
        $scheme = $secure ? 'https' : 'http';

        return $scheme . '://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost') . $return;
    }
}
