<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/core/SystemAlerts.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/core/SystemAlerts.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/EventBus.php';

final class SystemAlerts
{
    public static function create(PDO $controlPdo, string $severity, string $eventType, string $message, array $metadata = [], ?int $tenantId = null): void
    {
        $sev = strtoupper(trim($severity));
        if (!in_array($sev, ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], true)) {
            $sev = 'MEDIUM';
        }
        try {
            $dedupeKey = hash('sha256', $sev . '|' . $eventType . '|' . substr($message, 0, 200) . '|' . (string) ($tenantId ?? ''));
            if (function_exists('apcu_inc')) {
                $repeat = apcu_inc('alert_rep:' . $dedupeKey, 1, $success, 120);
                if ($repeat === false) {
                    $repeat = 1;
                }
                if ($repeat > 1 && $repeat < 6) {
                    return;
                }
                if ($repeat >= 6) {
                    $sev = 'CRITICAL';
                }
            } else {
                static $localRep = [];
                $localRep[$dedupeKey] = ($localRep[$dedupeKey] ?? 0) + 1;
                $repeat = $localRep[$dedupeKey];
                if ($repeat > 1 && $repeat < 6) {
                    return;
                }
                if ($repeat >= 6) {
                    $sev = 'CRITICAL';
                }
            }

            switch ($sev) {
                case 'CRITICAL':
                    $level = 'critical';
                    break;
                case 'HIGH':
                    $level = 'error';
                    break;
                case 'MEDIUM':
                    $level = 'warn';
                    break;
                default:
                    $level = 'info';
            }
            emitEvent('ALERT_' . strtoupper(substr($eventType, 0, 64)), $level, substr($message, 0, 512), array_merge($metadata, [
                'tenant_id' => $tenantId,
                'source' => 'system_alert',
                'alert_severity' => $sev,
                'alert_event_type' => substr($eventType, 0, 64),
            ]), $controlPdo);
        } catch (Throwable $e) {
            error_log('SystemAlerts::create failed: ' . $e->getMessage());
        }
    }

    public static function recent(PDO $controlPdo, int $limit = 20): array
    {
        if (!self::tableExists($controlPdo, 'system_events')) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $stmt = $controlPdo->query(
            "SELECT id,
                    UPPER(level) AS severity,
                    event_type,
                    message,
                    tenant_id,
                    created_at
             FROM system_events
             WHERE level IN ('error', 'critical')
                OR event_type LIKE 'ALERT_%'
             ORDER BY id DESC
             LIMIT {$limit}"
        );
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
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
