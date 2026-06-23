<?php
declare(strict_types=1);

/**
 * Customer portal API — portal token auth (not agent session).
 */
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

if (!defined('RCC_SKIP_ORCHESTRATOR_BOOT')) {
    define('RCC_SKIP_ORCHESTRATOR_BOOT', true);
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Ratib\ContactCenter\App\Controllers\Api\CustomerPortalApiController;

(new CustomerPortalApiController())->handle();
