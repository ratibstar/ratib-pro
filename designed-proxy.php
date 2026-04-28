<?php
/**
 * EN: Handles application behavior in `designed-proxy.php`.
 * AR: يدير سلوك جزء من التطبيق في `designed-proxy.php`.
 */

declare(strict_types=1);

/**
 * Legacy entry; prefer public/index.php + includes/designed_bootstrap.php.
 * Keeps old bookmarks working: redirects to /Designed/ or runs the app.
 */
require_once __DIR__ . '/includes/designed_bootstrap.php';
ratib_serve_designed_if_requested();

header('Location: /Designed/', true, 302);
exit;
