<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-09 — Online booking appointments linked to service requests.
 */
final class PortalBookingService
{
    private TenantWebsiteRepository $repo;
    private PortalAppointmentService $appointments;
    private PortalTimelineService $timeline;

    /** @var array<string, array{label:string,amount:float,currency:string,service_type:string}> */
    private const PACKAGES = [
        'recruitment_basic' => [
            'label' => 'Recruitment Basic',
            'amount' => 1500.0,
            'currency' => 'SAR',
            'service_type' => 'recruitment',
        ],
        'domestic_standard' => [
            'label' => 'Domestic Worker Standard',
            'amount' => 2500.0,
            'currency' => 'SAR',
            'service_type' => 'domestic_worker',
        ],
        'workforce_team' => [
            'label' => 'Company Workforce Package',
            'amount' => 5000.0,
            'currency' => 'SAR',
            'service_type' => 'workforce',
        ],
    ];

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
        $this->appointments = new PortalAppointmentService($this->repo);
        $this->timeline = new PortalTimelineService($this->repo);
    }

    /** @return array<string, array{label:string,amount:float,currency:string,service_type:string}> */
    public function packages(): array
    {
        return self::PACKAGES;
    }

    /** @return array<string, mixed>|null */
    public function package(string $code): ?array
    {
        return self::PACKAGES[$code] ?? null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, appointment_id?: int, error?: string}
     */
    public function schedule(array $portalUser, int $serviceRequestId, array $data): array
    {
        $req = $this->findOwnedRequest($serviceRequestId, (int) $portalUser['id']);
        if ($req === null) {
            return ['ok' => false, 'error' => 'request_not_found'];
        }
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = 'Service appointment #' . $serviceRequestId;
        }
        $appt = $this->appointments->book($portalUser, array_merge($data, [
            'title' => $title,
            'appointment_type' => (string) ($data['appointment_type'] ?? 'meeting'),
        ]));
        if (!($appt['ok'] ?? false)) {
            return $appt;
        }
        $portalApptId = (int) ($appt['id'] ?? 0);
        $this->repo->execute(
            'INSERT INTO rateb_website_service_appointments
             (company_id, portal_user_id, service_request_id, portal_appointment_id, title, starts_at, ends_at, location, status, notes)
             VALUES (:cid, :uid, :sid, :paid, :title, :starts, :ends, :loc, :st, :notes)',
            [
                'cid' => $this->repo->companyId(),
                'uid' => (int) $portalUser['id'],
                'sid' => $serviceRequestId,
                'paid' => $portalApptId ?: null,
                'title' => $title,
                'starts' => str_replace('T', ' ', (string) ($data['starts_at'] ?? '')),
                'ends' => trim((string) ($data['ends_at'] ?? '')) ?: null,
                'loc' => trim((string) ($data['location'] ?? '')) ?: null,
                'st' => 'scheduled',
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            ]
        );
        $svcApptId = (int) $this->repo->lastInsertId();
        $this->repo->execute(
            "UPDATE rateb_website_service_requests SET status = IF(status = 'submitted' OR status = 'draft', 'booked', status)
             WHERE id = :id AND company_id = :cid",
            ['id' => $serviceRequestId, 'cid' => $this->repo->companyId()]
        );
        $this->timeline->add(
            $serviceRequestId,
            'appointment_scheduled',
            'Appointment scheduled',
            $title . ' @ ' . (string) ($data['starts_at'] ?? ''),
            (int) $portalUser['id'],
            'customer'
        );

        return ['ok' => true, 'appointment_id' => $svcApptId];
    }

    /** @return list<array<string, mixed>> */
    public function appointmentsForUser(int $portalUserId): array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['uid'] = $portalUserId;

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_website_service_appointments
             WHERE {$where} AND portal_user_id = :uid
             ORDER BY starts_at DESC LIMIT 100",
            $params
        );
    }

    /** @return array<string, mixed>|null */
    private function findOwnedRequest(int $id, int $portalUserId): ?array
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
}
