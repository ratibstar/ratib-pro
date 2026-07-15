<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use Rateb\App\Core\TenantContext;
use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-07 — Portal service requests (bridge → CRM LeadService when available).
 */
final class PortalRequestService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, id?: int, error?: string}
     */
    public function create(array $portalUser, string $requestType, array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            return ['ok' => false, 'error' => 'title_required'];
        }
        $allowed = [
            'recruitment', 'workforce', 'visa', 'contract', 'service',
            'replacement', 'support', 'referral', 'other',
        ];
        if (!in_array($requestType, $allowed, true)) {
            $requestType = 'other';
        }
        $priority = (string) ($data['priority'] ?? 'normal');
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            $priority = 'normal';
        }

        $crmLeadId = $this->routeToCrm($portalUser, $requestType, $title, $data);

        $this->repo->execute(
            'INSERT INTO rateb_website_portal_requests
             (company_id, portal_user_id, portal_type, request_type, title, description, priority, status, crm_lead_id, meta_json)
             VALUES (:cid, :uid, :ptype, :rtype, :title, :desc, :prio, :st, :lead, :meta)',
            [
                'cid' => $this->repo->companyId(),
                'uid' => (int) $portalUser['id'],
                'ptype' => (string) $portalUser['portal_type'],
                'rtype' => $requestType,
                'title' => $title,
                'desc' => trim((string) ($data['description'] ?? '')) ?: null,
                'prio' => $priority,
                'st' => 'submitted',
                'lead' => $crmLeadId,
                'meta' => json_encode([
                    'contact_phone' => $data['phone'] ?? null,
                    'source' => 'website_portal',
                ], JSON_UNESCAPED_UNICODE),
            ]
        );

        return ['ok' => true, 'id' => (int) $this->repo->lastInsertId()];
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $portalUserId, ?string $requestType = null): array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['uid'] = $portalUserId;
        $sql = "SELECT * FROM rateb_website_portal_requests WHERE {$where} AND portal_user_id = :uid";
        if ($requestType !== null && $requestType !== '') {
            $sql .= ' AND request_type = :rtype';
            $params['rtype'] = $requestType;
        }
        $sql .= ' ORDER BY id DESC LIMIT 100';

        return $this->repo->fetchAll($sql, $params);
    }

    /** @return array<string, mixed>|null */
    public function findForUser(int $id, int $portalUserId): ?array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['id'] = $id;
        $params['uid'] = $portalUserId;

        return $this->repo->fetchOne(
            "SELECT * FROM rateb_website_portal_requests WHERE {$where} AND id = :id AND portal_user_id = :uid LIMIT 1",
            $params
        );
    }

    /**
     * @param array<string, mixed> $portalUser
     * @param array<string, mixed> $data
     */
    private function routeToCrm(array $portalUser, string $requestType, string $title, array $data): ?int
    {
        try {
            if (!class_exists(\Rateb\App\Services\LeadService::class)) {
                return null;
            }
            TenantContext::setCompanyId($this->repo->companyId());
            $result = (new \Rateb\App\Services\LeadService())->create([
                'title' => '[' . strtoupper($requestType) . '] ' . $title,
                'contact_name' => (string) ($portalUser['full_name'] ?? ''),
                'email' => (string) ($portalUser['email'] ?? ''),
                'phone' => (string) ($data['phone'] ?? $portalUser['phone'] ?? ''),
                'notes' => 'Source: website_' . (string) ($portalUser['portal_type'] ?? 'portal') . "\n"
                    . trim((string) ($data['description'] ?? '')),
            ]);

            return isset($result['id']) ? (int) $result['id'] : null;
        } catch (\Throwable $e) {
            error_log('PortalRequestService CRM: ' . $e->getMessage());

            return null;
        }
    }
}
