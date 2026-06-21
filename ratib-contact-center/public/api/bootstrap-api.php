<?php
declare(strict_types=1);

/**
 * RCC HTTP API bootstrap — JSON errors even on fatals.
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
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'ok' => false,
        'error' => 'Unhandled exception',
        'detail' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit(1);
});

require_once dirname(__DIR__, 2) . '/bootstrap.php';
