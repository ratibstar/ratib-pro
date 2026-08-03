<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Admin;

use Rateb\PlatformCatalog\Application\Support\AdminLocale;
use Rateb\PlatformCatalog\Application\Support\ErpCatalogSsoToken;
use Rateb\PlatformCatalog\Application\Support\ErpSessionIdentityBridge;

final class ErpSsoController
{
    public function __construct(
        private readonly ErpSessionIdentityBridge $identityBridge
    ) {
    }

    public function accept(): void
    {
        if (!catalog_admin_host_allowed()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Forbidden';

            return;
        }

        $token = trim((string) ($_GET['token'] ?? ''));
        $claims = ErpCatalogSsoToken::verify($token);
        if ($claims === null) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo catalog__('admin_unauthorized', AdminLocale::resolve());

            return;
        }

        $platformUserId = $this->identityBridge->mapErpSessionPayload([
            'rateb_user_id' => $claims['erp_user_id'],
            'rateb_user_email' => $claims['email'],
            'rateb_is_super_admin' => $claims['super_admin'],
            'rateb_portal' => $claims['portal'],
        ]);

        if ($platformUserId === null) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo catalog__('admin_forbidden', AdminLocale::resolve());

            return;
        }

        $_SESSION['platform_user_id'] = $platformUserId;
        $_SESSION['catalog_sso_at'] = time();

        $return = catalog_safe_return_url((string) ($_GET['return'] ?? ''));
        if ($return === '') {
            $return = catalog_admin_dashboard_url();
        }

        header('Location: ' . $return, true, 302);
        exit;
    }
}
