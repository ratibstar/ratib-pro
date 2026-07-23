<?php
declare(strict_types=1);

/**
 * Compatibility shim — prefer views/partials/subscription-alert.php.
 */
$partial = (defined('RATEB_VIEWS_PATH') ? RATEB_VIEWS_PATH : dirname(__DIR__, 3) . '/views')
    . '/partials/subscription-alert.php';
if (is_file($partial)) {
    include $partial;
    return;
}
