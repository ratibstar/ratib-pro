<?php
declare(strict_types=1);

/**
 * Shared bootstrap for Phase 6 accounting control REST APIs.
 */
require_once __DIR__ . '/../../../config/env/load.php';
require_once __DIR__ . '/../../../app/Core/Autoloader.php';
\App\Core\Autoloader::register(dirname(__DIR__, 3) . '/app');

use App\Accounting\Admin\AccountingControlBootstrap;

AccountingControlBootstrap::init();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function accounting_control_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function accounting_control_require_auth(string $permission = 'accounting.dashboard'): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $erpAuthed = !empty($_SESSION['rateb_user_id']) || !empty($_SESSION['rateb_admin_id']);
    $siteAuthed = !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
    $controlAuthed = !empty($_SESSION['control_logged_in']);

    if (!$erpAuthed && !$siteAuthed && !$controlAuthed) {
        accounting_control_json(['ok' => false, 'message' => 'Unauthorized'], 401);
    }

    if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
        return;
    }

    if (function_exists('rateb_can') && rateb_can($permission)) {
        return;
    }

    if ($siteAuthed && isset($_SESSION['role_id']) && (int) $_SESSION['role_id'] === 1) {
        return;
    }

    if ($controlAuthed) {
        return;
    }

    accounting_control_json(['ok' => false, 'message' => 'Forbidden: ' . $permission], 403);
}

/**
 * @return array<string, mixed>
 */
function accounting_control_filters(): array
{
    $get = $_GET;
    $body = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $body = $decoded;
        }
    }

    return array_merge($get, $body);
}
