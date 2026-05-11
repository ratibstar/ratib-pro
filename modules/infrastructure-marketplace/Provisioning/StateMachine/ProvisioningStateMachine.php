<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Provisioning\StateMachine;

use Ratib\InfrastructureMarketplace\Audit\InfrastructureAuditLogger;
use Ratib\InfrastructureMarketplace\Provisioning\Lifecycle\StateTransitionValidator;
use Ratib\InfrastructureMarketplace\Provisioning\Persistence\ProvisioningJobRepository;

final class ProvisioningStateMachine
{
    private ProvisioningJobRepository $jobs;
    private StateTransitionValidator $validator;
    private InfrastructureAuditLogger $audit;

    public function __construct(ProvisioningJobRepository $jobs, StateTransitionValidator $validator, InfrastructureAuditLogger $audit) {
        $this->jobs = $jobs;
        $this->validator = $validator;
        $this->audit = $audit;
    }


    /**
     * @param array<string, mixed> $meta
     */
    public function transition(int $jobId, string $fromState, string $toState, string $actor, array $meta = []): void
    {
        $this->validator->assertValid($fromState, $toState);
        $ok = $this->jobs->transitionState($jobId, $fromState, $toState);
        if (!$ok) {
            throw new \RuntimeException('State transition rejected due to concurrent update.');
        }
        $this->audit->appendImmutable('provisioning_state_transition', [
            'job_id' => $jobId,
            'from' => strtoupper($fromState),
            'to' => strtoupper($toState),
            'actor' => $actor,
            'meta' => $meta,
        ]);
    }
}

