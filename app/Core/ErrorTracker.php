<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

final class ErrorTracker
{
    /** @var callable|null */
    private static $pdoResolver = null;
    private static bool $registered = false;

    public static function register(?callable $pdoResolver = null): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        self::$pdoResolver = $pdoResolver;

        set_exception_handler(static function (Throwable $exception): void {
            self::capture($exception);
            self::renderFallback($exception);
        });
    }

    public static function capture(Throwable $exception): void
    {
        try {
            $pdo = self::resolvePdo();
            if (!$pdo instanceof PDO) {
                error_log('[error-tracker:fallback] ' . $exception->getMessage());
                return;
            }
            $stmt = $pdo->prepare(
                'INSERT INTO error_logs (message, stack_trace, request_context, user_id, created_at)
                 VALUES (:message, :stack_trace, :request_context, :user_id, NOW())'
            );
            $context = [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'sapi' => PHP_SAPI,
            ];
            $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : (isset($_SESSION['control_admin_id']) ? (int) $_SESSION['control_admin_id'] : null);
            $stmt->execute([
                ':message' => substr($exception->getMessage(), 0, 1000),
                ':stack_trace' => substr($exception->getTraceAsString(), 0, 65535),
                ':request_context' => json_encode($context, JSON_UNESCAPED_SLASHES) ?: '{}',
                ':user_id' => $userId,
            ]);
        } catch (Throwable $secondary) {
            error_log('[error-tracker:fallback] ' . $secondary->getMessage());
        }
    }

    private static function renderFallback(Throwable $exception): void
    {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, '[Unhandled Exception] ' . $exception->getMessage() . PHP_EOL);
            return;
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'message' => 'Internal server error'], JSON_UNESCAPED_SLASHES);
    }

    private static function resolvePdo(): ?PDO
    {
        if (is_callable(self::$pdoResolver)) {
            try {
                $pdo = (self::$pdoResolver)();
                if ($pdo instanceof PDO) {
                    return $pdo;
                }
            } catch (Throwable) {
                // fall through
            }
        }
        return null;
    }
}
