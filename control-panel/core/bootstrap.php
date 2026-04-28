<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/core/bootstrap.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/core/bootstrap.php`.
 */
/**
 * Control Panel Bootstrap - minimal core (no multi-tenant)
 */
if (defined('CORE_BOOTSTRAP_LOADED')) {
    return;
}
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/BaseModel.php';
define('CORE_BOOTSTRAP_LOADED', true);
