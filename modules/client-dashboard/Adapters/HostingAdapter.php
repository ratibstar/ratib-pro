<?php
declare(strict_types=1);

final class Ratib_ClientDashboard_HostingAdapter
{
    /**
     * @return list<array<string, mixed>>
     */
    public function fetchNormalized(Ratib_ClientDashboard_AdapterContext $ctx): array
    {
        try {
            $conn = $ctx->conn;
            if ($conn instanceof mysqli) {
                $rows = $this->tryHostingServices($conn);
                if ($rows !== null) {
                    $ctx->obs->recordAdapter('hosting', true, null, ['rows' => count($rows)]);

                    return $rows;
                }
            }
        } catch (Throwable $e) {
            $ctx->obs->recordAdapter('hosting', false, $e->getMessage());
        }

        $ctx->obs->recordAdapter('hosting', true, 'synthetic', ['fallback' => true]);

        return [
            $this->serviceRow('host:shared:1', 'hosting', 'shared', 'active', 'paid', 'healthy', 'auto', 'panel:acct/shared-1', ['restart', 'upgrade']),
            $this->serviceRow('host:vps:1', 'vps', 'cloud', 'active', 'paid', 'healthy', 'auto', 'provider:node-7', ['restart', 'suspend', 'upgrade']),
        ];
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function tryHostingServices(mysqli $conn): ?array
    {
        $chk = @$conn->query("SHOW TABLES LIKE 'ratib_client_services'");
        if (!$chk || $chk->num_rows === 0) {
            return null;
        }
        $r = @$conn->query("SELECT service_id, type, provider, status, billing_state, health_state, renewal_state, quick_actions_json, infrastructure_binding FROM ratib_client_services WHERE type IN ('hosting','vps','email','ssl') ORDER BY service_id ASC LIMIT 100");
        if (!$r) {
            return null;
        }
        $out = [];
        while ($row = $r->fetch_assoc()) {
            $qa = [];
            if (!empty($row['quick_actions_json'])) {
                $dec = json_decode((string) $row['quick_actions_json'], true);
                if (is_array($dec)) {
                    $qa = $dec;
                }
            }
            $out[] = [
                'service_id' => (string) ($row['service_id'] ?? ''),
                'type' => (string) ($row['type'] ?? ''),
                'provider' => (string) ($row['provider'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'billing_state' => (string) ($row['billing_state'] ?? ''),
                'health_state' => (string) ($row['health_state'] ?? ''),
                'renewal_state' => (string) ($row['renewal_state'] ?? ''),
                'quick_actions' => $qa,
                'infrastructure_binding' => (string) ($row['infrastructure_binding'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param list<string> $actions
     * @return array<string, mixed>
     */
    private function serviceRow(
        string $id,
        string $type,
        string $provider,
        string $status,
        string $billing,
        string $health,
        string $renewal,
        string $bind,
        array $actions
    ): array {
        return [
            'service_id' => $id,
            'type' => $type,
            'provider' => $provider,
            'status' => $status,
            'billing_state' => $billing,
            'health_state' => $health,
            'renewal_state' => $renewal,
            'quick_actions' => $actions,
            'infrastructure_binding' => $bind,
        ];
    }
}
