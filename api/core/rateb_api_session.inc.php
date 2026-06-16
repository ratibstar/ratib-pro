<?php
/**
 * EN: Handles API endpoint/business logic in `api/core/rateb_api_session.inc.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/core/rateb_api_session.inc.php`.
 */
/**
 * API session name must match pages (config/env/load.php): rateb_control when
 * ?control=1 OR when the rateb_control cookie is sent (RATEB Pro + control SSO).
 * Call only while session_status() === PHP_SESSION_NONE.
 */
if (!function_exists('rateb_api_pick_session_name')) {
    function rateb_api_pick_session_name(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }
        $cookie = isset($_COOKIE['rateb_control']) ? (string) $_COOKIE['rateb_control'] : '';
        if (isset($_GET['control']) && (string) $_GET['control'] === '1') {
            session_name('rateb_control');
        } elseif ($cookie !== '') {
            session_name('rateb_control');
        }
    }
}
