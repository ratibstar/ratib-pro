<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\JobQueueReadRepositoryInterface;

final class MysqlJobQueueReadRepository extends BaseRepository implements JobQueueReadRepositoryInterface
{
    protected function table(): string
    {
        return 'job_queue';
    }

    public function findByJobId(string $jobId): ?array
    {
        return $this->fetchOne(
            'SELECT job_id, queue, job_type, payload, idempotency_key, status, attempts, max_attempts,
                    available_at, started_at, completed_at, last_error, created_at
             FROM job_queue WHERE job_id = :job_id LIMIT 1',
            ['job_id' => $jobId]
        );
    }

    public function countByQueueAndStatus(): array
    {
        $rows = $this->fetchAll(
            'SELECT queue, status, COUNT(*) AS cnt FROM job_queue GROUP BY queue, status'
        );
        $counts = [];
        foreach ($rows as $row) {
            $key = (string) $row['queue'] . ':' . (string) $row['status'];
            $counts[$key] = (int) $row['cnt'];
        }

        return $counts;
    }
}
