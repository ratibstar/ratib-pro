<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/migrations/payment_commerce_schema.php';
require_once dirname(__DIR__) . '/migrations/platform_settings_schema.php';

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "No database\n");
    exit(1);
}

ensure_payment_commerce_schema($pdo);
ensure_platform_settings_schema($pdo);
echo "commerce_schema=ok\n";