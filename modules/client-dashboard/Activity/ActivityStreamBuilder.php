<?php
declare(strict_types=1);

final class RATEB_ClientDashboard_ActivityStreamBuilder
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function buildForHttp(?mysqli $conn): array
    {
        require_once dirname(__DIR__) . '/Observability/ObservabilityHub.php';
        require_once dirname(__DIR__) . '/Adapters/AdapterContext.php';
        require_once dirname(__DIR__) . '/Adapters/OrdersAdapter.php';

        $obs = new RATEB_ClientDashboard_ObservabilityHub();
        $ctx = RATEB_ClientDashboard_AdapterContext::fromSession($conn, $obs);
        $orders = (new RATEB_ClientDashboard_OrdersAdapter())->fetchNormalized($ctx);

        return (new self())->build($ctx, $orders);
    }

    /**
     * @param list<array<string, mixed>> $orders
     * @return list<array<string, mixed>>
     */
    public function build(RATEB_ClientDashboard_AdapterContext $ctx, array $orders): array
    {
        $events = [];

        try {
            $conn = $ctx->conn;
            if ($conn instanceof mysqli) {
                $events = array_merge($events, $this->fromActivityLogs($conn, 12));
                $events = array_merge($events, $this->fromSystemEvents($conn, 6));
            }
        } catch (Throwable $e) {
            $ctx->obs->recordAdapter('activity', false, $e->getMessage());
        }

        foreach (array_slice($orders, 0, 5) as $o) {
            $events[] = [
                'id' => 'ord:' . ($o['id'] ?? ''),
                'severity' => 'info',
                'actor' => 'commerce',
                'source' => 'orders',
                'title' => 'Order ' . ($o['id'] ?? '') . ' · ' . ($o['status'] ?? ''),
                'timestamp' => gmdate('c', strtotime((string) ($o['created_at'] ?? 'now')) ?: time()),
            ];
        }

        usort($events, static function ($a, $b): int {
            return strcmp((string) ($b['timestamp'] ?? ''), (string) ($a['timestamp'] ?? ''));
        });

        return array_slice($events, 0, 25);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fromActivityLogs(mysqli $conn, int $limit): array
    {
        $limit = max(1, min(50, $limit));
        $chk = @$conn->query("SHOW TABLES LIKE 'activity_logs'");
        if (!$chk || $chk->num_rows === 0) {
            return [];
        }
        $sql = 'SELECT description, created_at FROM activity_logs ORDER BY created_at DESC LIMIT ' . $limit;
        $r = @$conn->query($sql);
        if (!$r) {
            return [];
        }
        $out = [];
        while ($row = $r->fetch_assoc()) {
            $ts = strtotime((string) ($row['created_at'] ?? ''));
            $out[] = [
                'id' => 'act:' . sha1((string) ($row['created_at'] ?? '') . (string) ($row['description'] ?? '')),
                'severity' => 'info',
                'actor' => 'user',
                'source' => 'activity_logs',
                'title' => (string) ($row['description'] ?? 'Activity'),
                'timestamp' => $ts ? gmdate('c', $ts) : gmdate('c'),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fromSystemEvents(mysqli $conn, int $limit): array
    {
        $limit = max(1, min(30, $limit));
        $chk = @$conn->query("SHOW TABLES LIKE 'system_events'");
        if (!$chk || $chk->num_rows === 0) {
            return [];
        }
        $sql = 'SELECT event_type, created_at FROM system_events ORDER BY created_at DESC LIMIT ' . $limit;
        $r = @$conn->query($sql);
        if (!$r) {
            return [];
        }
        $out = [];
        while ($row = $r->fetch_assoc()) {
            $ts = strtotime((string) ($row['created_at'] ?? ''));
            $out[] = [
                'id' => 'sys:' . sha1((string) ($row['created_at'] ?? '') . (string) ($row['event_type'] ?? '')),
                'severity' => 'info',
                'actor' => 'platform',
                'source' => 'system_events',
                'title' => (string) ($row['event_type'] ?? 'system'),
                'timestamp' => $ts ? gmdate('c', $ts) : gmdate('c'),
            ];
        }

        return $out;
    }
}
