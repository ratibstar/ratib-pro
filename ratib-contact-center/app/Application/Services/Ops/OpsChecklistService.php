<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Ops;

use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Ops\OpsChecklistRepository;

final class OpsChecklistService
{
    public function __construct(
        private readonly OpsChecklistRepository $checklist = new OpsChecklistRepository(),
        private readonly OpsDiagnosticService $diagnostics = new OpsDiagnosticService(),
        private readonly OpsProvisioningService $provisioning = new OpsProvisioningService(),
        private readonly OpsAuditService $audit = new OpsAuditService()
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $tenantId): array
    {
        return $this->checklist->statusForTenant($tenantId);
    }

    /** @return array<string, mixed> */
    public function summary(int $tenantId): array
    {
        return $this->checklist->summary($tenantId);
    }

    public function update(
        int $tenantId,
        string $stepSlug,
        string $status,
        ?int $userId = null,
        ?string $notes = null
    ): void {
        if (!in_array($status, ['pending', 'pass', 'fail', 'skipped'], true)) {
            throw new \InvalidArgumentException('Invalid status');
        }
        $this->checklist->updateStatus($tenantId, $stepSlug, $status, $userId, null, $notes);
        $this->audit->log($tenantId, 'ops.checklist.update', $userId, 'checklist', null, ['step' => $stepSlug, 'status' => $status]);
        EventBus::instance()->emit([
            'type' => EventType::OPS_CHECKLIST_UPDATED,
            'tenant_id' => $tenantId,
            'payload' => ['step_slug' => $stepSlug, 'status' => $status],
        ]);
    }

    /** @return array<string, mixed> */
    public function autoVerify(int $tenantId, ?int $userId = null): array
    {
        $steps = $this->checklist->steps();
        $results = [];
        foreach ($steps as $step) {
            $slug = (string) $step['slug'];
            $action = (string) ($step['verify_action'] ?? '');
            if ($action === '') {
                continue;
            }
            $pass = $this->runVerifyAction($tenantId, $action);
            $status = $pass ? 'pass' : 'fail';
            $this->checklist->updateStatus($tenantId, $slug, $status, $userId, ['auto' => true, 'action' => $action]);
            $results[$slug] = $status;
        }
        EventBus::instance()->emit([
            'type' => EventType::OPS_CHECKLIST_AUTO_VERIFY,
            'tenant_id' => $tenantId,
            'payload' => ['results' => $results],
        ]);
        return ['results' => $results, 'summary' => $this->summary($tenantId)];
    }

    private function runVerifyAction(int $tenantId, string $action): bool
    {
        return match ($action) {
            'health_center' => ($this->diagnostics->healthCenter($tenantId)['percent'] ?? 0) >= 70,
            'diag_ami' => (bool) ($this->diagnostics->diagAmi($tenantId)['ok'] ?? false),
            'sip_list' => count($this->provisioning->listSipExtensions($tenantId)) > 0,
            'queue_list' => $this->provisioning->hasQueueWithMembers($tenantId),
            'ivr_flows_list' => count($this->provisioning->listIvrFlows($tenantId)) > 0,
            'agent_list' => count($this->provisioning->listAgents($tenantId)) > 0,
            'hub_status' => (bool) ($this->diagnostics->diagHub()['running'] ?? false),
            'diag_voice_worker' => (bool) ($this->diagnostics->diagVoiceWorker()['ok'] ?? false),
            'diag_webrtc' => (bool) ($this->diagnostics->diagWebrtc($tenantId, 0)['ok'] ?? false),
            'checklist_summary' => $this->summary($tenantId)['ready'] ?? false,
            default => false,
        };
    }
}
