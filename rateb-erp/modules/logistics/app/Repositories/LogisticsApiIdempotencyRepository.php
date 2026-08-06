<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Repositories;

use Rateb\App\Core\Model;
use Rateb\App\Core\TenantContext;
use Rateb\App\Logistics\Models\LogisticsApiIdempotency;

class LogisticsApiIdempotencyRepository extends AbstractLogisticsRepository
{
    protected function newModel(): Model
    {
        return new LogisticsApiIdempotency();
    }

    /** @return array<string, mixed>|null */
    public function findByKey(int $companyId, int $driverId, string $key): ?array
    {
        if ($companyId < 1 || $driverId < 1 || $key === '') {
            return null;
        }
        TenantContext::setCompanyId($companyId);
        foreach ($this->listForCompany($companyId, 500, 0) as $row) {
            if ((int) ($row['driver_id'] ?? 0) === $driverId
                && (string) ($row['idempotency_key'] ?? '') === $key) {
                return $row;
            }
        }

        return null;
    }
}
