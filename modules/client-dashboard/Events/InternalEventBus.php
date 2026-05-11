<?php
/**
 * Lightweight in-process + audit fan-out (swap for real bus later).
 */
declare(strict_types=1);

final class Ratib_ClientDashboard_InternalEventBus
{
    /** @var array<string, list<callable(array<string, mixed>): void>> */
    private static $listeners = [];

    /**
     * @param callable(array<string, mixed>): void $fn
     */
    public static function subscribe(string $pattern, callable $fn): void
    {
        if (!isset(self::$listeners[$pattern])) {
            self::$listeners[$pattern] = [];
        }
        self::$listeners[$pattern][] = $fn;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function publish(?mysqli $conn, string $event, array $payload): void
    {
        $envelope = array_merge(
            [
                'event' => $event,
                'published_at' => gmdate('c'),
            ],
            $payload
        );

        $auditPath = dirname(__DIR__) . '/Observability/AuditLogger.php';
        if (is_file($auditPath)) {
            require_once $auditPath;
        }
        if (class_exists('Ratib_ClientDashboard_AuditLogger')) {
            Ratib_ClientDashboard_AuditLogger::log($conn, 'internal_event', $envelope);
        }

        foreach (self::$listeners as $pattern => $fns) {
            if ($pattern !== '*' && $pattern !== $event && !self::wildcardMatch($pattern, $event)) {
                continue;
            }
            foreach ($fns as $fn) {
                try {
                    $fn($envelope);
                } catch (\Throwable $e) {
                    error_log('[client-hub-event] listener_error ' . $e->getMessage());
                }
            }
        }
    }

    private static function wildcardMatch(string $pattern, string $event): bool
    {
        if (strpos($pattern, '*') === false) {
            return false;
        }
        $prefix = strstr($pattern, '*', true);

        return $prefix !== false && strpos($event, $prefix) === 0;
    }
}
