<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-07 — Appointments / interview booking (bridge table).
 */
final class PortalAppointmentService
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
    public function book(array $portalUser, array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $starts = trim((string) ($data['starts_at'] ?? ''));
        if ($title === '' || $starts === '') {
            return ['ok' => false, 'error' => 'title_starts_required'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2})?/', $starts)) {
            return ['ok' => false, 'error' => 'invalid_datetime'];
        }
        $type = (string) ($data['appointment_type'] ?? 'meeting');
        if (!in_array($type, ['meeting', 'interview', 'service', 'other'], true)) {
            $type = 'meeting';
        }
        $this->repo->execute(
            'INSERT INTO rateb_website_portal_appointments
             (company_id, portal_user_id, appointment_type, title, starts_at, ends_at, location,
              recruitment_candidate_id, status, notes)
             VALUES (:cid, :uid, :atype, :title, :starts, :ends, :loc, :cand, :st, :notes)',
            [
                'cid' => $this->repo->companyId(),
                'uid' => (int) $portalUser['id'],
                'atype' => $type,
                'title' => $title,
                'starts' => str_replace('T', ' ', $starts),
                'ends' => trim((string) ($data['ends_at'] ?? '')) ?: null,
                'loc' => trim((string) ($data['location'] ?? '')) ?: null,
                'cand' => (int) ($data['recruitment_candidate_id'] ?? 0) ?: null,
                'st' => 'scheduled',
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            ]
        );

        return ['ok' => true, 'id' => (int) $this->repo->lastInsertId()];
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $portalUserId): array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['uid'] = $portalUserId;

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_website_portal_appointments
             WHERE {$where} AND portal_user_id = :uid
             ORDER BY starts_at DESC LIMIT 100",
            $params
        );
    }
}
