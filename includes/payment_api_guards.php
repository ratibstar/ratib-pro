<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/payment_api_guards.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/payment_api_guards.php`.
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'payment_api_throwable_polyfill.php';

/**
 * Call from payment API jsonOut() (and early exits) so shutdown does not mis-report
 * a stale error_get_last() as a fatal after a successful response.
 */
function payment_api_mark_completed(): void
{
    $GLOBALS['ratib_payment_api_completed'] = true;
}

/**
 * Register output buffering + shutdown handler so PHP fatals during payment API bootstrap
 * still return JSON (instead of an empty 500 body the browser cannot parse).
 */
function payment_api_register_fatal_json_handler(): void
{
    if (ob_get_level() < 1) {
        ob_start();
    }

    register_shutdown_function(static function () {
        if (!empty($GLOBALS['ratib_payment_api_completed'])) {
            return;
        }
        $err = error_get_last();
        if ($err === null) {
            return;
        }
        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($err['type'], $fatal, true)) {
            return;
        }

        $root = dirname(__DIR__);
        $logDir = $root . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] FATAL ' . $err['message']
            . ' in ' . $err['file'] . ':' . (string) $err['line'] . PHP_EOL;
        @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'payment.log', $line, FILE_APPEND);
        @error_log(trim($line));

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }

        http_response_code(500);
        $payload = [
            'message' => 'Payment setup failed: ' . $err['message'],
            'error' => $err['message'],
        ];
        if (getenv('RATIB_PAYMENT_DEBUG') === '1' || (isset($_SERVER['RATIB_PAYMENT_DEBUG']) && (string) $_SERVER['RATIB_PAYMENT_DEBUG'] === '1')) {
            $payload['debug'] = $err['message'] . ' @ ' . $err['file'] . ':' . (string) $err['line'];
        }
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    });
}
