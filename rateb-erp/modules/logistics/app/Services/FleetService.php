<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Logistics\Policies\LogisticsStatusPolicy;
use Rateb\App\Logistics\Repositories\LogisticsDriverRepository;
use Rateb\App\Logistics\Repositories\LogisticsVehicleRepository;

final class FleetService
{
    public function __construct(
        private LogisticsVehicleRepository $vehicles = new LogisticsVehicleRepository(),
        private LogisticsDriverRepository $drivers = new LogisticsDriverRepository(),
        private LogisticsStatusService $status = new LogisticsStatusService(),
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function listForCompany(int $companyId, int $limit = 200, int $offset = 0): array
    {
        return $this->vehicles->listForCompany($companyId, $limit, $offset);
    }

    public function countForCompany(int $companyId): int
    {
        return $this->vehicles->countForCompany($companyId);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $companyId): ?array
    {
        return $this->vehicles->find($id, $companyId);
    }

    /** @param array<string, mixed> $data */
    public function create(int $companyId, array $data): int
    {
        $this->assertCompany($companyId);
        TenantContext::setCompanyId($companyId);
        $payload = $this->normalize($companyId, $data, true);
        $payload['created_by'] = $this->userId();

        return $this->vehicles->create($companyId, $payload);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, int $companyId, array $data): bool
    {
        $existing = $this->require($id, $companyId);
        $payload = $this->normalize($companyId, $data, false);
        $payload['updated_by'] = $this->userId();

        $newStatus = (string) ($payload['status'] ?? $existing['status'] ?? 'available');
        $oldStatus = (string) ($existing['status'] ?? 'available');
        unset($payload['status']);

        $ok = $this->vehicles->update($id, $companyId, $payload);
        if ($ok && $newStatus !== $oldStatus) {
            $this->status->transition(
                $companyId,
                LogisticsStatusPolicy::ENTITY_VEHICLE,
                $id,
                $newStatus,
                'fleet_update'
            );
        }

        return $ok;
    }

    public function delete(int $id, int $companyId): bool
    {
        $this->require($id, $companyId);

        return $this->vehicles->delete($id, $companyId);
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
            $label = trim((string) ($row['plate_number'] ?? '')) . ' — ' . trim((string) ($row['brand'] ?? '')) . ' ' . trim((string) ($row['model'] ?? ''));
            $out[] = ['value' => $id, 'label' => trim($label) !== '—' ? trim($label) : ('#' . $id)];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function normalize(int $companyId, array $data, bool $isCreate): array
    {
        $plate = strtoupper(trim((string) ($data['plate_number'] ?? '')));
        if ($plate === '') {
            throw new \RuntimeException(__('logistics_plate_required'));
        }

        $status = (string) ($data['status'] ?? 'available');
        if (!in_array($status, LogisticsStatusPolicy::statuses(LogisticsStatusPolicy::ENTITY_VEHICLE), true)) {
            $status = 'available';
        }

        $driverId = (int) ($data['current_driver_id'] ?? 0);
        if ($driverId > 0 && $this->drivers->find($driverId, $companyId) === null) {
            throw new \RuntimeException(__('logistics_driver_invalid'));
        }

        $year = isset($data['year']) && $data['year'] !== '' ? (int) $data['year'] : null;
        $capacity = isset($data['capacity']) && $data['capacity'] !== '' ? (float) $data['capacity'] : null;

        return [
            'company_id' => $companyId,
            'branch_id' => ((int) ($data['branch_id'] ?? 0)) ?: null,
            'plate_number' => $plate,
            'vehicle_type' => trim((string) ($data['vehicle_type'] ?? '')) ?: null,
            'brand' => trim((string) ($data['brand'] ?? '')) ?: null,
            'model' => trim((string) ($data['model'] ?? '')) ?: null,
            'year' => $year,
            'capacity' => $capacity,
            'status' => $isCreate ? $status : $status,
            'current_driver_id' => $driverId > 0 ? $driverId : null,
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
