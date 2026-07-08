<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Support\Request;

catalog_test('Request method reads SERVER value', static function (): void {
    $_SERVER['REQUEST_METHOD'] = 'PATCH';
    catalog_assert_same('PATCH', Request::method());
    unset($_SERVER['REQUEST_METHOD']);
});

catalog_test('Request detects JSON content type', static function (): void {
    $_SERVER['CONTENT_TYPE'] = 'application/json; charset=UTF-8';
    catalog_assert_true(Request::isJson());
    unset($_SERVER['CONTENT_TYPE']);
});

catalog_test('Request header lookup is case-insensitive for HTTP_ prefix', static function (): void {
    $_SERVER['HTTP_X_RATEB_LOCALE'] = 'ar';
    catalog_assert_same('ar', Request::header('X-Rateb-Locale'));
    unset($_SERVER['HTTP_X_RATEB_LOCALE']);
});

catalog_test('Request rawBody is cached for repeated reads', static function (): void {
    Request::seedRawBodyForTesting('{"cached":true}');
    $_SERVER['CONTENT_TYPE'] = 'application/json';

    catalog_assert_same('{"cached":true}', Request::rawBody());
    catalog_assert_same('{"cached":true}', Request::rawBody());
    catalog_assert_true(Request::jsonBody()['cached'] ?? false);

    Request::resetCachedInput();
    unset($_SERVER['CONTENT_TYPE']);
});

catalog_test('Request resolvePath strips catalog mount prefix', static function (): void {
    $cases = [
        ['/rateb-platform-catalog/health', '/rateb-platform-catalog/public/index.php', null, '/health'],
        ['/rateb-platform-catalog/admin', '/rateb-platform-catalog/public/index.php', null, '/admin'],
        ['/rateb-platform-catalog/public/health', '/rateb-platform-catalog/public/index.php', null, '/health'],
        ['/rateb-platform-catalog/health', '/rateb-platform-catalog/public/index.php', 'health', '/health'],
    ];

    foreach ($cases as [$uri, $script, $route, $expected]) {
        unset($_GET['route'], $_SERVER['PATH_INFO']);
        if ($route !== null) {
            $_GET['route'] = $route;
        }
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['SCRIPT_NAME'] = $script;
        catalog_assert_same($expected, Request::resolvePath(), $uri);
    }

    unset($_GET['route'], $_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME'], $_SERVER['PATH_INFO']);
});
