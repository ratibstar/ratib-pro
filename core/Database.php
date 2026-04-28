<?php
/**
 * EN: Handles core framework/runtime behavior in `core/Database.php`.
 * AR: يدير سلوك النواة والإطار الأساسي للتشغيل في `core/Database.php`.
 */
/**
 * Canonical PDO Database is api/core/Database.php (singleton + country/agency DB).
 * This file exists so legacy paths (core/bootstrap, require_once __DIR__+'/Database.php') load one class only.
 */
if (class_exists('Database', false)) {
    return;
}
require_once __DIR__ . '/../api/core/Database.php';
