<?php
/**
 * EN: Handles application behavior in `tap-payments/error_log.php`.
 * AR: يدير سلوك جزء من التطبيق في `tap-payments/error_log.php`.
 */
/**
 * Tap Payments - Error Logging Helper
 * 
 * Optional error logging utility for debugging payment issues.
 * Enable in pay.php and verify.php by uncommenting error_log calls.
 */

/**
 * Log error to file with timestamp
 * 
 * @param string $message Error message
 * @param array $context Additional context data
 */
function logTapError($message, $context = []) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/tap_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
    $logEntry = "[$timestamp] [$ip] $message$contextStr" . PHP_EOL;
    
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
