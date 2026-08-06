<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Logistics\Policies\LogisticsStatusPolicy;
use Rateb\App\Logistics\Repositories\LogisticsDriverRepository;
use Rateb\App\Models\Employee;

final class DriverService
{
    public function __construct(
        private LogisticsDriverRepository $drivers = new LogisticsDriverRepository(),
        private LogisticsStatusService $status = new LogisticsStatusService(),
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function listForCompany(int $companyId, int $limit = 200, int $offset = 0): array
    {
        if ($companyId < 1) {
            return [];
        }
        $rows = $this->drivers->listForCompany($companyId, $limit, $offset);
        if ($rows === []) {
            return [];
        }
        $names = $this->employeeNames($companyId);
        foreach ($rows as &$row) {
            $eid = (int) ($row['employee_id'] ?? 0);
            $row['employee_name'] = $names[$eid] ?? ('#' . $eid);
        }
        unset($row);

        return $rows;
    }

    public function countForCompany(int $companyId): int
    {
        return $this->drivers->countForCompany($companyId);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $companyId): ?array
    {
        $row = $this->drivers->find($id, $companyId);
        if ($row === null) {
            return null;
        }
        $names = $this->employeeNames($companyId);
        $eid = (int) ($row['employee_id'] ?? 0);
        $row['employee_name'] = $names[$eid] ?? ('#' . $eid);

        return $row;
    }

    /** @param array<string, mixed> $data */
    public function create(int $companyId, array $data): int
    {
        $this->assertCompany($companyId);
        TenantContext::setCompanyId($companyId);
        $payload = $this->normalize($companyId, $data);
        $payload['created_by'] = $this->userId();

        return $this->drivers->create($companyId, $payload);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, int $companyId, array $data): bool
    {
        $existing = $this->require($id, $companyId);
        $payload = $this->normalize($companyId, $data);
        $payload['updated_by'] = $this->userId();

        $newStatus = (string) ($payload['status'] ?? $existing['status'] ?? 'active');
        $oldStatus = (string) ($existing['status'] ?? 'active');
        unset($payload['status']);

        $ok = $this->drivers->update($id, $companyId, $payload);
        if ($ok && $newStatus !== $oldStatus) {
            $this->status->transition(
                $companyId,
                LogisticsStatusPolicy::ENTITY_DRIVER,
                $id,
                $newStatus,
                'driver_update'
            );
        }

        return $ok;
    }

    public function delete(int $id, int $companyId): bool
    {
        $this->require($id, $companyId);

        return $this->drivers->delete($id, $companyId);
    }

    /** @return array<int, array{value:int,label:string}> */
    public function options(int $companyId, bool $activeOnly = true): array
    {
        $out = [];
        foreach ($this->listForCompany($companyId, 500) as $row) {
            if ($activeOnly && (string) ($row['status'] ?? '') !== 'active') {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $label = (string) ($row['employee_name'] ?? '') . ' (' . (string) ($row['license_number'] ?? '-') . ')';
            $out[] = ['value' => $id, 'label' => $label];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function normalize(int $companyId, array $data): array
    {
        $employeeId = (int) ($data['employee_id'] ?? 0);
        if ($employeeId < 1) {
            throw new \RuntimeException(__('logistics_employee_required'));
        }
        TenantContext::setCompanyId($companyId);
        $employee = (new Employee())->find($employeeId);
        if ($employee === null || (int) ($employee['company_id'] ?? 0) !== $companyId) {
            throw new \RuntimeException(__('logistics_employee_invalid'));
        }

        $status = (string) ($data['status'] ?? 'active');
        if (!in_array($status, LogisticsStatusPolicy::statuses(LogisticsStatusPolicy::ENTITY_DRIVER), true)) {
            $status = 'active';
        }

        $expiry = trim((string) ($data['license_expiry'] ?? ''));

        return [
            'company_id' => $companyId,
            'branch_id' => ((int) ($data['branch_id'] ?? 0)) ?: null,
            'employee_id' => $employeeId,
            'license_number' => trim((string) ($data['license_number'] ?? '')) ?: null,
            'license_type' => trim((string) ($data['license_type'] ?? '')) ?: null,
            'license_expiry' => $expiry !== '' ? $expiry : null,
            'status' => $status,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];
    }

    /** @return array<int, string> */
    private function employeeNames(int $companyId): array
    {
        TenantContext::setCompanyId($companyId);
        $map = [];
        foreach ((new Employee())->all(1000, 0) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $map[$id] = (string) ($row['name'] ?? ('#' . $id));
            }
        }

        return $map;
    }

    /** @return array<string, mixed> */
    private function require(int $id, int $companyId): array
    {
        $row = $this->drivers->find($id, $companyId);
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
