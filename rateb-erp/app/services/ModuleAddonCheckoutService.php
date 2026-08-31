<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Helpers\LineItems;
use Rateb\App\Models\Invoice;
use Rateb\App\Payment\PaymentService;
use Throwable;

/**
 * Phase 2 — self-serve module add-on checkout.
 * Does not activate modules or write company.modules.
 */
final class ModuleAddonCheckoutService
{
    public const TAX_RATE = 15.0;
    public const CURRENCY = 'SAR';

    /** @var callable|null */
    private $paymentInitiator;

    /**
     * @param callable(int,int):array{ok:bool,redirect_url?:string,transaction_id?:int,error?:string}|null $paymentInitiator
     */
    public function __construct(
        private readonly ModuleAddonService $addons = new ModuleAddonService(),
        ?callable $paymentInitiator = null,
    ) {
        $this->paymentInitiator = $paymentInitiator;
    }

    public function addons(): ModuleAddonService
    {
        return $this->addons;
    }

    public function isEnabled(): bool
    {
        return $this->addons->isEnabled();
    }

    /**
     * Posted cycle only. Invalid values are rejected (not silently coerced).
     *
     * @param array<string, mixed> $posted
     */
    public function requestedCycle(array $posted): ?string
    {
        if (!array_key_exists('cycle', $posted) || trim((string) $posted['cycle']) === '') {
            return 'monthly';
        }
        $cycle = strtolower(trim((string) $posted['cycle']));
        if (!in_array($cycle, ['monthly', 'yearly'], true)) {
            return null;
        }

        return $cycle;
    }

    /**
     * Server-side quote. Never reads HTTP price/amount/tax.
     *
     * @return array{slug:string,name:string,cycle:string,unit_price:float,tax_rate:float,amount:float,tax_amount:float,total_amount:float,currency:string}|null
     */
    public function quote(string $slug, string $cycle): ?array
    {
        $slug = strtolower(trim($slug));
        $cycle = strtolower(trim($cycle));
        if ($slug === '' || !in_array($cycle, ['monthly', 'yearly'], true)) {
            return null;
        }
        if (!$this->addons->isPurchasable($slug)) {
            return null;
        }
        $item = $this->addons->catalog()[$slug] ?? null;
        if ($item === null) {
            return null;
        }
        $price = round((float) ($item[$cycle] ?? 0), 2);
        if ($price <= 0) {
            return null;
        }
        $totals = LineItems::lineTotals(1.0, $price, self::TAX_RATE, true);
        if ($totals['total'] <= 0) {
            return null;
        }

        return [
            'slug' => $slug,
            'name' => (string) ($item['name'] ?? $slug),
            'cycle' => $cycle,
            'unit_price' => $price,
            'tax_rate' => self::TAX_RATE,
            'amount' => $totals['subtotal'],
            'tax_amount' => $totals['tax'],
            'total_amount' => $totals['total'],
            'currency' => self::CURRENCY,
        ];
    }

    /**
     * @return list<string>
     */
    public function availableCycles(string $slug): array
    {
        $slug = strtolower(trim($slug));
        $item = $this->addons->catalog()[$slug] ?? null;
        if ($item === null || empty($item['enabled'])) {
            return [];
        }
        $out = [];
        foreach (['monthly', 'yearly'] as $cycle) {
            if ((float) ($item[$cycle] ?? 0) > 0) {
                $out[] = $cycle;
            }
        }

        return $out;
    }

