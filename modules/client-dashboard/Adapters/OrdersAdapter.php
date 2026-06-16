<?php
declare(strict_types=1);

final class RATEB_ClientDashboard_OrdersAdapter
{
    /**
     * @return list<array<string, mixed>>
     */
    public function fetchNormalized(RATEB_ClientDashboard_AdapterContext $ctx): array
    {
        require_once dirname(__DIR__) . '/Data/FallbackPayloads.php';

        try {
            $conn = $ctx->conn;
            if ($conn instanceof mysqli) {
                $rows = $this->tryRATEBOrdersTable($conn);
                if ($rows !== null) {
                    $ctx->obs->recordAdapter('orders', true, null, ['rows' => count($rows)]);

                    return $rows;
                }
            }
        } catch (Throwable $e) {
            $ctx->obs->recordAdapter('orders', false, $e->getMessage());
        }

        $ctx->obs->recordAdapter('orders', true, 'demo_dataset', ['fallback' => true]);

        return RATEB_ClientDashboard_FallbackPayloads::demoOrdersRows();
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function tryRATEBOrdersTable(mysqli $conn): ?array
    {
        $chk = @$conn->query("SHOW TABLES LIKE 'rateb_client_orders'");
        if (!$chk || $chk->num_rows === 0) {
            return null;
        }
        $sql = 'SELECT order_id AS id, product_label AS product, status, payment_status, created_at, renewal_at FROM rateb_client_orders ORDER BY created_at DESC LIMIT 200';
        $r = @$conn->query($sql);
        if (!$r) {
            return null;
        }
        $out = [];
        while ($row = $r->fetch_assoc()) {
            $out[] = [
                'id' => (string) ($row['id'] ?? ''),
                'product' => (string) ($row['product'] ?? ''),
                'status' => strtolower((string) ($row['status'] ?? 'pending')),
                'payment_status' => strtolower((string) ($row['payment_status'] ?? 'pending')),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'renewal_at' => (string) ($row['renewal_at'] ?? ''),
            ];
        }

        return $out;
    }
}
