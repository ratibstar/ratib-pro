<?php
declare(strict_types=1);

if (!function_exists('requireApiMutationSecurity')) {
    function requireApiMutationSecurity(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            throw new RuntimeException('Method not allowed.');
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $json = json_decode((string) file_get_contents('php://input'), true);
        $json = is_array($json) ? $json : [];
        $token = (string) (
            $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_POST['_csrf']
            ?? $_POST['csrf_token']
            ?? $json['_csrf']
            ?? $json['csrf_token']
            ?? ''
        );
        $stored = (string) ($_SESSION['_csrf_token'] ?? $_SESSION['csrf_token'] ?? '');
        $cookie = (string) ($_COOKIE['rateb_csrf'] ?? '');
        $valid = $token !== ''
            && (($stored !== '' && hash_equals($stored, $token))
                || ($cookie !== '' && hash_equals($cookie, $token)));
        if (!$valid) {
            http_response_code(403);
            throw new RuntimeException('Invalid or missing CSRF token.');
        }
    }
}
