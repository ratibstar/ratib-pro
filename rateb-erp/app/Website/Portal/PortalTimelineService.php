<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-09 — Service request timeline (bridge events only).
 */
final class PortalTimelineService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    public function add(
        int $serviceRequestId,
        string $eventCode,
        string $title,
        ?string $body = null,
        ?int $portalUserId = null,
        string $actor = 'system'
    ): int {
        $this->repo->execute(
            'INSERT INTO rateb_website_service_timeline
             (company_id, service_request_id, portal_user_id, event_code, title, body, actor)
             VALUES (:cid, :sid, :uid, :code, :title, :body, :actor)',
            [
                'cid' => $this->repo->companyId(),
                'sid' => $serviceRequestId,
                'uid' => $portalUserId,
                'code' => substr(preg_replace('/[^a-z0-9_\-]/i', '', $eventCode) ?: 'event', 0, 80),
                'title' => $title,
                'body' => $body,
                'actor' => $actor,
            ]
        );

        return (int) $this->repo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function forRequest(int $serviceRequestId, int $portalUserId): array
    {
        $req = $this->repo->fetchOne(
            'SELECT id, company_id, portal_user_id FROM rateb_website_service_requests
             WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $serviceRequestId, 'cid' => $this->repo->companyId()]
        );
        if ($req === null) {
            return [];
        }
        $this->repo->assertRowCompany($req, 'service_request');
        if ($portalUserId > 0 && (int) ($req['portal_user_id'] ?? 0) !== $portalUserId) {
            return [];
        }
        [$where, $params] = $this->repo->companyWhere();
        $params['sid'] = $serviceRequestId;

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_website_service_timeline WHERE {$where} AND service_request_id = :sid ORDER BY id ASC",
            $params
        );
    }
}
