<?php

declare(strict_types=1);

use Rateb\App\Pos\Controllers\V2\PosV2BootstrapApiController;
use Rateb\App\Pos\Controllers\V2\PosV2RegisterApiController;
use Rateb\App\Pos\Controllers\V2\PosV2RegisterController;
use Rateb\App\Pos\Middleware\V2\PosV2FeatureGateMiddleware;

require_once RATEB_ROOT . '/routes/middleware-helpers.php';

/** @var Rateb\App\Core\Router $router */

$posApp = static fn (string $sub = ''): string => '/' . rateb_app_route($sub === '' ? 'pos' : 'pos/' . ltrim($sub, '/'));

if (!function_exists('rateb_pos_v2_web_mw')) {
    /**
     * POS V2 web + session API middleware: ERP auth + entity permissions + feature gate.
     *
     * @return list<class-string|array{class-string, string}|array{class-string, string, string}>
     */
    function rateb_pos_v2_web_mw(): array
    {
        return array_merge(
            rateb_erp_mw('pos', '', 'pos/register'),
            [[PosV2FeatureGateMiddleware::class, 'web']],
        );
    }
}

$posV2Mw = rateb_pos_v2_web_mw();

/*
|--------------------------------------------------------------------------
| POS V2 — Web (session-authenticated, admin/ops/pos/*)
|--------------------------------------------------------------------------
| Loaded from public/index.php when POS V2 route registration is enabled (T03).
*/

$router->get($posApp('v2/register'), [PosV2RegisterController::class, 'index'], $posV2Mw);

/*
|--------------------------------------------------------------------------
| POS V2 — Session JSON API (same auth as V1 pos/api/register/*)
|--------------------------------------------------------------------------
*/

$router->get($posApp('api/v2/bootstrap'), [PosV2BootstrapApiController::class, 'bootstrap'], $posV2Mw);
$router->get($posApp('api/v2/register'), [PosV2RegisterApiController::class, 'index'], $posV2Mw);
