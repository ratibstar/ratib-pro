<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Provisioning\Queue;

use Ratib\InfrastructureMarketplace\Config\ModuleConfig;
use Ratib\InfrastructureMarketplace\Domain\Contracts\QueueDispatcherInterface;
use Ratib\InfrastructureMarketplace\Provisioning\Persistence\ProvisioningJobLogRepository;
use Ratib\InfrastructureMarketplace\Provisioning\Persistence\ProvisioningJobRepository;
use Ratib\InfrastructureMarketplace\Provisioning\ProvisioningJob;

final class DatabaseQueueDispatcher implements QueueDispatcherInterface
{
    private ProvisioningJobRepository $jobs;
    private ProvisioningJobLogRepository $logs;

    public function __construct(ProvisioningJobRepository $jobs, ProvisioningJobLogRepository $logs) {
        $this->jobs = $jobs;
        $this->logs = $logs;
    }


    public function enqueue(ProvisioningJob $job): string
    {
        $publicId = $this->newPublicId();
        $this->jobs->insertQueued($job, $publicId, ModuleConfig::queueMaxAttempts());
        return $publicId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lockNext(): ?array
    {
        return $this->jobs->lockNextAvailable();
    }

    public function complete(int $jobId): void
    {
        $this->jobs->markSuccess($jobId);
        $this->logs->append($jobId, 'info', 'Provisioning job completed');
    }

    public function fail(int $jobId, int $attempts, int $maxAttempts, string $error): void
    {
        $deadState = ModuleConfig::queueDeadLetterState();
        $this->jobs->markRetryOrDead($jobId, $attempts, $maxAttempts, $error, $deadState);
        $this->logs->append($jobId, 'error', 'Provisioning job failed', [
            'attempts' => $attempts,
            'max_attempts' => $maxAttempts,
            'dead_state' => $deadState,
            'error' => $error,
        ]);
    }

    private function newPublicId(): string
    {
        $hex = bin2hex(random_bytes(16));
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}

