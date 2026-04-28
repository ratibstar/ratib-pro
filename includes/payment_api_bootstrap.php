<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/payment_api_bootstrap.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/payment_api_bootstrap.php`.
 */

declare(strict_types=1);

require_once __DIR__ . '/payment_api_guards.php';
require_once __DIR__ . '/payment_api_throwable_polyfill.php';

/**
 * Minimal bootstrap for N-Genius payment API endpoints only.
 * Avoids includes/config.php (mysqli, core bootstrap, Database singleton) which can
 * fatally error or behave differently on API hits and is not needed for create-order / verify.
 *
 * Skip PHP session: not needed for JSON payment APIs and avoids session save path / header issues.
 */
if (!defined('RATIB_ENV_NO_SESSION')) {
    define('RATIB_ENV_NO_SESSION', true);
}

try {
    require_once __DIR__ . '/../config/env/load.php';
} catch (Throwable $e) {
    $origin = payment_api_root_throwable($e);
    throw new RuntimeException(
        'env/load.php or config/env/<host>.php: ' . $e->getMessage()
            . ' @ ' . basename($origin->getFile()) . ':' . $origin->getLine(),
        0,
        $e
    );
}

try {
    require_once __DIR__ . '/../config/env.php';
} catch (Throwable $e) {
    $origin = payment_api_root_throwable($e);
    throw new RuntimeException(
        'while loading config/env.php: ' . $e->getMessage()
            . ' @ ' . basename($origin->getFile()) . ':' . $origin->getLine(),
        0,
        $e
    );
}
