<?php
declare(strict_types=1);

/**
 * Resume Control Panel PHP session (rateb_control) for cross-path API auth.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_name('rateb_control');
    $sessSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $sessSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/', '', $sessSecure, true);
    }
    session_start();
}
