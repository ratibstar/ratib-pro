<?php

declare(strict_types=1);

catalog_test('catalog_admin_host_allowed permits rateb.sa', static function (): void {
    $_SERVER['HTTP_HOST'] = 'rateb.sa';
    catalog_assert_true(catalog_admin_host_allowed());
});

catalog_test('catalog_admin_host_allowed blocks agency host test.rateb.sa', static function (): void {
    $_SERVER['HTTP_HOST'] = 'test.rateb.sa';
    catalog_assert_same(false, catalog_admin_host_allowed());
});
