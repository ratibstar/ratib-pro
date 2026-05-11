<?php
/**
 * Idempotency + correlation metadata (optional DB persistence).
 */
declare(strict_types=1);

final class Ratib_ClientDashboard_ActionReliabilityLayer
{
    private const TABLE = 'ratib_client_hub_idempotency';

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null Cached prior response to replay verbatim.
     */
    public static function tryReplay(?mysqli $conn, Ratib_ClientDashboard_TenantScope $tenant, string $verb, string $targetId, array $input): ?array
    {
        $key = isset($input['idempotency_key']) ? trim((string) $input['idempotency_key']) : '';
        if ($key === '' || !$conn instanceof mysqli) {
            return null;
        }

        $scoped = $tenant->isolationKey() . ':' . $key;

        try {
            $chk = @$conn->query('SHOW TABLES LIKE \'' . self::TABLE . '\'');
            if (!$chk || $chk->num_rows === 0) {
                return null;
            }
            $uid = $tenant->userId;
            $stmt = @$conn->prepare('SELECT response_json FROM `' . self::TABLE . '` WHERE user_id = ? AND idempotency_key = ? LIMIT 1');
            if (!$stmt) {
                return null;
            }
            $stmt->bind_param('is', $uid, $scoped);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $decoded = json_decode((string) ($row['response_json'] ?? ''), true);
                $stmt->close();
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
            $stmt->close();
        } catch (Throwable $e) {
            /* ignore */
        }

        return null;
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function remember(?mysqli $conn, Ratib_ClientDashboard_TenantScope $tenant, string $verb, string $targetId, array $input, array $response): void
    {
        $key = isset($input['idempotency_key']) ? trim((string) $input['idempotency_key']) : '';
        if ($key === '' || !$conn instanceof mysqli) {
            return;
        }

        $scoped = $tenant->isolationKey() . ':' . $key;

        try {
            $chk = @$conn->query('SHOW TABLES LIKE \'' . self::TABLE . '\'');
            if (!$chk || $chk->num_rows === 0) {
                return;
            }
            $uid = $tenant->userId;
            $json = json_encode($response, JSON_UNESCAPED_SLASHES);
            if (!is_string($json)) {
                return;
            }
            $stmt = @$conn->prepare(
                'INSERT INTO `' . self::TABLE . '` (user_id, idempotency_key, verb, target_id, response_json, created_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE response_json = VALUES(response_json), verb = VALUES(verb), target_id = VALUES(target_id)'
            );
            if (!$stmt) {
                return;
            }
            $stmt->bind_param('issss', $uid, $scoped, $verb, $targetId, $json);
            @$stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            /* ignore */
        }
    }
}
