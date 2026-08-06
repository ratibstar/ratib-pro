<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Repositories;

use Rateb\App\Core\Model;
use Rateb\App\Core\TenantContext;

abstract class AbstractLogisticsRepository
{
    abstract protected function newModel(): Model;

    protected function bindTenant(int $companyId): void
    {
        if ($companyId > 0) {
            TenantContext::setCompanyId($companyId);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function listForCompany(int $companyId, int $limit = 200, int $offset = 0, string $search = ''): array
    {
        if ($companyId < 1) {
            return [];
        }
        $this->bindTenant($companyId);
        return $this->newModel()->all($limit, $offset, [], $search);
    }

    public function countForCompany(int $companyId, string $search = ''): int
    {
        if ($companyId < 1) {
            return 0;
        }
        $this->bindTenant($companyId);
        return $this->newModel()->count([], $search);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $this->bindTenant($companyId);
        $row = $this->newModel()->find($id);
        if ($row === null) {
            return null;
        }
        if ((int) ($row['company_id'] ?? 0) !== $companyId) {
            return null;
        }

        return $row;
    }

    /** @param array<string, mixed> $data */
    public function create(int $companyId, array $data): int
    {
        $this->bindTenant($companyId);
        $data['company_id'] = $companyId;

        return $this->newModel()->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, int $companyId, array $data): bool
    {
        if ($this->find($id, $companyId) === null) {
            return false;
        }
        $this->bindTenant($companyId);
        $data['company_id'] = $companyId;

        return $this->newModel()->update($id, $data);
    }

    public function delete(int $id, int $companyId): bool
    {
        if ($this->find($id, $companyId) === null) {
            return false;
        }
        $this->bindTenant($companyId);

        return $this->newModel()->delete($id);
    }

    /** @return array<int, array<string, mixed>> */
    public function listByStatus(int $companyId, string $status, int $limit = 200): array
    {
        $rows = $this->listForCompany($companyId, $limit, 0);
        if ($status === '') {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn(array $row): bool => (string) ($row['status'] ?? '') === $status
        ));
    }
}
