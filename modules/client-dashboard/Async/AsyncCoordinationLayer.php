<?php
/**
 * Provider-agnostic job envelope (DB optional). Never blocks HTTP success path.
 */
declare(strict_types=1);

final class RATEB_ClientDashboard_AsyncCoordinationLayer
{
    private const TABLE = 'rateb_client_hub_jobs';

    /**
     * @param array<string, mixed> $meta
     * @return array{job_id: string|null, state: string, provider: string}
     */
    public static function enqueue(?mysqli $conn, string $verb, string $targetId, RATEB_ClientDashboard_TenantScope $tenant, string $correlationId, array $meta = []): array
    {
        $jobId = 'local:' . bin2hex(random_bytes(6));

        if (!$conn instanceof mysqli) {
            return ['job_id' => null, 'state' => 'memory_only', 'provider' => 'none'];
        }

        try {
            $chk = @$conn->query('SHOW TABLES LIKE \'' . self::TABLE . '\'');
            if (!$chk || $chk->num_rows === 0) {
                return ['job_id' => null, 'state' => 'deferred_no_store', 'provider' => 'none'];
            }

            $payload = json_encode(
                array_merge($meta, [
                    'verb' => $verb,
                    'target_id' => $targetId,
                    'tenant' => $tenant->toMeta(),
                    'correlation_id' => $correlationId,
                ]),
                JSON_UNESCAPED_SLASHES
            );
            if (!is_string($payload)) {
                return ['job_id' => null, 'state' => 'encode_failed', 'provider' => 'db'];
            }

            $stmt = @$conn->prepare(
                'INSERT INTO `' . self::TABLE . '` (job_id, user_id, state, payload_json, attempts, max_attempts, available_at, created_at) VALUES (?, ?, \'QUEUED\', ?, 0, 5, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            if (!$stmt) {
                return ['job_id' => null, 'state' => 'prepare_failed', 'provider' => 'db'];
            }
            $uid = $tenant->userId;
            $stmt->bind_param('sis', $jobId, $uid, $payload);
            @$stmt->execute();
            $stmt->close();

            return ['job_id' => $jobId, 'state' => 'QUEUED', 'provider' => 'db'];
        } catch (Throwable $e) {
            return ['job_id' => null, 'state' => 'error', 'provider' => 'db'];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function tail(?mysqli $conn, int $userId, int $limit = 5): array
    {
        if (!$conn instanceof mysqli) {
            return [];
        }
        $limit = max(1, min(20, $limit));
        try {
            $chk = @$conn->query('SHOW TABLES LIKE \'' . self::TABLE . '\'');
            if (!$chk || $chk->num_rows === 0) {
                return [];
            }
            $stmt = @$conn->prepare('SELECT job_id, state, attempts, created_at FROM `' . self::TABLE . '` WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . $limit);
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            $out = [];
            while ($row = $res->fetch_assoc()) {
                $out[] = [
                    'job_id' => (string) ($row['job_id'] ?? ''),
                    'state' => (string) ($row['state'] ?? ''),
                    'attempts' => (int) ($row['attempts'] ?? 0),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
            }
            $stmt->close();

            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }
}
