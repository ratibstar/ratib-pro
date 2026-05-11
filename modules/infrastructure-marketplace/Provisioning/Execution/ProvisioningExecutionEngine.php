<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Provisioning\Execution;

use Ratib\InfrastructureMarketplace\Audit\InfrastructureAuditLogger;
use Ratib\InfrastructureMarketplace\Compliance\TenantIsolationCompliance;
use Ratib\InfrastructureMarketplace\Config\ModuleConfig;
use Ratib\InfrastructureMarketplace\Domain\TenantContext;
use Ratib\InfrastructureMarketplace\Events\InfrastructureEventEmitter;
use Ratib\InfrastructureMarketplace\Observability\InfrastructureMetrics;
use Ratib\InfrastructureMarketplace\Provisioning\Lifecycle\ProvisioningState;
use Ratib\InfrastructureMarketplace\Provisioning\Lifecycle\StateTransitionValidator;
use Ratib\InfrastructureMarketplace\Provisioning\Persistence\ProvisioningJobLogRepository;
use Ratib\InfrastructureMarketplace\Provisioning\Persistence\ProvisioningJobRepository;
use Ratib\InfrastructureMarketplace\Provisioning\StateMachine\ProvisioningStateMachine;
use Ratib\InfrastructureMarketplace\DNS\Orchestration\DnsOrchestrationService;
use Ratib\InfrastructureMarketplace\Registrars\Lifecycle\DomainLifecycleManager;
use Ratib\InfrastructureMarketplace\SSL\Lifecycle\CertificateLifecycleManager;
use Ratib\InfrastructureMarketplace\SSL\Validation\DnsValidationPreparation;
use Ratib\InfrastructureMarketplace\SSL\Validation\HttpValidationPreparation;
use Ratib\InfrastructureMarketplace\Services\ProviderRegistry;

final class ProvisioningExecutionEngine
{
    private ProvisioningStateMachine $stateMachine;
    private ProvisioningJobRepository $jobs;
    private ProvisioningJobLogRepository $logs;
    private InfrastructureEventEmitter $events;
    private InfrastructureMetrics $metrics;
    private InfrastructureAuditLogger $audit;
    private TenantIsolationCompliance $compliance;
    private ProviderRegistry $providers;


    public function __construct(ProvisioningJobRepository $jobs, ProvisioningJobLogRepository $logs, InfrastructureEventEmitter $events, InfrastructureMetrics $metrics, InfrastructureAuditLogger $audit, TenantIsolationCompliance $compliance, ProviderRegistry $providers) {
        $this->jobs = $jobs;
        $this->logs = $logs;
        $this->events = $events;
        $this->metrics = $metrics;
        $this->audit = $audit;
        $this->compliance = $compliance;
        $this->providers = $providers;

        $this->stateMachine = new ProvisioningStateMachine(
            $this->jobs,
            new StateTransitionValidator(),
            $this->audit
        );
    }


    /**
     * @param array<string, mixed> $row
     */
    public function process(array $row, string $workerName): void
    {
        $jobId = (int) ($row['id'] ?? 0);
        if ($jobId <= 0) {
            throw new \RuntimeException('Invalid job row payload.');
        }

        $publicId = (string) ($row['public_id'] ?? '');
        $from = strtoupper((string) ($row['status'] ?? ProvisioningState::QUEUED));
        $startedAt = microtime(true);
        $currentState = $from;

        $this->stateMachine->transition($jobId, $from, ProvisioningState::RUNNING, $workerName);
        $currentState = ProvisioningState::RUNNING;
        $this->logs->append($jobId, 'info', 'Execution started', ['worker' => $workerName]);

        $tenant = new TenantContext(
            isset($row['tenant_id']) ? (int) $row['tenant_id'] : null,
            isset($row['agency_id']) ? (int) $row['agency_id'] : null
        );
        $this->compliance->assertTenantOperation($tenant, 'job_execute');
        $this->compliance->logAccess($tenant, $workerName, 'job_execute');

        try {
            $steps = $this->decodeSteps((string) ($row['steps_json'] ?? '[]'));
            $payloads = $this->decodePayloads((string) ($row['payload_snapshot_json'] ?? '{}'));
            foreach ($steps as $step) {
                if (in_array(strtolower($step), ['dns', 'registrar', 'ssl'], true)) {
                    $this->stateMachine->transition($jobId, $currentState, ProvisioningState::WAITING_EXTERNAL, $workerName, ['step' => $step]);
                    $currentState = ProvisioningState::WAITING_EXTERNAL;
                    $this->stateMachine->transition($jobId, $currentState, ProvisioningState::RUNNING, $workerName, ['step' => $step, 'external_ready' => true]);
                    $currentState = ProvisioningState::RUNNING;
                }
                $this->executeStep($step, $tenant, $payloads[$step] ?? []);
            }

            $this->stateMachine->transition($jobId, $currentState, ProvisioningState::COMPLETED, $workerName);
            $this->logs->append($jobId, 'info', 'Execution completed');
            $this->metrics->markLatencyMs('job_execution', (microtime(true) - $startedAt) * 1000, $publicId);
        } catch (\Throwable $e) {
            $this->logs->append($jobId, 'error', 'Execution failed', ['reason' => substr($e->getMessage(), 0, 250)]);
            $this->metrics->incrementFailureCounter('job_execution', 'runtime');
            $this->events->structuredLog('error', 'Provisioning execution failure', ['job_id' => $jobId]);
            throw $e;
        }
    }

    /**
     * @return list<string>
     */
    private function decodeSteps(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_filter(array_map(static fn($v): string => (string) $v, $decoded)));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function decodePayloads(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function executeStep(string $step, TenantContext $tenant, array $payload): void
    {
        if (ModuleConfig::dryRunMode() || ModuleConfig::executionKillSwitch()) {
            return;
        }
        $step = strtolower($step);
        if ($step === 'hosting' && $this->providers->hosting() !== null) {
            $username = (string) ($payload['attributes']['username'] ?? '');
            if ($username !== '') {
                $this->providers->hosting()?->usageMetrics($tenant, $username);
            }
            return;
        }
        if ($step === 'dns' && $this->providers->dns() !== null) {
            $dns = new DnsOrchestrationService($this->providers->dns());
            $zone = (string) ($payload['attributes']['zone_fqdn'] ?? '');
            if ($zone !== '') {
                $dns->verifyZone($zone);
                $dns->propagationCheck($zone);
            }
            return;
        }
        if ($step === 'ssl') {
            $cert = new CertificateLifecycleManager(
                new DnsValidationPreparation(),
                new HttpValidationPreparation()
            );
            $fqdn = (string) ($payload['attributes']['fqdn'] ?? '');
            if ($fqdn !== '') {
                $cert->prepareValidation($fqdn, 'token-placeholder');
            }
            return;
        }
        if ($step === 'registrar') {
            $domains = new DomainLifecycleManager();
            $fqdn = (string) ($payload['attributes']['fqdn'] ?? '');
            if ($fqdn !== '') {
                $domains->registrationPlan($fqdn, 1);
            }
            return;
        }
    }
}

