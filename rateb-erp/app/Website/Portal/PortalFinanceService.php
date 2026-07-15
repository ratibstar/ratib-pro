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

    /**
     * Phase WEBSITE-09 — HMAC payment token for online service requests (bridge only).
     */
    public function createServicePaymentToken(int $serviceRequestId, float $amount, string $currency): string
    {
        $payload = $this->repo->companyId() . '|' . $serviceRequestId . '|' . number_format($amount, 2, '.', '') . '|' . strtoupper($currency);
        $sig = hash_hmac('sha256', $payload, $this->paymentSecret());

        return rtrim(strtr(base64_encode($serviceRequestId . '.' . $sig), '+/', '-_'), '=');
    }

    public function verifyServicePaymentToken(int $serviceRequestId, string $token, float $amount, string $currency): bool
    {
        $raw = base64_decode(strtr($token, '-_', '+/'), true);
        if ($raw === false || !str_contains($raw, '.')) {
            return false;
        }
        [$idPart, $sig] = explode('.', $raw, 2);
        if ((int) $idPart !== $serviceRequestId || $sig === '') {
            return false;
        }
        $payload = $this->repo->companyId() . '|' . $serviceRequestId . '|' . number_format($amount, 2, '.', '') . '|' . strtoupper($currency);
        $expected = hash_hmac('sha256', $payload, $this->paymentSecret());

        return hash_equals($expected, $sig);
    }

    /**
     * Record payment intent against ERP PaymentService when available (never invents master tables).
     */
    public function recordServicePaymentBridge(int $serviceRequestId, float $amount, string $currency, string $ref): void
    {
        TenantContext::setCompanyId($this->repo->companyId());
        try {
            if (class_exists(\Rateb\App\Services\PaymentService::class)) {
                $svc = new \Rateb\App\Services\PaymentService();
                if (method_exists($svc, 'recordExternal')) {
                    $svc->recordExternal([
                        'amount' => $amount,
                        'currency' => $currency,
                        'reference' => $ref,
                        'source' => 'website_service_request',
                        'source_id' => $serviceRequestId,
                        'company_id' => $this->repo->companyId(),
                    ]);
                } elseif (method_exists($svc, 'create')) {
                    $svc->create([
                        'amount' => $amount,
                        'currency' => $currency,
                        'status' => 'paid',
                        'payment_method' => 'online',
                        'notes' => 'website_service_request:' . $serviceRequestId . ' ref:' . $ref,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            error_log('PortalFinanceService service payment: ' . $e->getMessage());
        }
    }

    private function paymentSecret(): string
    {
        if (defined('RATEB_APP_KEY') && is_string(RATEB_APP_KEY) && RATEB_APP_KEY !== '') {
            return RATEB_APP_KEY;
        }
        $env = (string) (getenv('RATEB_APP_KEY') ?: getenv('APP_KEY') ?: '');
        if ($env !== '') {
            return $env;
        }

        return 'rateb-website-services-' . $this->repo->companyId();
    }
}
