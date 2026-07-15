<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-08 — Portal company contacts (profile workspace).
 */
final class PortalContactService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $portalUserId): array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['uid'] = $portalUserId;

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_website_portal_contacts WHERE {$where} AND portal_user_id = :uid ORDER BY is_primary DESC, id ASC",
            $params
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, id?: int, error?: string}
     */
    public function add(array $portalUser, array $data): array
    {
        $name = trim((string) ($data['contact_name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'error' => 'contact_name_required'];
        }
        $this->repo->execute(
            'INSERT INTO rateb_website_portal_contacts
             (company_id, portal_user_id, contact_name, email, phone, role_title, is_primary)
             VALUES (:cid, :uid, :name, :email, :phone, :role, :pri)',
            [
                'cid' => $this->repo->companyId(),
                'uid' => (int) $portalUser['id'],
                'name' => $name,
                'email' => trim((string) ($data['email'] ?? '')) ?: null,
                'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
                'role' => trim((string) ($data['role_title'] ?? '')) ?: null,
                'pri' => !empty($data['is_primary']) ? 1 : 0,
            ]
        );

        return ['ok' => true, 'id' => (int) $this->repo->lastInsertId()];
    }
}
