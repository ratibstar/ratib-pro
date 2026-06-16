<?php
/**
 * Persists hub audit when optional table exists; always logs to PHP error_log (non-fatal).
 */
declare(strict_types=1);

final class RATEB_ClientDashboard_AuditLogger
{
    private const TABLE = 'rateb_client_hub_audit';

    /**
     * @param array<string, mixed> $payload
     */
    public static function log(?mysqli $conn, string $category, array $payload): void
    {
        $line = json_encode(
            array_merge(['category' => $category, 'at' => gmdate('c')], $payload),
            JSON_UNESCAPED_SLASHES
        );
        if (is_string($line)) {
            error_log('[client-hub] ' . $line);
        }

        if (!$conn instanceof mysqli) {
            return;
        }

        try {
            $chk = @$conn->query('SHOW TABLES LIKE \'' . self::TABLE . '\'');
            if (!$chk || $chk->num_rows === 0) {
                return;
            }
            $uid = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
            if (!is_string($json)) {
                return;
            }
            $stmt = @$conn->prepare(
                'INSERT INTO `' . self::TABLE . '` (user_id, category, payload_json, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())'
            );
            if (!$stmt) {
                return;
            }
            $stmt->bind_param('iss', $uid, $category, $json);
            @$stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            /* never throw */
        }
    }
}
