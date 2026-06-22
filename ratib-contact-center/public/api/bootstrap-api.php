<?php
declare(strict_types=1);

/**
 * RCC HTTP API bootstrap — JSON errors, authentication, CSRF for mutating requests.
 */
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err === null) {
        return;
    }
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'], $fatal, true)) {
        return;
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'ok' => false,
        'error' => 'Fatal error',
        'detail' => $err['message'] . ' in ' . basename((string) $err['file']) . ':' . $err['line'],
    ], JSON_UNESCAPED_UNICODE);
});

set_exception_handler(static function (\Throwable $e): void {
    error_log('[RCC API] ' . $e->getMessage());
    if (!headers_sent()) {
        $code = $e->getCode();
        http_response_code(is_int($code) && $code >= 400 && $code < 600 ? $code : 500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit(1);
});

if (!defined('RCC_SKIP_ORCHESTRATOR_BOOT')) {
    define('RCC_SKIP_ORCHESTRATOR_BOOT', true);
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Ratib\ContactCenter\App\Core\Security\ApiAuthMiddleware;
use Ratib\ContactCenter\App\Core\Security\AuthContext;
use Ratib\ContactCenter\App\Core\Security\WebhookSignatureValidator;
use Ratib\ContactCenter\App\Core\TenantContext;

$action = (string) ($_GET['action'] ?? '');
$publicActions = ['health', 'webhook_whatsapp', 'webhook_email', 'webhook_chat', 'chat_widget_config'];

if (!in_array($action, $publicActions, true)) {
    try {
        (new ApiAuthMiddleware())->authenticate(['rcc.access']);
        TenantContext::set(AuthContext::tenantId());
    } catch (\Throwable $e) {
        http_response_code((int) ($e->getCode() >= 400 ? $e->getCode() : 401));
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
} elseif (in_array($action, ['webhook_whatsapp', 'webhook_email', 'webhook_chat'], true)) {
    $raw = file_get_contents('php://input') ?: '';
    $sig = (string) ($_SERVER['HTTP_X_RCC_SIGNATURE'] ?? $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '');
    if (!WebhookSignatureValidator::validate($raw, $sig, $action)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid webhook signature'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $tenantId = (int) ($_GET['tenant_id'] ?? 0);
    if ($tenantId < 1) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'tenant_id required for webhooks'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    TenantContext::set($tenantId);
}
