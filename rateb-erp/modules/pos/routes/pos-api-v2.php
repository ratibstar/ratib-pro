<?php

declare(strict_types=1);

use Rateb\App\Core\Middleware\ApiAuthMiddleware;
use Rateb\App\Core\Middleware\ApiModuleMiddleware;
use Rateb\App\Pos\Controllers\V2\PosV2BootstrapApiController;
use Rateb\App\Pos\Controllers\V2\PosV2RegisterApiController;
use Rateb\App\Pos\Middleware\V2\PosV2FeatureGateMiddleware;

/** @var Rateb\App\Core\Router $router */

if (!function_exists('rateb_pos_v2_api_mw')) {
    /**
     * POS V2 bearer-token API middleware (mirrors V1 pos-api.php + feature gate).
     *
     * @return list<class-string|array{class-string, string}>
     */
    function rateb_pos_v2_api_mw(): array
    {
        return [
            ApiAuthMiddleware::class,
            [ApiModuleMiddleware::class, 'pos'],
            [PosV2FeatureGateMiddleware::class, 'api'],
        ];
    }
}

$posV2ApiMw = rateb_pos_v2_api_mw();

/*
|--------------------------------------------------------------------------
| POS V2 — REST API (Bearer token, /api/v2/pos/*)
|--------------------------------------------------------------------------
| Loaded from routes/api.php when POS V2 route registration is enabled (T03).
| Mirrors V1 routes in modules/pos/routes/pos-api.php.
*/

$router->get('/api/v2/pos/bootstrap', [PosV2BootstrapApiController::class, 'bootstrap'], $posV2ApiMw);
$router->get('/api/v2/pos/register', [PosV2RegisterApiController::class, 'index'], $posV2ApiMw);
