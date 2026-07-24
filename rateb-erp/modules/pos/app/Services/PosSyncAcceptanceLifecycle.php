<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;
use PDO;

/**
 * Acceptance status machine + CAS claim (Phase 13).
 * No business posting — storage transitions only.
 */
class PosSyncAcceptanceLifecycle
{
    public const WAITING_COMMIT = 'WAITING_COMMIT';
    public const COMMITTING = 'COMMITTING';
    public const COMMITTED = 'COMMITTED';
    public const FAILED = 'FAILED';

    /** @var array<string, array<string, bool>> */
    private const ALLOWED = [
        self::WAITING_COMMIT => [self::COMMITTING => true],
        self::FAILED => [self::COMMITTING => true],
        self::COMMITTING => [self::COMMITTED => true, self::FAILED => true],
        self::COMMITTED => [],
    ];

    public function assertTransition(string $from, string $to): void
    {
        $allowed = self::ALLOWED[$from] ?? [];
        if (empty($allowed[$to])) {
            throw new \RuntimeException('pos_invalid_acceptance_transition:' . $from . '->' . $to);
        }
    }

    /**
     * Atomic CAS claim. No plain SELECT before UPDATE.
     *
     * @return array{ok: bool, row?: array<string, mixed>, reason?: string, already_committed?: bool, in_progress?: bool, retried?: bool}
     */
    public function claim(int $companyId, int $acceptanceId): array
    {
        if ($companyId < 1 || $acceptanceId < 1) {
            return ['ok' => false, 'reason' => 'invalid_ids'];
        }

        $token = 'ct_' . bin2hex(random_bytes(12));
        $now = date('Y-m-d H:i:s');
        $db = Database::connection();

        $stmt = $db->prepare(
            'UPDATE rateb_pos_sync_acceptances
             SET status = :st,
                 commit_token = :tok,
                 committing_at = :at,
                 retry_count = retry_count + 1,
                 last_error = NULL,
                 error_code = NULL,
                 failed_at = NULL
             WHERE company_id = :cid
               AND id = :id
               AND status IN (:s1, :s2)'
        );
        $stmt->bindValue('st', self::COMMITTING);
        $stmt->bindValue('tok', $token);
        $stmt->bindValue('at', $now);
        $stmt->bindValue('cid', $companyId, PDO::PARAM_INT);
        $stmt->bindValue('id', $acceptanceId, PDO::PARAM_INT);
        $stmt->bindValue('s1', self::WAITING_COMMIT);
        $stmt->bindValue('s2', self::FAILED);
        $stmt->execute();

        if ($stmt->rowCount() < 1) {
            $current = $this->fetchById($companyId, $acceptanceId);
            if ($current === null) {
                return ['ok' => false, 'reason' => 'not_found'];
            }
            $status = (string) ($current['status'] ?? '');
            if ($status === self::COMMITTED) {
                return [
                    'ok' => false,
                    'reason' => 'already_committed',
                    'already_committed' => true,
                    'row' => $current,
                ];
            }
            if ($status === self::COMMITTING) {
                return [
                    'ok' => false,
                    'reason' => 'in_progress',
                    'in_progress' => true,
                    'row' => $current,
                ];
            }

            return ['ok' => false, 'reason' => 'claim_rejected', 'row' => $current];
        }

        $row = $this->fetchById($companyId, $acceptanceId);
        if ($row === null || (string) ($row['commit_token'] ?? '') !== $token) {
            return ['ok' => false, 'reason' => 'claim_token_mismatch'];
        }

        $retried = (int) ($row['retry_count'] ?? 1) > 1;

        return [
            'ok' => true,
            'row' => $row,
            'retried' => $retried,
            'commit_token' => $token,
        ];
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function markCommitted(int $companyId, int $acceptanceId, string $commitToken, array $fields): bool
    {
        $this->assertTransition(self::COMMITTING, self::COMMITTED);
        $now = date('Y-m-d H:i:s');
        $stmt = Database::connection()->prepare(
            'UPDATE rateb_pos_sync_acceptances
             SET status = :st,
                 order_id = :oid,
                 committed_at = :cat,
                 processing_ms = :ms,
                 committing_at = NULL,
                 last_error = NULL,
                 error_code = NULL,
                 failed_at = NULL
             WHERE company_id = :cid AND id = :id AND status = :cur AND commit_token = :tok'
        );
        $stmt->execute([
            'st' => self::COMMITTED,
            'oid' => (int) ($fields['order_id'] ?? 0) ?: null,
            'cat' => $now,
            'ms' => isset($fields['processing_ms']) ? (int) $fields['processing_ms'] : null,
            'cid' => $companyId,
            'id' => $acceptanceId,
            'cur' => self::COMMITTING,
            'tok' => $commitToken,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function markFailed(int $companyId, int $acceptanceId, string $commitToken, array $fields): bool
    {
        $this->assertTransition(self::COMMITTING, self::FAILED);
        $now = date('Y-m-d H:i:s');
        $stmt = Database::connection()->prepare(
            'UPDATE rateb_pos_sync_acceptances
             SET status = :st,
                 failed_at = :fat,
                 last_error = :err,
                 error_code = :code,
                 processing_ms = :ms,
                 committing_at = NULL
             WHERE company_id = :cid AND id = :id AND status = :cur AND commit_token = :tok'
        );
        $stmt->execute([
            'st' => self::FAILED,
            'fat' => $now,
            'err' => (string) ($fields['last_error'] ?? ''),
            'code' => (string) ($fields['error_code'] ?? 'commit_failed'),
            'ms' => isset($fields['processing_ms']) ? (int) $fields['processing_ms'] : null,
            'cid' => $companyId,
            'id' => $acceptanceId,
            'cur' => self::COMMITTING,
            'tok' => $commitToken,
        ]);

        return $stmt->rowCount() > 0;
    }

    /** Force COMMITTED from reconcile (COMMITTING or FAILED → COMMITTED via explicit path). */
    public function reconcileCommitted(int $companyId, int $acceptanceId, int $orderId): bool
    {
        $now = date('Y-m-d H:i:s');
        $stmt = Database::connection()->prepare(
            'UPDATE rateb_pos_sync_acceptances
             SET status = :st,
                 order_id = :oid,
                 committed_at = :cat,
                 committing_at = NULL,
                 last_error = NULL,
                 error_code = NULL,
                 failed_at = NULL
             WHERE company_id = :cid AND id = :id AND status IN (:s1, :s2)'
        );
        $stmt->execute([
            'st' => self::COMMITTED,
            'oid' => $orderId,
            'cat' => $now,
            'cid' => $companyId,
            'id' => $acceptanceId,
            's1' => self::COMMITTING,
            's2' => self::FAILED,
        ]);

        return $stmt->rowCount() > 0;
    }

    /** Stale COMMITTING → FAILED (commit_interrupted) when no order found. */
    public function reconcileInterrupted(int $companyId, int $acceptanceId, string $message): bool
    {
        $now = date('Y-m-d H:i:s');
        $stmt = Database::connection()->prepare(
            'UPDATE rateb_pos_sync_acceptances
             SET status = :st,
                 failed_at = :fat,
                 last_error = :err,
                 error_code = :code,
                 committing_at = NULL
             WHERE company_id = :cid AND id = :id AND status = :cur'
        );
        $stmt->execute([
            'st' => self::FAILED,
            'fat' => $now,
            'err' => $message,
            'code' => 'commit_interrupted',
            'cid' => $companyId,
            'id' => $acceptanceId,
            'cur' => self::COMMITTING,
        ]);

        return $stmt->rowCount() > 0;
    }

    /** @return array<string, mixed>|null */
    public function fetchById(int $companyId, int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rateb_pos_sync_acceptances WHERE company_id = :cid AND id = :id LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function fetchByServerSyncId(int $companyId, string $serverSyncId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rateb_pos_sync_acceptances
             WHERE company_id = :cid AND server_sync_id = :sid LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'sid' => $serverSyncId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function fetchBySyncKey(int $companyId, string $syncKey): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rateb_pos_sync_acceptances
             WHERE company_id = :cid AND sync_key = :sk LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'sk' => $syncKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listStaleCommitting(int $companyId, int $ttlSeconds, int $limit = 50): array
    {
        $cutoff = date('Y-m-d H:i:s', time() - max(30, $ttlSeconds));
        $safe = max(1, min(100, $limit));
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rateb_pos_sync_acceptances
             WHERE company_id = :cid AND status = :st
               AND committing_at IS NOT NULL AND committing_at < :cut
             ORDER BY committing_at ASC
             LIMIT ' . $safe
        );
        $stmt->execute([
            'cid' => $companyId,
            'st' => self::COMMITTING,
            'cut' => $cutoff,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listFailedWithoutOrder(int $companyId, int $limit = 50): array
    {
        $safe = max(1, min(100, $limit));
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rateb_pos_sync_acceptances
             WHERE company_id = :cid AND status = :st
               AND (order_id IS NULL OR order_id = 0)
             ORDER BY failed_at DESC
             LIMIT ' . $safe
        );
        $stmt->execute(['cid' => $companyId, 'st' => self::FAILED]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
