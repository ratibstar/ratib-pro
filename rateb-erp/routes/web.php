<?php
declare(strict_types=1);

/**
 * Legacy aggregator — Phase AA.3 split into routes/modules/{auth,dashboard,platform}.php
 * Kept so existing require paths still load the full former web.php set in order.
 */
/** @var Rateb\App\Core\Router $router */

require RATEB_ROOT . '/routes/modules/auth.php';
require RATEB_ROOT . '/routes/modules/dashboard.php';
require RATEB_ROOT . '/routes/modules/platform.php';
