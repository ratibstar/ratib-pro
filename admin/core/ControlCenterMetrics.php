<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/core/ControlCenterMetrics.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/core/ControlCenterMetrics.php`.
 */
declare(strict_types=1);

final class ControlCenterMetrics
{
    public static function bump(PDO $controlPdo, string $key, int $delta = 1): void
    {
        if (!self::tableExists($controlPdo, 'admin_control_metrics')) {
            return;
        }
        $key = substr($key, 0, 64);
        try {
            $controlPdo->prepare(
                'INSERT INTO admin_control_metrics (metric_key, metric_value, created_at) VALUES (:k, :v, NOW())'
            )->execute([':k' => $key, ':v' => $delta]);
        } catch (Throwable $e) {
            error_log('ControlCenterMetrics::bump failed: ' . $e->getMessage());
        }
    }

    public static function getCounters(PDO $controlPdo): array
    {
        $out = ['queries_ok' => 0, 'queries_fail' => 0, 'safety_warnings' => 0];
        if (!self::tableExists($controlPdo, 'admin_control_metrics')) {
            return $out;
        }
        try {
            $stmt = $controlPdo->query(
                "SELECT metric_key, SUM(metric_value) AS c FROM admin_control_metrics
                 WHERE created_at > (NOW() - INTERVAL 1 HOUR)
                 GROUP BY metric_key"
            );
            if ($stmt) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $k = (string) ($row['metric_key'] ?? '');
                    $c = (int) ($row['c'] ?? 0);
                    if ($k === 'query_ok') {
                        $out['queries_ok'] = $c;
                    }
                    if ($k === 'query_fail') {
                        $out['queries_fail'] = $c;
                    }
                    if ($k === 'safety_warn') {
                        $out['safety_warnings'] = $c;
                    }
                }
            }
        } catch (Throwable $e) {
            /* ignore */
        }
        return $out;
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $st = $pdo->prepare('SHOW TABLES LIKE :t');
            $st->execute([':t' => $table]);
            return (bool) $st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}
