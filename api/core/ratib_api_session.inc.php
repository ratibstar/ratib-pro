<?php
/**
 * EN: Handles API endpoint/business logic in `api/core/ratib_api_session.inc.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/core/ratib_api_session.inc.php`.
 */
/**
 * API session name must match pages (config/env/load.php): ratib_control when
 * ?control=1 OR when the ratib_control cookie is sent (Ratib Pro + control SSO).
 * Call only while session_status() === PHP_SESSION_NONE.
 */
if (!function_exists('ratib_api_pick_session_name')) {
    function ratib_api_pick_session_name(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }
        $cookie = isset($_COOKIE['ratib_control']) ? (string) $_COOKIE['ratib_control'] : '';
        if (isset($_GET['control']) && (string) $_GET['control'] === '1') {
            session_name('ratib_control');
        } elseif ($cookie !== '') {
            session_name('ratib_control');
        }
    }
}
