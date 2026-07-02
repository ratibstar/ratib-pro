<?php
declare(strict_types=1);

/**
 * Canonical health URL — delegates to rateb-erp/public/erp-health.php
 * (deploy verify + monitoring expect GET /erp-health.php → {"status":"ok"})
 */
require __DIR__ . '/rateb-erp/public/erp-health.php';