    /**
     * Invoice payload for a quote. Always sent/unpaid/SAR; never draft.
     *
     * @param array{slug:string,name:string,cycle:string,unit_price:float,tax_rate:float,amount:float,tax_amount:float,total_amount:float,currency:string} $quote
     * @return array<string, mixed>
     */
    public function invoiceFieldsFromQuote(array $quote, int $companyId): array
    {
        $slug = strtolower(trim((string) ($quote['slug'] ?? '')));
        $cycle = strtolower(trim((string) ($quote['cycle'] ?? 'monthly')));

        return [
            'company_id' => $companyId,
            'subscription_id' => null,
            'invoice_no' => '',
            'invoice_type' => 'tax',
            'po_number' => $this->poNumber($slug, $cycle),
            'amount' => (float) $quote['amount'],
            'tax_amount' => (float) $quote['tax_amount'],
            'total_amount' => (float) $quote['total_amount'],
            'currency' => self::CURRENCY,
            'discount_amount' => 0.0,
            'discount_type' => 'value',
            'tax_rate' => self::TAX_RATE,
            'payment_terms_days' => 30,
            'status' => 'sent',
            'payment_status' => 'unpaid',
            'notes' => 'RATIB ERP Module Add-on: ' . $slug . "\nBilling cycle: " . $cycle,
            'issued_at' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'sent_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Effective access set via PlanLimitService::getLimits (read-only).
     * Does not use companyHasModule() so Super Admin bypass cannot skip checkout for a tenant.
     */
    public function companyAlreadyHasModule(int $companyId, string $slug): bool
    {
        if ($companyId < 1) {
            return false;
        }
        $slug = strtolower(trim($slug));
        try {
            $limits = (new PlanLimitService())->getLimits($companyId);
            $modules = $limits['modules'] ?? [];

            return is_array($modules) && in_array($slug, $modules, true);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $posted Ignored for company_id / prices
     * @return array{ok:bool,code:string,redirect_url?:string,invoice_id?:int,state?:string}
     */
    public function startCheckout(int $companyId, string $slug, array $posted = []): array
    {
        if (!$this->addons->isEnabled()) {
            return ['ok' => false, 'code' => 'disabled'];
        }
        if ($companyId < 1) {
            return ['ok' => false, 'code' => 'no_company'];
        }
        $slug = strtolower(trim($slug));
        if ($slug === '' || !isset($this->addons->catalog()[$slug])) {
            return ['ok' => false, 'code' => 'unknown_module'];
        }
        $cycle = $this->requestedCycle($posted);
        if ($cycle === null) {
            return ['ok' => false, 'code' => 'invalid_cycle'];
        }
        $quote = $this->quote($slug, $cycle);
        if ($quote === null) {
            if (!$this->addons->isPurchasable($slug)) {
                return ['ok' => false, 'code' => 'not_purchasable'];
            }

            return ['ok' => false, 'code' => 'invalid_cycle'];
        }
        if ($this->companyAlreadyHasModule($companyId, $slug)) {
            return ['ok' => false, 'code' => 'already_enabled', 'state' => 'active'];
        }

        try {
            $existing = $this->findOpenInvoice($companyId, $slug, $cycle);
            if ($existing !== null) {
                $payStatus = strtolower(trim((string) ($existing['payment_status'] ?? '')));
                if ($payStatus === 'paid') {
                    return [
                        'ok' => true,
                        'code' => 'paid_pending_activation',
                        'invoice_id' => (int) $existing['id'],
                        'state' => 'paid_pending_activation',
                    ];
                }
                $invoiceId = (int) $existing['id'];
            } else {
                $invoiceId = $this->createPayableInvoice($quote, $companyId);
            }

            if ($invoiceId < 1) {
                return ['ok' => false, 'code' => 'invoice_create_failed'];
            }

            $invoice = (new Invoice())->find($invoiceId);
            if (!is_array($invoice) || (string) ($invoice['status'] ?? '') === 'draft') {
                return ['ok' => false, 'code' => 'invoice_not_payable'];
            }

            $result = $this->initiatePayment($invoiceId, $companyId);
            if (!($result['ok'] ?? false) || empty($result['redirect_url'])) {
                Logger::error('module_addon_payment_init_failed', [
                    'invoice_id' => $invoiceId,
                    'error' => (string) ($result['error'] ?? 'gateway_error'),
                ]);

                return ['ok' => false, 'code' => 'payment_init_failed', 'invoice_id' => $invoiceId];
            }

            return [
                'ok' => true,
                'code' => 'redirect',
                'redirect_url' => (string) $result['redirect_url'],
                'invoice_id' => $invoiceId,
            ];
        } catch (Throwable $e) {
            Logger::error('module_addon_checkout_failed', [
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'code' => 'invoice_create_failed'];
        }
    }

    /**
     * @return array{ok:bool,code:string,state?:string,module?:array<string,mixed>,invoice?:array<string,mixed>|null,payment_status?:string,cycle?:string}
     */
    public function statusPayload(int $companyId, string $slug): array
    {
        $slug = strtolower(trim($slug));
        $item = $this->addons->catalog()[$slug] ?? null;
        if ($item === null) {
            return ['ok' => false, 'code' => 'unknown_module'];
        }
        if (!$this->addons->isEnabled()) {
            return [
                'ok' => true,
                'code' => 'unavailable',
                'state' => 'unavailable',
                'module' => ['slug' => $slug, 'name' => (string) ($item['name'] ?? $slug)],
            ];
        }

        $invoice = $this->findLatestAddonInvoice($companyId, $slug);
        $cycle = '';
        if (is_array($invoice)) {
            $cycle = $this->cycleFromPoNumber((string) ($invoice['po_number'] ?? ''));
        }
        $state = $this->resolveState($companyId, $slug, $invoice);

        return [
            'ok' => true,
            'code' => $state,
            'state' => $state,
            'module' => ['slug' => $slug, 'name' => (string) ($item['name'] ?? $slug)],
            'invoice' => $invoice,
            'payment_status' => is_array($invoice) ? (string) ($invoice['payment_status'] ?? '') : '',
            'cycle' => $cycle,
        ];
    }

    public function poNumber(string $slug, string $cycle): string
    {
        return 'ADDON:' . strtolower(trim($slug)) . ':' . strtolower(trim($cycle));
    }

    /**
     * @param array{slug:string,name:string,cycle:string,unit_price:float,tax_rate:float,amount:float,tax_amount:float,total_amount:float,currency:string} $quote
     */
    private function createPayableInvoice(array $quote, int $companyId): int
    {
        $fields = $this->invoiceFieldsFromQuote($quote, $companyId);
        $fields['invoice_no'] = (new BillingService())->nextInvoiceNo();
        if ((string) $fields['status'] === 'draft' || (float) $fields['total_amount'] <= 0) {
            return 0;
        }

        $db = Database::connection();
        $started = false;
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $started = true;
        }
        try {
            $invoiceId = (new Invoice())->create($fields);
            if ($invoiceId < 1) {
                if ($started) {
                    $db->rollBack();
                }

                return 0;
            }
            LineItems::syncInvoiceLines($invoiceId, [[
                'item_name' => 'RATIB ERP Module Add-on: ' . $quote['slug'],
                'description' => 'RATIB ERP Module Add-on: ' . $quote['slug'] . "\nBilling cycle: " . $quote['cycle'],
                'quantity' => 1,
                'unit' => 'unit',
                'unit_price' => $quote['unit_price'],
                'tax_rate' => self::TAX_RATE,
                'excluding_tax' => 1,
            ]]);
            if ($started) {
                $db->commit();
            }

            return $invoiceId;
        } catch (Throwable $e) {
            if ($started && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** @return array<string, mixed>|null */
    private function findOpenInvoice(int $companyId, string $slug, string $cycle): ?array
    {
        $po = $this->poNumber($slug, $cycle);
        try {
            $row = (new Invoice())->queryOne(
                "SELECT * FROM rateb_invoices
                 WHERE company_id = :cid AND po_number = :po
                   AND status <> 'cancelled' AND status <> 'draft'
                 ORDER BY id DESC LIMIT 1",
                ['cid' => $companyId, 'po' => $po]
            );

            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @return array<string, mixed>|null */
    private function findLatestAddonInvoice(int $companyId, string $slug): ?array
    {
        if ($companyId < 1) {
            return null;
        }
        try {
            $row = (new Invoice())->queryOne(
                "SELECT * FROM rateb_invoices
                 WHERE company_id = :cid AND po_number LIKE :pfx
                 ORDER BY id DESC LIMIT 1",
                ['cid' => $companyId, 'pfx' => 'ADDON:' . $slug . ':%']
            );

            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @param array<string, mixed>|null $invoice */
    private function resolveState(int $companyId, string $slug, ?array $invoice): string
    {
        if ($this->companyAlreadyHasModule($companyId, $slug)) {
            return 'active';
        }
        if ($invoice === null) {
            return 'unavailable';
        }
        $pay = strtolower(trim((string) ($invoice['payment_status'] ?? '')));
        $st = strtolower(trim((string) ($invoice['status'] ?? '')));
        if ($pay === 'paid') {
            return 'paid_pending_activation';
        }
        if ($st === 'cancelled') {
            return 'failed';
        }
        $txFailed = $this->latestTransactionFailed((int) ($invoice['id'] ?? 0), $companyId);
        if ($txFailed) {
            return 'failed';
        }

        return 'payment_pending';
    }

    private function latestTransactionFailed(int $invoiceId, int $companyId): bool
    {
        if ($invoiceId < 1) {
            return false;
        }
        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                "SELECT status FROM rateb_payment_transactions
                 WHERE invoice_id = :iid AND company_id = :cid
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute(['iid' => $invoiceId, 'cid' => $companyId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $status = strtolower(trim((string) ($row['status'] ?? '')));

            return in_array($status, ['failed', 'cancelled'], true);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function cycleFromPoNumber(string $po): string
    {
        if (preg_match('/^ADDON:[^:]+:(monthly|yearly)$/', $po, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * @return array{ok:bool,redirect_url?:string,transaction_id?:int,error?:string}
     */
    private function initiatePayment(int $invoiceId, int $companyId): array
    {
        if ($this->paymentInitiator !== null) {
            return ($this->paymentInitiator)($invoiceId, $companyId);
        }

        return (new PaymentService())->initiate($invoiceId, 'moyasar', null, $companyId);
    }
}
