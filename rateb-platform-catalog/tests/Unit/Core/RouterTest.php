<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Core\Router;

catalog_test('Router dispatches GET route with params', static function (): void {
    $router = new Router();
    $captured = null;

    $router->get('/catalog/items/{uuid}', static function (array $params) use (&$captured): void {
        $captured = $params;
    });

    ob_start();
    $router->dispatch('GET', '/catalog/items/abc-123');
    ob_end_clean();

    catalog_assert_same(['uuid' => 'abc-123'], $captured);
});

catalog_test('Router renders HTML 404 for non-catalog paths', static function (): void {
    $router = new Router();

    ob_start();
    $router->dispatch('GET', '/missing-page');
    $output = (string) ob_get_clean();

    catalog_assert_true(str_contains($output, '404') || str_contains($output, 'not found') || str_contains($output, 'غير موجودة'));
});
