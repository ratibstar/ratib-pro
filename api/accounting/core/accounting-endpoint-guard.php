<?php
declare(strict_types=1);

/**
 * Shared guards for accounting migration and diagnostic HTTP endpoints.
 */

if (!function_exists('accounting_endpoint_is_production')) {
    function accounting_endpoint_is_production(): bool
    {
        $env = strtolower(trim((string) (getenv('RATEB_ENV') ?: getenv('APP_ENV') ?: '')));
        if ($env === '') {
            return false;
        }

        return in_array($env, ['production', 'prod', 'live'], true);
    }
}

if (!function_exists('accounting_endpoint_ensure_session')) {
    function accounting_endpoint_ensure_session(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

if (!function_exists('accounting_endpoint_is_enterprise_admin')) {
    function accounting_endpoint_is_enterprise_admin(): bool
    {
        accounting_endpoint_ensure_session();

        if (!empty($_SESSION['rateb_admin_id']) || !empty($_SESSION['rateb_user_id'])) {
            if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
                return true;
            }
            if (function_exists('rateb_can') && rateb_can('accounting.post')) {
                return true;
            }
        }

        if (!empty($_SESSION['control_logged_in'])) {
            return true;
        }

        if (!empty($_SESSION['logged_in']) && !empty($_SESSION['user_id'])) {
            if (isset($_SESSION['role_id']) && (int) $_SESSION['role_id'] === 1) {
                return true;
            }
            if (function_exists('hasPermission') && hasPermission('accounting.post')) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('accounting_require_diagnostic_access')) {
    /**
     * Diagnostics: non-production OR enterprise admin; otherwise 403.
     */
    function accounting_require_diagnostic_access(): void
    {
        accounting_endpoint_ensure_session();

        if (!accounting_endpoint_is_production()) {
            return;
        }

        if (accounting_endpoint_is_enterprise_admin()) {
            return;
        }

        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'ok' => false,
            'message' => 'Diagnostic endpoints are restricted in production.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('accounting_require_migration_access')) {
    /**
     * Migrations: authenticated enterprise admin; production requires explicit admin.
     */
    function accounting_require_migration_access(): void
    {
        accounting_endpoint_ensure_session();

        if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            if (empty($_SESSION['control_logged_in']) && empty($_SESSION['rateb_admin_id']) && empty($_SESSION['rateb_user_id'])) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Unauthorized — authentication required']);
                exit;
            }
        }

        if (!accounting_endpoint_is_enterprise_admin()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Forbidden — enterprise admin permission required (accounting.post or admin role).',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (accounting_endpoint_is_production() && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '');
            if ($token !== '' && function_exists('validateCsrfToken') && !validateCsrfToken($token)) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
                exit;
            }
        }
    }
}
