<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/migrations/admin_staff_schema.php';

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "No database\n");
    exit(1);
}

ensure_admin_staff_schema($pdo);
echo "admin_staff_schema=ok\n";