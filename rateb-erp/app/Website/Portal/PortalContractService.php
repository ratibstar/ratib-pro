<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use Rateb\App\Core\TenantContext;
use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-08 — Portal-scoped contracts (documents first; never company-wide).
 */
final class PortalContractService
{
    private TenantWebsiteRepository $repo;

    /** @var list<string>|null */
    private static ?array $contractCustomerCols = null;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /**
     * @param array<string, mixed>|null $portalUser
     * @return list<array<string, mixed>>
     */
    public function listActive(int $limit = 50, int $page = 1, ?array $portalUser = null): array
    {
        if ($portalUser === null || (int) ($portalUser['id'] ?? 0) < 1) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $page = max(1, $page);
        $offset = ($page - 1) * $limit;
        $uid = (int) $portalUser['id'];
        $cid = $this->repo->companyId();
        TenantContext::setCompanyId($cid);

        $rows = [];
        try {
            $docs = $this->repo->fetchAll(
                "SELECT id, title, file_path AS document_path, status, created_at, doc_category
                 FROM rateb_website_portal_documents
                 WHERE company_id = :cid AND portal_user_id = :uid AND status = 'active'
                   AND (
                        doc_category IN ('contract', 'agreement')
                     OR LOWER(title) LIKE '%contract%'
                   )
                 ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",
                ['cid' => $cid, 'uid' => $uid]
            );
            foreach ($docs as $doc) {
                $rows[] = [
                    'id' => (int) ($doc['id'] ?? 0),
                    'contract_no' => 'DOC-' . (int) ($doc['id'] ?? 0),
                    'title' => (string) ($doc['title'] ?? ''),
                    'contract_type' => (string) ($doc['doc_category'] ?? 'contract'),
                    'start_date' => null,
                    'end_date' => null,
                    'value' => null,
                    'status' => (string) ($doc['status'] ?? 'active'),
                    'approval_status' => null,
                    'document_path' => $doc['document_path'] ?? null,
                    'source' => 'portal_document',
                ];
            }
        } catch (\Throwable $e) {
            error_log('PortalContractService documents: ' . $e->getMessage());
        }

        $safeCol = $this->safeCustomerColumn();
        if ($safeCol !== null) {
            try {
                $customer = (new PortalFinanceService($this->repo))->resolveCustomer($portalUser);
                if ($customer !== null) {
                    $col = $safeCol;
                    $erpRows = $this->repo->fetchAll(
                        "SELECT id, contract_no, title, contract_type, start_date, end_date, value, status, approval_status, document_path
                         FROM rateb_contracts
                         WHERE company_id = :cid AND `{$col}` = :cust
                         ORDER BY id DESC LIMIT {$limit}",
                        ['cid' => $cid, 'cust' => (int) $customer['id']]
                    );
                    foreach ($erpRows as $row) {
                        $this->repo->assertRowCompany($row, 'contract');
                        $row['source'] = 'rateb_contracts';
                        $rows[] = $row;
                    }
                }
            } catch (\Throwable $e) {
                error_log('PortalContractService contracts: ' . $e->getMessage());
            }
        }

        return array_slice($rows, 0, $limit);
    }

    /**
     * @param array<string, mixed>|null $portalUser
     * @return array<string, mixed>|null
     */
    public function find(int $id, ?array $portalUser = null): ?array
    {
        if ($id < 1 || $portalUser === null || (int) ($portalUser['id'] ?? 0) < 1) {
            return null;
        }
        $uid = (int) $portalUser['id'];
        $cid = $this->repo->companyId();
        TenantContext::setCompanyId($cid);

        $doc = $this->repo->fetchOne(
            "SELECT * FROM rateb_website_portal_documents
             WHERE id = :id AND company_id = :cid AND portal_user_id = :uid
               AND (
                    doc_category IN ('contract', 'agreement')
                 OR LOWER(title) LIKE '%contract%'
               )
             LIMIT 1",
            ['id' => $id, 'cid' => $cid, 'uid' => $uid]
        );
        if ($doc !== null) {
            $this->repo->assertRowCompany($doc, 'portal_document');

            return [
                'id' => (int) ($doc['id'] ?? 0),
                'contract_no' => 'DOC-' . (int) ($doc['id'] ?? 0),
                'title' => (string) ($doc['title'] ?? ''),
                'contract_type' => (string) ($doc['doc_category'] ?? 'contract'),
                'document_path' => $doc['file_path'] ?? null,
                'status' => (string) ($doc['status'] ?? 'active'),
                'source' => 'portal_document',
            ];
        }

        $safeCol = $this->safeCustomerColumn();
        if ($safeCol === null) {
            return null;
        }
        try {
            $customer = (new PortalFinanceService($this->repo))->resolveCustomer($portalUser);
            if ($customer === null) {
                return null;
            }
            $col = $safeCol;
            $row = $this->repo->fetchOne(
                "SELECT * FROM rateb_contracts WHERE id = :id AND company_id = :cid AND `{$col}` = :cust LIMIT 1",
                ['id' => $id, 'cid' => $cid, 'cust' => (int) $customer['id']]
            );
            if ($row !== null) {
                $this->repo->assertRowCompany($row, 'contract');
            }

            return $row;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @param array<string, mixed>|null $portalUser */
    public function activeCount(?array $portalUser = null): int
    {
        if ($portalUser === null || (int) ($portalUser['id'] ?? 0) < 1) {
            return 0;
        }

        return count($this->listActive(100, 1, $portalUser));
    }

    /** @return string|null Safe FK column on rateb_contracts linking to customers */
    private function safeCustomerColumn(): ?string
    {
        if (self::$contractCustomerCols !== null) {
            return self::$contractCustomerCols[0] ?? null;
        }
        self::$contractCustomerCols = [];
        $candidates = ['customer_id', 'client_id', 'party_id', 'buyer_customer_id'];
        try {
            $cols = $this->repo->fetchAll('SHOW COLUMNS FROM rateb_contracts');
            $names = [];
            foreach ($cols as $c) {
                $names[strtolower((string) ($c['Field'] ?? ''))] = true;
            }
            foreach ($candidates as $cand) {
                if (isset($names[$cand])) {
                    self::$contractCustomerCols[] = $cand;
                    break;
                }
            }
        } catch (\Throwable $e) {
            self::$contractCustomerCols = [];
        }

        return self::$contractCustomerCols[0] ?? null;
    }
}
