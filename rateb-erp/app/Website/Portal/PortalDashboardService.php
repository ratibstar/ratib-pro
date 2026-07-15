<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-07 — Aggregated dashboards for three portals (read via ERP bridges).
 */
final class PortalDashboardService
{
    private TenantWebsiteRepository $repo;
    private PortalRequestService $requests;
    private PortalFinanceService $finance;
    private PortalDocumentService $documents;
    private PortalSupportService $support;
    private PortalAppointmentService $appointments;
    private PortalRecruitmentService $recruitment;
    private PortalNotificationService $notifications;
    private PortalWorkflowService $workflow;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
        $this->requests = new PortalRequestService($this->repo);
        $this->finance = new PortalFinanceService($this->repo);
        $this->documents = new PortalDocumentService($this->repo);
        $this->support = new PortalSupportService($this->repo);
        $this->appointments = new PortalAppointmentService($this->repo);
        $this->recruitment = new PortalRecruitmentService($this->repo);
        $this->notifications = new PortalNotificationService($this->repo);
        $this->workflow = new PortalWorkflowService($this->repo);
    }

    /** @param array<string, mixed> $user @return array<string, mixed> */
    public function employer(array $user): array
    {
        $uid = (int) $user['id'];
        $finance = $this->finance->snapshot($user);

        return [
            'portal_type' => PortalAuthService::TYPE_EMPLOYER,
            'user' => $user,
            'requests' => $this->requests->listForUser($uid),
            'shortlists' => $this->recruitment->shortlistsForUser($uid),
            'documents' => $this->documents->listForUser($uid),
            'appointments' => $this->appointments->listForUser($uid),
            'tickets' => $this->support->ticketsForUser($uid),
            'invoices' => $finance['invoices'],
            'payments' => $finance['payments'],
            'outstanding' => $finance['outstanding'],
            'notifications' => $this->notifications->listInApp(),
            'approvals' => $this->workflow->pendingForCompany(),
        ];
    }

    /** @param array<string, mixed> $user @return array<string, mixed> */
    public function customer(array $user): array
    {
        $uid = (int) $user['id'];
        $finance = $this->finance->snapshot($user);

        return [
            'portal_type' => PortalAuthService::TYPE_CUSTOMER,
            'user' => $user,
            'requests' => $this->requests->listForUser($uid),
            'documents' => $this->documents->listForUser($uid),
            'tickets' => $this->support->ticketsForUser($uid),
            'invoices' => $finance['invoices'],
            'payments' => $finance['payments'],
            'outstanding' => $finance['outstanding'],
            'notifications' => $this->notifications->listInApp(),
            'approvals' => $this->workflow->pendingForCompany(),
            'appointments' => $this->appointments->listForUser($uid),
        ];
    }

    /** @param array<string, mixed> $user @return array<string, mixed> */
    public function partner(array $user): array
    {
        $uid = (int) $user['id'];
        $finance = $this->finance->snapshot($user);

        return [
            'portal_type' => PortalAuthService::TYPE_PARTNER,
            'user' => $user,
            'opportunities' => $this->requests->listForUser($uid, 'referral'),
            'requests' => $this->requests->listForUser($uid),
            'documents' => $this->documents->listForUser($uid),
            'payments' => $finance['payments'],
            'invoices' => $finance['invoices'],
            'notifications' => $this->notifications->listInApp(),
        ];
    }
}
