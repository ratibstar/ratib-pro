<?php
declare(strict_types=1);

/**
 * Proxy entry for RATEB ERP REST API v1 at /api/v1/rateb/*
 * Forwards to rateb-erp public router.
 */
$_SERVER['REQUEST_URI'] = preg_replace('#^/api/v1/rateb#', '', (string) ($_SERVER['REQUEST_URI'] ?? '')) ?: '/api/v1';
require dirname(__DIR__, 2) . '/rateb-erp/public/index.php';
