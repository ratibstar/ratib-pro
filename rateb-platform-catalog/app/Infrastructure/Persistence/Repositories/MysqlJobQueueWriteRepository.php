<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\JobQueueWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;
use Rateb\PlatformCatalog\Infrastructure\Queue\RetryPolicy;

final class MysqlJobQueueWriteRepository extends BaseRepository implements JobQueueWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'job_queue';
    }

    public function push(Job $job, ?\DateTimeImmutable $availableAt = null): string
    {
        return $this->transaction(function () use ($job, $availableAt): string {
            if ($job->idempotencyKey !== null) {
                $existing = $this->fetchOne(
                    'SELECT job_id, status FROM job_queue
                     WHERE job_type = :job_type AND idempotency_key = :idempotency_key
                       AND status IN ("pending", "processing", "failed", "dead", "completed")
                     LIMIT 1',
                    ['job_type' => $job->jobType, 'idempotency_key' => $job->idempotencyKey],
                    false
                );
                if ($existing !== null) {
                    return (string) $existing['job_id'];
                }
            }

            $this->writePdo->prepare(
                'INSERT INTO job_queue (job_id, queue, job_type, payload, idempotency_key, max_attempts, available_at)
                 VALUES (:job_id, :queue, :job_type, :payload, :idempotency_key, :max_attempts, :available_at)'
            )->execute([
                'job_id' => $job->jobId,
                'queue' => $job->queue,
                'job_type' => $job->jobType,
                'payload' => json_encode($job->payload, JSON_UNESCAPED_UNICODE) ?: '{}',
                'idempotency_key' => $job->idempotencyKey,
                'max_attempts' => $job->maxAttempts,
                'available_at' => ($availableAt ?? new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
            ]);

            return $job->jobId;
        });
    }

    public function pop(string $queue, ?string $workerId = null, int $visibilityTimeoutSeconds = 300): ?Job
    {
        return $this->transaction(function () use ($queue, $workerId, $visibilityTimeoutSeconds): ?Job {
            $row = $this->fetchOne(
                'SELECT job_id, queue, job_type, payload, idempotency_key, attempts, max_attempts
                 FROM job_queue
                 WHERE queue = :queue AND status = "pending" AND available_at <= CURRENT_TIMESTAMP(6)
                 ORDER BY available_at ASC, id ASC
                 LIMIT 1 FOR UPDATE',
                ['queue' => $queue],
                false
            );
            if ($row === null) {
                return null;
            }

            $this->writePdo->prepare(
                'UPDATE job_queue SET status = "processing", started_at = CURRENT_TIMESTAMP(6),
                 attempts = attempts + 1, locked_by = :locked_by,
                 heartbeat_at = CURRENT_TIMESTAMP(6),
                 visibility_timeout_at = DATE_ADD(CURRENT_TIMESTAMP(6), INTERVAL :timeout SECOND),
                 updated_at = CURRENT_TIMESTAMP(6)
                 WHERE job_id = :job_id'
            )->execute([
                'job_id' => $row['job_id'],
                'locked_by' => $workerId,
                'timeout' => $visibilityTimeoutSeconds,
            ]);

            $payload = json_decode((string) $row['payload'], true);

            return new Job(
                jobId: (string) $row['job_id'],
                queue: (string) $row['queue'],
                jobType: (string) $row['job_type'],
                payload: is_array($payload) ? $payload : [],
                idempotencyKey: isset($row['idempotency_key']) ? (string) $row['idempotency_key'] : null,
                maxAttempts: (int) $row['max_attempts'],
                attempts: (int) $row['attempts'] + 1
            );
        });
    }

    public function heartbeat(string $jobId, string $workerId, int $visibilityTimeoutSeconds = 300): void
    {
        $this->writePdo->prepare(
            'UPDATE job_queue SET heartbeat_at = CURRENT_TIMESTAMP(6),
             visibility_timeout_at = DATE_ADD(CURRENT_TIMESTAMP(6), INTERVAL :timeout SECOND),
             updated_at = CURRENT_TIMESTAMP(6)
             WHERE job_id = :job_id AND status = "processing" AND locked_by = :worker_id'
        )->execute([
            'job_id' => $jobId,
            'worker_id' => $workerId,
            'timeout' => $visibilityTimeoutSeconds,
        ]);
    }

    public function recoverStaleJobs(int $visibilityTimeoutSeconds = 300): int
    {
        return $this->transaction(function () use ($visibilityTimeoutSeconds): int {
            $stmt = $this->writePdo->prepare(
                'UPDATE job_queue SET status = "pending", locked_by = NULL,
                 heartbeat_at = NULL, visibility_timeout_at = NULL,
                 available_at = CURRENT_TIMESTAMP(6), updated_at = CURRENT_TIMESTAMP(6)
                 WHERE status = "processing"
                   AND visibility_timeout_at IS NOT NULL
                   AND visibility_timeout_at < CURRENT_TIMESTAMP(6)'
            );
            $stmt->execute();

            $this->writePdo->prepare(
                'DELETE FROM queue_worker_locks WHERE expires_at < CURRENT_TIMESTAMP(6)'
            )->execute();

            return $stmt->rowCount();
        });
    }

    public function acquireWorkerLock(string $workerId, string $queue, int $ttlSeconds = 60): bool
    {
        return $this->transaction(function () use ($workerId, $queue, $ttlSeconds): bool {
            $this->writePdo->prepare(
                'DELETE FROM queue_worker_locks WHERE queue = :queue AND expires_at < CURRENT_TIMESTAMP(6)'
            )->execute(['queue' => $queue]);

            $existing = $this->fetchOne(
                'SELECT worker_id FROM queue_worker_locks WHERE queue = :queue LIMIT 1 FOR UPDATE',
                ['queue' => $queue],
                false
            );
            if ($existing !== null && (string) $existing['worker_id'] !== $workerId) {
                return false;
            }

            if ($existing === null) {
                $this->writePdo->prepare(
                    'INSERT INTO queue_worker_locks (worker_id, queue, heartbeat_at, expires_at)
                     VALUES (:worker_id, :queue, CURRENT_TIMESTAMP(6), DATE_ADD(CURRENT_TIMESTAMP(6), INTERVAL :ttl SECOND))'
                )->execute(['worker_id' => $workerId, 'queue' => $queue, 'ttl' => $ttlSeconds]);
            } else {
                $this->touchWorkerLock($workerId, $queue, $ttlSeconds);
            }

            return true;
        });
    }

    public function releaseWorkerLock(string $workerId, string $queue): void
    {
        $this->writePdo->prepare(
            'DELETE FROM queue_worker_locks WHERE queue = :queue AND worker_id = :worker_id'
        )->execute(['queue' => $queue, 'worker_id' => $workerId]);
    }

    public function touchWorkerLock(string $workerId, string $queue, int $ttlSeconds = 60): void
    {
        $this->writePdo->prepare(
            'UPDATE queue_worker_locks SET heartbeat_at = CURRENT_TIMESTAMP(6),
             expires_at = DATE_ADD(CURRENT_TIMESTAMP(6), INTERVAL :ttl SECOND)
             WHERE queue = :queue AND worker_id = :worker_id'
        )->execute(['queue' => $queue, 'worker_id' => $workerId, 'ttl' => $ttlSeconds]);
    }

    public function acknowledge(string $jobId): void
    {
        $this->transaction(function () use ($jobId): void {
            $this->writePdo->prepare(
                'UPDATE job_queue SET status = "completed", completed_at = CURRENT_TIMESTAMP(6),
                 locked_by = NULL, heartbeat_at = NULL, visibility_timeout_at = NULL, updated_at = CURRENT_TIMESTAMP(6)
                 WHERE job_id = :job_id'
            )->execute(['job_id' => $jobId]);
        });
    }

    public function fail(string $jobId, string $reason): void
    {
        $this->transaction(function () use ($jobId, $reason): void {
            $row = $this->fetchOne(
                'SELECT job_id, queue, job_type, payload, attempts, max_attempts FROM job_queue WHERE job_id = :job_id LIMIT 1',
                ['job_id' => $jobId],
                false
            );
            if ($row === null) {
                return;
            }

            $attempts = (int) $row['attempts'];
            $maxAttempts = (int) $row['max_attempts'];
            if ($attempts >= $maxAttempts) {
                $this->writePdo->prepare(
                    'UPDATE job_queue SET status = "dead", last_error = :reason,
                     locked_by = NULL, heartbeat_at = NULL, visibility_timeout_at = NULL, updated_at = CURRENT_TIMESTAMP(6)
                     WHERE job_id = :job_id'
                )->execute(['job_id' => $jobId, 'reason' => $reason]);

                $this->writePdo->prepare(
                    'INSERT INTO job_dead_letter_log (uuid, job_id, queue, job_type, payload, last_error, expires_at)
                     VALUES (:uuid, :job_id, :queue, :job_type, :payload, :last_error, DATE_ADD(CURRENT_TIMESTAMP(6), INTERVAL 90 DAY))'
                )->execute([
                    'uuid' => $this->newUuid(),
                    'job_id' => $row['job_id'],
                    'queue' => $row['queue'],
                    'job_type' => $row['job_type'],
                    'payload' => $row['payload'],
                    'last_error' => $reason,
                ]);

                return;
            }

            $delay = RetryPolicy::delaySecondsForAttempt($attempts + 1);
            $this->writePdo->prepare(
                'UPDATE job_queue SET status = "failed", last_error = :reason,
                 locked_by = NULL, heartbeat_at = NULL, visibility_timeout_at = NULL,
                 available_at = DATE_ADD(CURRENT_TIMESTAMP(6), INTERVAL :delay SECOND),
                 updated_at = CURRENT_TIMESTAMP(6)
                 WHERE job_id = :job_id'
            )->execute(['job_id' => $jobId, 'reason' => $reason, 'delay' => $delay]);
        });
    }

    public function retry(string $jobId): void
    {
        $this->transaction(function () use ($jobId): void {
            $this->writePdo->prepare(
                'UPDATE job_queue SET status = "pending", available_at = CURRENT_TIMESTAMP(6), updated_at = CURRENT_TIMESTAMP(6)
                 WHERE job_id = :job_id AND status = "failed"'
            )->execute(['job_id' => $jobId]);
        });
    }

    public function replayDead(string $jobId): bool
    {
        return $this->transaction(function () use ($jobId): bool {
            $stmt = $this->writePdo->prepare(
                'UPDATE job_queue SET status = "pending", attempts = 0, available_at = CURRENT_TIMESTAMP(6),
                 last_error = NULL, locked_by = NULL, heartbeat_at = NULL, visibility_timeout_at = NULL,
                 updated_at = CURRENT_TIMESTAMP(6)
                 WHERE job_id = :job_id AND status = "dead"'
            );
            $stmt->execute(['job_id' => $jobId]);

            return $stmt->rowCount() > 0;
        });
    }

    public function cancelPending(string $jobId): bool
    {
        return $this->transaction(function () use ($jobId): bool {
            $stmt = $this->writePdo->prepare(
                'UPDATE job_queue SET status = "dead", last_error = "cancelled", updated_at = CURRENT_TIMESTAMP(6)
                 WHERE job_id = :job_id AND status = "pending"'
            );
            $stmt->execute(['job_id' => $jobId]);

            return $stmt->rowCount() > 0;
        });
    }
}
