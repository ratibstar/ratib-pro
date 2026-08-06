<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Logistics\Policies\LogisticsStatusPolicy;
use Rateb\App\Logistics\Repositories\LogisticsTripRepository;

final class TripService
{
    public function __construct(
        private LogisticsTripRepository $trips = new LogisticsTripRepository(),
        private FleetService $fleet = new FleetService(),
        private DriverService $drivers = new DriverService(),
        private RouteService $routes = new RouteService(),
        private LogisticsStatusService $status = new LogisticsStatusService(),
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function listForCompany(int $companyId, int $limit = 200, int $offset = 0): array
    {
        return $this->trips->listForCompany($companyId, $limit, $offset);
    }

    public function countForCompany(int $companyId): int
    {
        return $this->trips->countForCompany($companyId);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $companyId): ?array
    {
        return $this->trips->find($id, $companyId);
    }

    /** @param array<string, mixed> $data */
    public function create(int $companyId, array $data): int
    {
        $this->assertCompany($companyId);
        TenantContext::setCompanyId($companyId);
        $payload = $this->normalize($companyId, $data, true);
        $payload['created_by'] = $this->userId();

        return $this->trips->create($companyId, $payload);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, int $companyId, array $data): bool
    {
        $existing = $this->require($id, $companyId);
        if (in_array((string) ($existing['status'] ?? ''), ['completed', 'cancelled'], true)) {
            throw new \RuntimeException(__('logistics_trip_locked'));
        }
        $payload = $this->normalize($companyId, $data, false);
        $payload['updated_by'] = $this->userId();
        unset($payload['status']);

        return $this->trips->update($id, $companyId, $payload);
    }

    public function assign(int $id, int $companyId, ?string $reason = null): array
    {
        $trip = $this->require($id, $companyId);
        if ((int) ($trip['driver_id'] ?? 0) < 1 || (int) ($trip['vehicle_id'] ?? 0) < 1) {
            throw new \RuntimeException(__('logistics_trip_assign_requires_assets'));
        }

        return $this->status->transition(
            $companyId,
            LogisticsStatusPolicy::ENTITY_TRIP,
            $id,
            'assigned',
            $reason ?? 'trip_assign'
        );
    }

    public function start(int $id, int $companyId, ?string $reason = null): array
    {
        $this->require($id, $companyId);

        return $this->status->transition(
            $companyId,
            LogisticsStatusPolicy::ENTITY_TRIP,
            $id,
            'started',
            $reason ?? 'trip_start',
            ['start_time' => date('Y-m-d H:i:s')]
        );
    }

    public function complete(int $id, int $companyId, ?string $reason = null): array
    {
        $this->require($id, $companyId);

        return $this->status->transition(
            $companyId,
            LogisticsStatusPolicy::ENTITY_TRIP,
            $id,
            'completed',
            $reason ?? 'trip_complete',
            ['end_time' => date('Y-m-d H:i:s')]
        );
    }

    public function cancel(int $id, int $companyId, ?string $reason = null): array
    {
        $this->require($id, $companyId);

        return $this->status->transition(
            $companyId,
            LogisticsStatusPolicy::ENTITY_TRIP,
            $id,
            'cancelled',
            $reason ?? 'trip_cancel'
        );
    }

    public function transition(int $id, int $companyId, string $toStatus, ?string $reason = null): array
    {
        return match ($toStatus) {
            'assigned' => $this->assign($id, $companyId, $reason),
            'started' => $this->start($id, $companyId, $reason),
            'completed' => $this->complete($id, $companyId, $reason),
            'cancelled' => $this->cancel($id, $companyId, $reason),
            default => $this->status->transition(
                $companyId,
                LogisticsStatusPolicy::ENTITY_TRIP,
                $id,
                $toStatus,
                $reason
            ),
        };
    }

    public function delete(int $id, int $companyId): bool
    {
        $row = $this->require($id, $companyId);
        if ((string) ($row['status'] ?? '') !== 'draft') {
            throw new \RuntimeException(__('logistics_delete_blocked'));
        }

        return $this->trips->delete($id, $companyId);
    }

    /** @return array<int, array{value:int,label:string}> */
    public function options(int $companyId): array
    {
        $out = [];
        foreach ($this->listForCompany($companyId, 500) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $label = '#' . $id . ' ' . (string) ($row['origin'] ?? '') . ' → ' . (string) ($row['destination'] ?? '');
            $out[] = ['value' => $id, 'label' => trim($label) . ' [' . (string) ($row['status'] ?? '') . ']'];
        }

        return $out;
    }

    /** @return list<string> */
    public function nextStatuses(int $id, int $companyId): array
    {
        $row = $this->require($id, $companyId);
        $from = (string) ($row['status'] ?? 'draft');

        return LogisticsStatusPolicy::allowedTransitions(LogisticsStatusPolicy::ENTITY_TRIP)[$from] ?? [];
    }

    /** @return array<string, mixed> */
    private function normalize(int $companyId, array $data, bool $isCreate): array
    {
        $driverId = (int) ($data['driver_id'] ?? 0);
        $vehicleId = (int) ($data['vehicle_id'] ?? 0);
        $routeId = (int) ($data['route_id'] ?? 0);

        if ($driverId > 0 && $this->drivers->find($driverId, $companyId) === null) {
            throw new \RuntimeException(__('logistics_driver_invalid'));
        }
        if ($vehicleId > 0 && $this->fleet->find($vehicleId, $companyId) === null) {
            throw new \RuntimeException(__('logistics_vehicle_invalid'));
        }
        if ($routeId > 0 && $this->routes->find($routeId, $companyId) === null) {
            throw new \RuntimeException(__('logistics_route_invalid'));
        }

        $status = $isCreate ? 'draft' : (string) ($data['status'] ?? 'draft');

        return [
            'company_id' => $companyId,
            'branch_id' => ((int) ($data['branch_id'] ?? 0)) ?: null,
            'driver_id' => $driverId > 0 ? $driverId : null,
            'vehicle_id' => $vehicleId > 0 ? $vehicleId : null,
            'route_id' => $routeId > 0 ? $routeId : null,
            'origin' => trim((string) ($data['origin'] ?? '')) ?: null,
            'destination' => trim((string) ($data['destination'] ?? '')) ?: null,
            'planned_date' => trim((string) ($data['planned_date'] ?? '')) ?: null,
            'start_time' => trim((string) ($data['start_time'] ?? '')) ?: null,
            'end_time' => trim((string) ($data['end_time'] ?? '')) ?: null,
            'status' => $status,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];
    }

    /** @return array<string, mixed> */
    private function require(int $id, int $companyId): array
    {
        $row = $this->find($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException(__('no_records'));
        }

        return $row;
    }

    private function assertCompany(int $companyId): void
    {
        if ($companyId < 1) {
            throw new \RuntimeException(__('select_company_ops'));
        }
    }

    private function userId(): ?int
    {
        $id = (int) (SessionManager::get('rateb_user_id') ?? 0);

        return $id > 0 ? $id : null;
    }
}
