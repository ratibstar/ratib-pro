<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use Rateb\App\Core\TenantContext;
use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-07 — Finance read-only bridge (reuses rateb_invoices / rateb_payments).
 */
final class PortalFinanceService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /** @return array{invoices: list<array<string,mixed>>, payments: list<array<string,mixed>>, outstanding: float} */
    public function snapshot(?array $portalUser = null): array
    {
        $cid = $this->repo->companyId();
        TenantContext::setCompanyId($cid);

        $invoices = [];
        $payments = [];
        try {
            $invoices = $this->repo->fetchAll(
                'SELECT id, invoice_no, total_amount, currency, status, payment_status, due_date, issued_at, document_path
                 FROM rateb_invoices WHERE company_id = :cid ORDER BY id DESC LIMIT 50',
                ['cid' => $cid]
            );
            $payments = $this->repo->fetchAll(
                'SELECT id, invoice_id, amount, currency, status, payment_method, paid_at, created_at
                 FROM rateb_payments WHERE company_id = :cid ORDER BY id DESC LIMIT 50',
                ['cid' => $cid]
            );
        } catch (\Throwable $e) {
            error_log('PortalFinanceService: ' . $e->getMessage());
        }

        $outstanding = 0.0;
        foreach ($invoices as $inv) {
            $ps = (string) ($inv['payment_status'] ?? '');
            if ($ps !== 'paid' && $ps !== 'cancelled') {
                $outstanding += (float) ($inv['total_amount'] ?? 0);
            }
        }

        return [
            'invoices' => $invoices,
            'payments' => $payments,
            'outstanding' => $outstanding,
            'portal_user_id' => $portalUser !== null ? (int) ($portalUser['id'] ?? 0) : 0,
        ];
    }

    /** @return array{balance: float, invoices: list<array<string,mixed>>, payments: list<array<string,mixed>>} */
    public function statement(?array $portalUser = null): array
    {
        $snap = $this->snapshot($portalUser);

        return [
            'balance' => (float) $snap['outstanding'],
            'invoices' => $snap['invoices'],
            'payments' => $snap['payments'],
        ];
    }

    /** @return array<string, mixed>|null */
    public function findInvoice(int $invoiceId): ?array
    {
        if ($invoiceId < 1) {
            return null;
        }
        $row = $this->repo->fetchOne(
            'SELECT * FROM rateb_invoices WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $invoiceId, 'cid' => $this->repo->companyId()]
        );
        if ($row !== null) {
            $this->repo->assertRowCompany($row, 'invoice');
        }

        return $row;
    }
}
