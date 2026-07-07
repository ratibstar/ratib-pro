<?php

declare(strict_types=1);

/** @var \Rateb\PlatformCatalog\Core\Router $router */

$router->get('/', static function (): void {
    \Rateb\PlatformCatalog\Core\View::render('platform/dashboard/index', [
        'title' => catalog__('app_name'),
    ]);
});
