<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * PERF-P1 — Server-Timing for navigation profiling (no business logic).
 */
final class ServerTiming
{
    /** @var array<string, array{start: float, end?: float, desc: string}> */
    private static array $marks = [];
    private static bool $armed = false;
    private static float $t0 = 0.0;

    public static function arm(): void
    {
        if (self::$armed || PHP_SAPI === 'cli') {
            return;
        }
        self::$armed = true;
        self::$t0 = hrtime(true);
        self::mark('bootstrap');
        register_shutdown_function(static function (): void {
            self::flush();
        });
    }

    public static function mark(string $name, string $desc = ''): void
    {
        if (!self::$armed) {
            return;
        }
        $key = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $name) ?: 'mark';
        self::$marks[$key] = [
            'start' => (hrtime(true) - self::$t0) / 1e6,
            'desc' => $desc !== '' ? $desc : $key,
        ];
    }

    public static function end(string $name): void
    {
        if (!self::$armed) {
            return;
        }
        $key = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $name) ?: 'mark';
        if (!isset(self::$marks[$key])) {
            self::mark($key);
        }
        self::$marks[$key]['end'] = (hrtime(true) - self::$t0) / 1e6;
    }

    public static function flush(): void
    {
        if (!self::$armed || headers_sent()) {
            return;
        }
        $total = (hrtime(true) - self::$t0) / 1e6;
        self::end('bootstrap');
        if (!isset(self::$marks['total'])) {
            self::$marks['total'] = ['start' => 0.0, 'end' => $total, 'desc' => 'total'];
        } else {
            self::$marks['total']['end'] = $total;
        }

        $parts = [];
        foreach (self::$marks as $name => $row) {
            $dur = isset($row['end'])
                ? max(0.0, (float) $row['end'] - (float) $row['start'])
                : max(0.0, $total - (float) $row['start']);
            $desc = addcslashes((string) ($row['desc'] ?? $name), '\\"');
            $parts[] = sprintf('%s;dur=%.2f;desc="%s"', $name, $dur, $desc);
        }
        if ($parts === []) {
            return;
        }
        header('Server-Timing: ' . implode(', ', array_slice($parts, 0, 12)), false);
        header('X-Rateb-Server-Timing: 1', false);
    }
}
