<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use Rateb\App\Core\TenantContext;
use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-07/09 — Finance bridge (invoice/payment reads scoped to portal customer).
 */
final class PortalFinanceService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /** @return array{invoices: list<array<string,mixed>>, payments: list<array<string,mixed>>, outstanding: float, portal_user_id: int} */
    public function snapshot(?array $portalUser = null): array
    {
        $cid = $this->repo->companyId();
        TenantContext::setCompanyId($cid);

        if ($portalUser === null || (int) ($portalUser['id'] ?? 0) < 1) {
            return ['invoices' => [], 'payments' => [], 'outstanding' => 0.0, 'portal_user_id' => 0];
        }

        $customer = $this->resolveCustomer($portalUser);
        $invoices = [];
        $payments = [];

        if ($customer !== null) {
            try {
                $invoices = $this->repo->fetchAll(
                    'SELECT id, invoice_no, total_amount, currency, status, payment_status, due_date, issued_at, document_path,
                            buyer_legal_name, buyer_vat_number
                     FROM rateb_invoices
                     WHERE company_id = :cid
                       AND (
                            (buyer_vat_number IS NOT NULL AND buyer_vat_number <> \'\' AND :tax <> \'\' AND buyer_vat_number = :tax)
                         OR (buyer_legal_name IS NOT NULL AND LOWER(TRIM(buyer_legal_name)) = LOWER(TRIM(:cname)))
                       )
                     ORDER BY id DESC LIMIT 50',
                    [
                        'cid' => $cid,
                        'tax' => (string) ($customer['tax_id'] ?? ''),
                        'cname' => (string) ($customer['name'] ?? ''),
                    ]
                );
                $invoiceIds = array_values(array_filter(array_map(
                    static fn ($r) => (int) ($r['id'] ?? 0),
                    $invoices
                )));
                if ($invoiceIds !== []) {
                    $placeholders = [];
                    $params = ['cid' => $cid];
                    foreach ($invoiceIds as $i => $id) {
                        $key = 'iid' . $i;
                        $placeholders[] = ':' . $key;
                        $params[$key] = $id;
                    }
                    $payments = $this->repo->fetchAll(
                        'SELECT id, invoice_id, amount, currency, status, payment_method, paid_at, created_at
                         FROM rateb_payments
                         WHERE company_id = :cid AND invoice_id IN (' . implode(',', $placeholders) . ')
                         ORDER BY id DESC LIMIT 50',
                        $params
                    );
                }
            } catch (\Throwable $e) {
                error_log('PortalFinanceService: ' . $e->getMessage());
            }
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
            'portal_user_id' => (int) ($portalUser['id'] ?? 0),
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
    public function findInvoice(int $invoiceId, ?array $portalUser = null): ?array
    {
        if ($invoiceId < 1 || $portalUser === null || (int) ($portalUser['id'] ?? 0) < 1) {
            return null;
        }
        $snap = $this->snapshot($portalUser);
        foreach ($snap['invoices'] as $inv) {
            if ((int) ($inv['id'] ?? 0) === $invoiceId) {
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

        return null;
    }

    public function createServicePaymentToken(int $serviceRequestId, float $amount, string $currency): string
    {
        $payload = $this->repo->companyId() . '|' . $serviceRequestId . '|' . number_format($amount, 2, '.', '') . '|' . strtoupper($currency) . '|pending';
        $sig = hash_hmac('sha256', $payload, $this->paymentSecret());

        return rtrim(strtr(base64_encode($serviceRequestId . '.' . $sig), '+/', '-_'), '=');
    }

    public function verifyServicePaymentToken(int $serviceRequestId, string $token, float $amount, string $currency, string $intent = 'pending'): bool
    {
        try {
            $secret = $this->paymentSecret();
        } catch (\Throwable $e) {
            return false;
        }
        $raw = base64_decode(strtr($token, '-_', '+/'), true);
        if ($raw === false || !str_contains($raw, '.')) {
            return false;
        }
        [$idPart, $sig] = explode('.', $raw, 2);
        if ((int) $idPart !== $serviceRequestId || $sig === '') {
            return false;
        }
        $payload = $this->repo->companyId() . '|' . $serviceRequestId . '|' . number_format($amount, 2, '.', '') . '|' . strtoupper($currency) . '|' . $intent;
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $sig);
    }

    public function createServicePaidProofToken(int $serviceRequestId, float $amount, string $currency, string $paymentRef): string
    {
        $payload = $this->repo->companyId() . '|' . $serviceRequestId . '|' . number_format($amount, 2, '.', '') . '|' . strtoupper($currency) . '|paid|' . $paymentRef;
        $sig = hash_hmac('sha256', $payload, $this->paymentSecret());

        return rtrim(strtr(base64_encode($serviceRequestId . '.' . $sig), '+/', '-_'), '=');
    }

    public function verifyServicePaidProofToken(int $serviceRequestId, string $token, float $amount, string $currency, string $paymentRef): bool
    {
        try {
            $secret = $this->paymentSecret();
        } catch (\Throwable $e) {
            return false;
        }
        $raw = base64_decode(strtr($token, '-_', '+/'), true);
        if ($raw === false || !str_contains($raw, '.')) {
            return false;
        }
        [$idPart, $sig] = explode('.', $raw, 2);
        if ((int) $idPart !== $serviceRequestId || $sig === '') {
            return false;
        }
        $payload = $this->repo->companyId() . '|' . $serviceRequestId . '|' . number_format($amount, 2, '.', '') . '|' . strtoupper($currency) . '|paid|' . $paymentRef;
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $sig);
    }

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

    /** @param array<string, mixed> $portalUser @return array<string, mixed>|null */
    public function resolveCustomer(array $portalUser): ?array
    {
        $cid = $this->repo->companyId();
        $erpId = (int) ($portalUser['erp_customer_id'] ?? 0);
        if ($erpId > 0) {
            $row = $this->repo->fetchOne(
                'SELECT id, company_id, name, email, phone, tax_id FROM rateb_customers
                 WHERE id = :id AND company_id = :cid LIMIT 1',
                ['id' => $erpId, 'cid' => $cid]
            );
            if ($row !== null) {
                $this->repo->assertRowCompany($row, 'customer');
                return $row;
            }
        }

        $email = strtolower(trim((string) ($portalUser['email'] ?? '')));
        if ($email === '') {
            return null;
        }
        $row = $this->repo->fetchOne(
            'SELECT id, company_id, name, email, phone, tax_id FROM rateb_customers
             WHERE company_id = :cid AND LOWER(TRIM(email)) = :email LIMIT 1',
            ['cid' => $cid, 'email' => $email]
        );
        if ($row === null) {
            return null;
        }
        $this->repo->assertRowCompany($row, 'customer');
        $uid = (int) ($portalUser['id'] ?? 0);
        if ($uid > 0) {
            try {
                $this->repo->execute(
                    'UPDATE rateb_website_portal_users SET erp_customer_id = :eid
                     WHERE id = :id AND company_id = :cid AND (erp_customer_id IS NULL OR erp_customer_id = 0)',
                    ['eid' => (int) $row['id'], 'id' => $uid, 'cid' => $cid]
                );
            } catch (\Throwable $e) {
            }
        }

        return $row;
    }

    /** @throws \RuntimeException */
    private function paymentSecret(): string
    {
        if (defined('RATEB_APP_KEY') && is_string(RATEB_APP_KEY) && RATEB_APP_KEY !== '') {
            return RATEB_APP_KEY;
        }
        $env = (string) (getenv('RATEB_APP_KEY') ?: getenv('APP_KEY') ?: '');
        if ($env !== '') {
            return $env;
        }
        throw new \RuntimeException('payment_secret_missing');
    }
}
