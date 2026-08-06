<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services\DriverApi;

use Rateb\App\Core\TenantContext;
use Rateb\App\Logistics\Contracts\ApiEmployeeResolver;
use Rateb\App\Logistics\Contracts\DriverPermissionChecker;
use Rateb\App\Logistics\Repositories\LogisticsDriverRepository;
use Rateb\App\Logistics\Services\Integration\ErpApiEmployeeResolver;
use Rateb\App\Logistics\Services\Integration\ErpDriverPermissionChecker;

/**
 * Resolves API bearer → employee → logistics driver with tenant + permission checks.
 *
 * @phpstan-type DriverContext array{
 *   company_id:int,user_id:int,employee_id:int,driver_id:int,driver:array<string,mixed>,employee:array<string,mixed>
 * }
 */
final class LogisticsDriverContextService
{
    public function __construct(
        private LogisticsDriverRepository $drivers = new LogisticsDriverRepository(),
        private ApiEmployeeResolver $employees = new ErpApiEmployeeResolver(),
        private DriverPermissionChecker $permissions = new ErpDriverPermissionChecker(),
    ) {
    }

    /**
     * @return array{ok:true,context:DriverContext}|array{ok:false,status:int,body:array<string,mixed>}
     */
    public function resolve(?int $userId = null, ?int $companyId = null): array
    {
        $userId = (int) ($userId ?? TenantContext::apiUserId() ?? 0);
        $companyId = (int) ($companyId ?? TenantContext::companyId() ?? 0);
        if ($userId < 1 || $companyId < 1) {
            return $this->fail(401, 'unauthorized', 'Unauthorized');
        }
        if (!$this->permissions->canDrive($userId, $companyId)) {
            return $this->fail(403, 'driver_forbidden', 'Driver permission required');
        }

        $resolved = $this->employees->resolveCurrentEmployee($userId, $companyId);
        if ((int) ($resolved['status'] ?? 500) !== 200) {
            return [
                'ok' => false,
                'status' => (int) ($resolved['status'] ?? 403),
                'body' => is_array($resolved['body'] ?? null) ? $resolved['body'] : ['success' => false, 'message' => 'Employee unresolved'],
            ];
        }
        $employee = is_array($resolved['body']['employee'] ?? null) ? $resolved['body']['employee'] : null;
        if ($employee === null) {
            return $this->fail(404, 'employee_unbound', 'No employee linked to this user');
        }
        if ((int) ($employee['company_id'] ?? 0) !== $companyId) {
            return $this->fail(403, 'tenant_mismatch', 'Employee does not belong to this company');
        }

        $employeeId = (int) ($employee['id'] ?? 0);
        $driver = $this->findDriverForEmployee($companyId, $employeeId);
        if ($driver === null) {
            return $this->fail(404, 'driver_profile_missing', 'No logistics driver profile for this employee');
        }
        if ((string) ($driver['status'] ?? '') === 'inactive' || (string) ($driver['status'] ?? '') === 'suspended') {
            return $this->fail(403, 'driver_inactive', 'Driver profile is not active');
        }

        TenantContext::setCompanyId($companyId);

        return [
            'ok' => true,
            'context' => [
                'company_id' => $companyId,
                'user_id' => $userId,
                'employee_id' => $employeeId,
                'driver_id' => (int) $driver['id'],
                'driver' => $driver,
                'employee' => $employee,
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function findDriverForEmployee(int $companyId, int $employeeId): ?array
    {
        foreach ($this->drivers->listForCompany($companyId, 500, 0) as $row) {
            if ((int) ($row['employee_id'] ?? 0) === $employeeId) {
                return $row;
            }
        }

        return null;
    }

    /** @return array{ok:false,status:int,body:array<string,mixed>} */
    private function fail(int $status, string $code, string $message): array
    {
        return [
            'ok' => false,
            'status' => $status,
            'body' => [
                'success' => false,
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
