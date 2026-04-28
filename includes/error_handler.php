<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/error_handler.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/error_handler.php`.
 */
/**
 * Central error and exception handling.
 * Include from config.php if you want global handling.
 */

if (!function_exists('ratib_exception_handler')) {
    function ratib_exception_handler(Throwable $e) {
        error_log('Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            throw $e;
        }
        if (php_sapi_name() === 'cli') {
            echo 'An error occurred. Check logs.';
        } else {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'An error occurred. Please try again later.']);
        }
        exit(1);
    }
}

if (!function_exists('ratib_error_handler')) {
    function ratib_error_handler($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        error_log("PHP Error [{$severity}]: {$message} in {$file}:{$line}");
        if (defined('DEBUG_MODE') && DEBUG_MODE && in_array($severity, [E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE], true)) {
            return false; // let PHP display in debug
        }
        return true;
    }
}
