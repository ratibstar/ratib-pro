<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use Rateb\App\Core\TenantContext;
use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-08 — Read-only ERP contracts (Contract model / rateb_contracts).
 */
final class PortalContractService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /** @return list<array<string, mixed>> */
    public function listActive(int $limit = 50, int $page = 1): array
    {
        $limit = max(1, min(100, $limit));
        $page = max(1, $page);
        $offset = ($page - 1) * $limit;
        TenantContext::setCompanyId($this->repo->companyId());

        try {
            if (class_exists(\Rateb\App\Models\Contract::class)) {
                $model = new \Rateb\App\Models\Contract();

                return $model->query(
                    "SELECT id, contract_no, title, contract_type, start_date, end_date, value, status, approval_status, document_path
                     FROM rateb_contracts
                     WHERE company_id = :cid AND deleted_at IS NULL
                     ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",
                    ['cid' => $this->repo->companyId()]
                );
            }
        } catch (\Throwable $e) {
            // Some schemas omit deleted_at — fallback without it.
            try {
                return $this->repo->fetchAll(
                    "SELECT id, contract_no, title, contract_type, start_date, end_date, value, status, approval_status, document_path
                     FROM rateb_contracts
                     WHERE company_id = :cid
                     ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",
                    ['cid' => $this->repo->companyId()]
                );
            } catch (\Throwable $e2) {
                error_log('PortalContractService: ' . $e2->getMessage());
            }
        }

        return [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        TenantContext::setCompanyId($this->repo->companyId());
        $row = $this->repo->fetchOne(
            'SELECT * FROM rateb_contracts WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $id, 'cid' => $this->repo->companyId()]
        );
        if ($row !== null) {
            $this->repo->assertRowCompany($row, 'contract');
        }

        return $row;
    }

    public function activeCount(): int
    {
        try {
            $row = $this->repo->fetchOne(
                "SELECT COUNT(*) AS c FROM rateb_contracts
                 WHERE company_id = :cid AND status IN ('active','approved')",
                ['cid' => $this->repo->companyId()]
            );

            return (int) ($row['c'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
