<?php
declare(strict_types=1);

/**
 * Repair CMS Arabic text using PHP UTF-8 strings (bypasses phpMyAdmin paste encoding issues).
 * Usage: php rateb-erp/bin/fix-cms-arabic.php
 */
$root = realpath(dirname(__DIR__));
if ($root === false) {
    fwrite(STDERR, "Cannot resolve rateb-erp root.\n");
    exit(1);
}

require_once $root . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init($root);

$result = (new Rateb\App\Services\CmsArabicRepairService())->repair();
echo "CMS Arabic repair complete. Rows touched: {$result['updated']}\n";
echo 'Hero title_ar: ' . $result['hero_title'] . "\n";
