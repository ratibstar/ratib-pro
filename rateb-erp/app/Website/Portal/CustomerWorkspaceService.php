<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-08 — Customer workspace aggregator (reuses portal + ERP domain services).
 */
final class CustomerWorkspaceService
{
    private TenantWebsiteRepository $repo;
    private PortalDashboardService $dashboard;
    private PortalContractService $contracts;
    private PortalRequestService $requests;
    private PortalRecruitmentService $recruitment;
    private PortalFinanceService $finance;
    private PortalDocumentService $documents;
    private PortalWorkflowService $workflow;
    private PortalNotificationService $notifications;
    private PortalSupportService $support;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
        $this->dashboard = new PortalDashboardService($this->repo);
        $this->contracts = new PortalContractService($this->repo);
        $this->requests = new PortalRequestService($this->repo);
        $this->recruitment = new PortalRecruitmentService($this->repo);
        $this->finance = new PortalFinanceService($this->repo);
        $this->documents = new PortalDocumentService($this->repo);
        $this->workflow = new PortalWorkflowService($this->repo);
        $this->notifications = new PortalNotificationService($this->repo);
        $this->support = new PortalSupportService($this->repo);
    }

    /** @param array<string, mixed> $user @return array<string, mixed> */
    public function workspace(array $user): array
    {
        $base = $this->dashboard->customer($user);
        $finance = $this->finance->snapshot($user);
        $pipeline = $this->recruitment->pipelineSummary();
        $contracts = $this->contracts->listActive(8);
        $approvals = $this->workflow->pendingForCompany();
        $notifications = $this->notifications->listInApp();

        $kpis = [
            'active_contracts' => $this->contracts->activeCount(),
            'open_requests' => count(array_filter(
                $base['requests'] ?? [],
                static fn ($r) => in_array((string) ($r['status'] ?? ''), ['submitted', 'in_progress', 'draft'], true)
            )),
            'outstanding_balance' => (float) ($finance['outstanding'] ?? 0),
            'pending_approvals' => count($approvals),
            'unread_notifications' => count(array_filter(
                $notifications,
                static fn ($n) => empty($n['is_read'])
            )),
            'open_tickets' => count($base['tickets'] ?? []),
            'pipeline_total' => (int) ($pipeline['total'] ?? 0),
            'documents' => count($base['documents'] ?? []),
        ];

        return array_merge($base, [
            'kpis' => $kpis,
            'contracts' => $contracts,
            'pipeline' => $pipeline,
            'orders' => $this->requests->listForUser((int) $user['id'], 'service'),
            'invoices' => $finance['invoices'],
            'payments' => $finance['payments'],
            'outstanding' => $finance['outstanding'],
            'approvals' => $approvals,
            'notifications' => $notifications,
        ]);
    }
}
