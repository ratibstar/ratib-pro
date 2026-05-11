<?php
/**
 * Builds home snapshot JSON; enriches from tenant DB when tables exist (graceful).
 */
declare(strict_types=1);

final class Ratib_ClientDashboard_SnapshotBuilder
{
    /**
     * @param mysqli|null $conn
     * @return array<string, mixed>
     */
    public static function build(?mysqli $conn): array
    {
        require_once dirname(__DIR__) . '/Data/FallbackPayloads.php';
        $base = Ratib_ClientDashboard_FallbackPayloads::homeSnapshotEnvelope();
        $widgets = $base['widgets'];
        $source = 'fallback';

        if ($conn instanceof mysqli) {
            try {
                $inv = self::safeCount($conn, 'accounting_invoices');
                if ($inv !== null) {
                    $widgets['billing_summary']['currency'] = 'SAR';
                    $widgets['billing_summary']['invoice_count'] = $inv;
                    $source = 'partial_db';
                }
            } catch (Throwable $e) {
                /* keep fallback */
            }

            try {
                $recent = self::recentActivitySlice($conn, 4);
                if (!empty($recent)) {
                    $widgets['activity_feed'] = $recent;
                    $source = 'partial_db';
                }
            } catch (Throwable $e) {
                /* keep fallback */
            }
        }

        $base['widgets'] = $widgets;
        $base['source'] = $source;
        return $base;
    }

    private static function safeCount(mysqli $conn, string $table): ?int
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
        if ($table === '') {
            return null;
        }
        $chk = @$conn->query("SHOW TABLES LIKE '{$table}'");
        if (!$chk || $chk->num_rows === 0) {
            return null;
        }
        $r = @$conn->query("SELECT COUNT(*) AS c FROM `{$table}`");
        if (!$r) {
            return null;
        }
        $row = $r->fetch_assoc();
        return isset($row['c']) ? (int) $row['c'] : null;
    }

    /**
     * @return list<array<string, string>>
     */
    private static function recentActivitySlice(mysqli $conn, int $limit): array
    {
        $limit = max(1, min(20, $limit));
        $chk = @$conn->query("SHOW TABLES LIKE 'activity_logs'");
        if (!$chk || $chk->num_rows === 0) {
            return [];
        }
        $sql = "SELECT description, created_at FROM activity_logs ORDER BY created_at DESC LIMIT " . $limit;
        $r = @$conn->query($sql);
        if (!$r) {
            return [];
        }
        $out = [];
        while ($row = $r->fetch_assoc()) {
            $out[] = [
                'title' => (string) ($row['description'] ?? 'Activity'),
                'at' => (string) ($row['created_at'] ?? ''),
            ];
        }
        return $out;
    }
}
