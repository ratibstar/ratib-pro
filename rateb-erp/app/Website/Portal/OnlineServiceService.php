<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use Rateb\App\Core\TenantContext;
use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-09 — Online services orchestrator (website presentation → ERP truth).
 *
 * Customer → WebsitePortalController → OnlineServiceService → CRM Lead → Workflow → Finance → Notifications
 */
final class OnlineServiceService
{
    private TenantWebsiteRepository $repo;
    private PortalRequestService $requests;
    private PortalBookingService $booking;
    private PortalTimelineService $timeline;
    private PortalFinanceService $finance;
    private PortalNotificationService $notifications;

    private const SERVICE_TYPES = ['recruitment', 'domestic_worker', 'workforce', 'package', 'other'];

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
        $this->requests = new PortalRequestService($this->repo);
        $this->booking = new PortalBookingService($this->repo);
        $this->timeline = new PortalTimelineService($this->repo);
        $this->finance = new PortalFinanceService($this->repo);
        $this->notifications = new PortalNotificationService($this->repo);
    }

    /** @return array<string, array{label:string,amount:float,currency:string,service_type:string}> */
    public function packages(): array
    {
        return $this->booking->packages();
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, id?: int, portal_request_id?: int, error?: string}
     */
    public function submitRequest(array $portalUser, array $data): array
    {
        $serviceType = (string) ($data['service_type'] ?? 'recruitment');
        if (!in_array($serviceType, self::SERVICE_TYPES, true)) {
            $serviceType = 'other';
        }
        $packageCode = trim((string) ($data['package_code'] ?? ''));
        $amount = null;
        $currency = 'SAR';
        if ($packageCode !== '') {
            $pkg = $this->booking->package($packageCode);
            if ($pkg === null) {
                return ['ok' => false, 'error' => 'invalid_package'];
            }
            $serviceType = (string) $pkg['service_type'];
            $amount = (float) $pkg['amount'];
            $currency = (string) $pkg['currency'];
            if (trim((string) ($data['title'] ?? '')) === '') {
                $data['title'] = (string) $pkg['label'];
            }
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            return ['ok' => false, 'error' => 'title_required'];
        }
        $agreement = !empty($data['agreement_accepted']) || !empty($data['accept_agreement']);
        if (!$agreement) {
            return ['ok' => false, 'error' => 'agreement_required'];
        }

        $portalTypeMap = [
            'recruitment' => 'recruitment',
            'domestic_worker' => 'recruitment',
            'workforce' => 'workforce',
            'package' => 'service',
            'other' => 'service',
        ];
        $portalRequestType = $portalTypeMap[$serviceType] ?? 'service';
        $portalResult = $this->requests->create($portalUser, $portalRequestType, array_merge($data, [
            'title' => $title,
            'description' => (string) ($data['description'] ?? ''),
        ]));
        if (!($portalResult['ok'] ?? false)) {
            return $portalResult;
        }
        $portalRequestId = (int) ($portalResult['id'] ?? 0);
        $portalRow = $this->requests->findForUser($portalRequestId, (int) $portalUser['id']);
        $crmLeadId = $portalRow['crm_lead_id'] ?? null;

        $this->repo->execute(
            'INSERT INTO rateb_website_service_requests
             (company_id, portal_user_id, service_type, package_code, title, description, priority, status,
              agreement_accepted, agreement_accepted_at, amount, currency, payment_status,
              crm_lead_id, portal_request_id, meta_json)
             VALUES (:cid, :uid, :stype, :pkg, :title, :desc, :prio, :st,
                     1, NOW(), :amount, :currency, :pay,
                     :lead, :preq, :meta)',
            [
                'cid' => $this->repo->companyId(),
                'uid' => (int) $portalUser['id'],
                'stype' => $serviceType,
                'pkg' => $packageCode !== '' ? $packageCode : null,
                'title' => $title,
                'desc' => trim((string) ($data['description'] ?? '')) ?: null,
                'prio' => in_array((string) ($data['priority'] ?? ''), ['low', 'normal', 'high', 'urgent'], true)
                    ? (string) $data['priority']
                    : 'normal',
                'st' => 'submitted',
                'amount' => $amount,
                'currency' => $currency,
                'pay' => $amount !== null && $amount > 0 ? 'unpaid' : 'unpaid',
                'lead' => $crmLeadId !== null ? (int) $crmLeadId : null,
                'preq' => $portalRequestId ?: null,
                'meta' => json_encode([
                    'source' => 'website_online_services',
                    'phone' => $data['phone'] ?? null,
                    'preferred_schedule' => $data['preferred_schedule'] ?? null,
                ], JSON_UNESCAPED_UNICODE),
            ]
        );
        $serviceId = (int) $this->repo->lastInsertId();

        $this->timeline->add(
            $serviceId,
            'submitted',
            'Service request submitted',
            $title,
            (int) $portalUser['id'],
            'customer'
        );
        $this->maybeStartWorkflow($serviceId, $crmLeadId);
        $this->notifications->notifyServiceStatus(
            $portalUser,
            $serviceId,
            'submitted',
            'Service request received: ' . $title
        );

        if (class_exists(\Rateb\App\Services\AuditService::class)) {
            (new \Rateb\App\Services\AuditService())->log(
                'website.service_request',
                'website_service_request',
                $serviceId,
                ['service_type' => $serviceType, 'portal_request_id' => $portalRequestId]
            );
        }

        return ['ok' => true, 'id' => $serviceId, 'portal_request_id' => $portalRequestId];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, appointment_id?: int, error?: string}
     */
    public function bookAppointment(array $portalUser, int $serviceRequestId, array $data): array
    {
        return $this->booking->schedule($portalUser, $serviceRequestId, $data);
    }

    /**
     * @return array{ok: bool, payment_token?: string, amount?: float, currency?: string, error?: string}
     */
    public function startPayment(array $portalUser, int $serviceRequestId): array
    {
        $req = $this->findOwned($serviceRequestId, (int) $portalUser['id']);
        if ($req === null) {
            return ['ok' => false, 'error' => 'request_not_found'];
        }
        if ((string) ($req['payment_status'] ?? '') === 'paid') {
            return ['ok' => false, 'error' => 'already_paid'];
        }
        $amount = (float) ($req['amount'] ?? 0);
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'no_amount'];
        }
        $token = $this->finance->createServicePaymentToken($serviceRequestId, $amount, (string) ($req['currency'] ?? 'SAR'));
        $this->repo->execute(
            "UPDATE rateb_website_service_requests SET payment_status = 'pending' WHERE id = :id AND company_id = :cid",
            ['id' => $serviceRequestId, 'cid' => $this->repo->companyId()]
        );
        $this->timeline->add(
            $serviceRequestId,
            'payment_started',
            'Payment initiated',
            number_format($amount, 2) . ' ' . (string) ($req['currency'] ?? 'SAR'),
            (int) $portalUser['id'],
            'customer'
        );

        return [
            'ok' => true,
            'payment_token' => $token,
            'amount' => $amount,
            'currency' => (string) ($req['currency'] ?? 'SAR'),
        ];
    }

    /**
     * Secure payment callback — HMAC token + company isolation (no gateway secret exposure).
     *
     * @return array{ok: bool, error?: string}
     */
    public function completePaymentCallback(int $serviceRequestId, string $token, string $paymentRef = ''): array
    {
        $row = $this->repo->fetchOne(
            'SELECT * FROM rateb_website_service_requests WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $serviceRequestId, 'cid' => $this->repo->companyId()]
        );
        if ($row === null) {
            return ['ok' => false, 'error' => 'request_not_found'];
        }
        $this->repo->assertRowCompany($row, 'service_request');
        if (!$this->finance->verifyServicePaymentToken($serviceRequestId, $token, (float) ($row['amount'] ?? 0), (string) ($row['currency'] ?? 'SAR'))) {
            return ['ok' => false, 'error' => 'invalid_token'];
        }
        if ((string) ($row['payment_status'] ?? '') === 'paid') {
            return ['ok' => true];
        }
        $ref = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', $paymentRef) ?: ('PAY-' . $serviceRequestId), 0, 120);
        $this->repo->execute(
            "UPDATE rateb_website_service_requests
             SET payment_status = 'paid', payment_ref = :ref, status = IF(status IN ('submitted','booked','draft'), 'paid', status)
             WHERE id = :id AND company_id = :cid",
            ['ref' => $ref, 'id' => $serviceRequestId, 'cid' => $this->repo->companyId()]
        );
        $this->timeline->add($serviceRequestId, 'payment_paid', 'Payment confirmed', $ref, null, 'system');
        $this->finance->recordServicePaymentBridge($serviceRequestId, (float) ($row['amount'] ?? 0), (string) ($row['currency'] ?? 'SAR'), $ref);

        $userId = (int) ($row['portal_user_id'] ?? 0);
        if ($userId > 0) {
            $user = $this->repo->fetchOne(
                'SELECT * FROM rateb_website_portal_users WHERE id = :id AND company_id = :cid LIMIT 1',
                ['id' => $userId, 'cid' => $this->repo->companyId()]
            );
            if ($user !== null) {
                $this->notifications->notifyServiceStatus(
                    $user,
                    $serviceRequestId,
                    'paid',
                    'Payment confirmed for service request #' . $serviceRequestId
                );
            }
        }
        if (class_exists(\Rateb\App\Services\AuditService::class)) {
            (new \Rateb\App\Services\AuditService())->log(
                'website.service_payment',
                'website_service_request',
                $serviceRequestId,
                ['payment_ref' => $ref]
            );
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function addCustomerMessage(array $portalUser, int $serviceRequestId, string $message): array
    {
        $message = trim($message);
        if ($message === '') {
            return ['ok' => false, 'error' => 'message_required'];
        }
        $req = $this->findOwned($serviceRequestId, (int) $portalUser['id']);
        if ($req === null) {
            return ['ok' => false, 'error' => 'request_not_found'];
        }
        $this->timeline->add(
            $serviceRequestId,
            'customer_message',
            'Customer message',
            $message,
            (int) $portalUser['id'],
            'customer'
        );
        $this->notifications->notifyCompany(
            'Service message #' . $serviceRequestId,
            substr($message, 0, 400)
        );

        return ['ok' => true];
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $portalUserId, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;
        [$where, $params] = $this->repo->companyWhere();
        $params['uid'] = $portalUserId;

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_website_service_requests
             WHERE {$where} AND portal_user_id = :uid
             ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function track(int $serviceRequestId, int $portalUserId): ?array
    {
        $req = $this->findOwned($serviceRequestId, $portalUserId);
        if ($req === null) {
            return null;
        }
        $req['timeline'] = $this->timeline->forRequest($serviceRequestId, $portalUserId);
        $req['appointments'] = $this->repo->fetchAll(
            'SELECT * FROM rateb_website_service_appointments
             WHERE company_id = :cid AND service_request_id = :sid AND portal_user_id = :uid
             ORDER BY starts_at ASC',
            [
                'cid' => $this->repo->companyId(),
                'sid' => $serviceRequestId,
                'uid' => $portalUserId,
            ]
        );

        return $req;
    }

    /** @return array{ok: bool, error?: string} */
    public function acceptAgreement(array $portalUser, int $serviceRequestId): array
    {
        $req = $this->findOwned($serviceRequestId, (int) $portalUser['id']);
        if ($req === null) {
            return ['ok' => false, 'error' => 'request_not_found'];
        }
        $this->repo->execute(
            'UPDATE rateb_website_service_requests
             SET agreement_accepted = 1, agreement_accepted_at = NOW()
             WHERE id = :id AND company_id = :cid AND portal_user_id = :uid',
            [
                'id' => $serviceRequestId,
                'cid' => $this->repo->companyId(),
                'uid' => (int) $portalUser['id'],
            ]
        );
        $this->timeline->add($serviceRequestId, 'agreement_accepted', 'Digital agreement accepted', null, (int) $portalUser['id'], 'customer');

        return ['ok' => true];
    }

    /** @return array<string, mixed>|null */
    private function findOwned(int $id, int $portalUserId): ?array
    {
        $row = $this->repo->fetchOne(
            'SELECT * FROM rateb_website_service_requests
             WHERE id = :id AND company_id = :cid AND portal_user_id = :uid LIMIT 1',
            ['id' => $id, 'cid' => $this->repo->companyId(), 'uid' => $portalUserId]
        );
        if ($row !== null) {
            $this->repo->assertRowCompany($row, 'service_request');
        }

        return $row;
    }

    private function maybeStartWorkflow(int $serviceId, mixed $crmLeadId): void
    {
        TenantContext::setCompanyId($this->repo->companyId());
        try {
            if (!class_exists(\Rateb\App\Services\WorkflowService::class) || $crmLeadId === null) {
                $this->timeline->add($serviceId, 'workflow_pending', 'Awaiting workflow routing', null, null, 'system');

                return;
            }
            $svc = new \Rateb\App\Services\WorkflowService();
            if (method_exists($svc, 'start')) {
                $svc->start('crm_lead', (int) $crmLeadId);
                $this->timeline->add($serviceId, 'workflow_started', 'Workflow started', 'lead:' . (int) $crmLeadId, null, 'system');
            } elseif (method_exists($svc, 'createForEntity')) {
                $svc->createForEntity('crm_lead', (int) $crmLeadId);
                $this->timeline->add($serviceId, 'workflow_started', 'Workflow started', 'lead:' . (int) $crmLeadId, null, 'system');
            } else {
                $this->timeline->add($serviceId, 'workflow_pending', 'Lead created — workflow via ERP', 'lead:' . (int) $crmLeadId, null, 'system');
            }
        } catch (\Throwable $e) {
            $this->timeline->add($serviceId, 'workflow_pending', 'Workflow deferred', $e->getMessage(), null, 'system');
        }
    }
}
