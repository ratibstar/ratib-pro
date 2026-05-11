<?php
declare(strict_types=1);

final class Ratib_ClientDashboard_ClientSecuritySnapshotBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Ratib_ClientDashboard_AdapterContext $ctx): array
    {
        $sessions = [
            ['id' => 'sess:current', 'label' => 'This browser', 'current' => true, 'last_seen' => gmdate('c')],
        ];

        $loginHistory = [];
        $mfa = ['state' => 'unknown', 'enforced' => false];
        $tokens = ['visible_count' => null, 'note' => 'Wire to API token store when available.'];

        try {
            $conn = $ctx->conn;
            if ($conn instanceof mysqli) {
                $loginHistory = $this->loginSlice($conn, 8);
                $extra = $this->sessionRowsFromDb($conn, $ctx->userId);
                if (!empty($extra)) {
                    $sessions = array_merge($sessions, $extra);
                }
            }
        } catch (Throwable $e) {
            $ctx->obs->recordAdapter('security_snapshot', false, $e->getMessage());
        }

        $suspicious = [];

        return [
            'active_sessions' => $sessions,
            'login_history' => $loginHistory,
            'mfa' => $mfa,
            'api_tokens' => $tokens,
            'suspicious_activity' => $suspicious,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loginSlice(mysqli $conn, int $limit): array
    {
        $limit = max(1, min(30, $limit));
        $chk = @$conn->query("SHOW TABLES LIKE 'activity_logs'");
        if (!$chk || $chk->num_rows === 0) {
            return [];
        }
        $pat = '%login%';
        $stmt = @$conn->prepare('SELECT description, created_at FROM activity_logs WHERE description LIKE ? ORDER BY created_at DESC LIMIT ' . $limit);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('s', $pat);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[] = [
                'title' => (string) ($row['description'] ?? ''),
                'at' => (string) ($row['created_at'] ?? ''),
            ];
        }
        $stmt->close();

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sessionRowsFromDb(mysqli $conn, int $userId): array
    {
        $chk = @$conn->query("SHOW TABLES LIKE 'ratib_client_sessions'");
        if (!$chk || $chk->num_rows === 0) {
            return [];
        }
        $stmt = @$conn->prepare('SELECT session_id, label, last_seen_at FROM ratib_client_sessions WHERE user_id = ? ORDER BY last_seen_at DESC LIMIT 5');
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[] = [
                'id' => (string) ($row['session_id'] ?? ''),
                'label' => (string) ($row['label'] ?? 'Device'),
                'current' => false,
                'last_seen' => gmdate('c', strtotime((string) ($row['last_seen_at'] ?? 'now')) ?: time()),
            ];
        }
        $stmt->close();

        return $out;
    }
}
