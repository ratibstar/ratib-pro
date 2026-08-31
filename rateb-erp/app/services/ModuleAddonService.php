<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use Rateb\App\Core\Database;
use Rateb\App\Models\Company;
use Rateb\App\Models\Plan;
use Throwable;

/**
 * Module Add-on Commerce ledger (Phase 1).
 *
 * NOT a runtime access authority. HTTP access remains:
 * company.modules → PlanLimitService::companyHasModule() → CompanyModuleMiddleware.
 *
 * calculateEffectiveModules() is diagnostics/tests only — never call from middleware.
 *
 * V1 limitation: JSON cannot tag plan vs add-on vs manual grant. Expiration uses
 * only plan membership, other active add-ons, and preexisting_grant (set at
 * activation). Do not infer a fourth "manual" source from JSON after purchase.
 */
final class ModuleAddonService
{
    public const FLAG_NAME = 'MODULE_ADDON_COMMERCE_ENABLED';

    /** @var array<string, array<string, mixed>> */
    private array $catalog;

    /** Optional test double; production uses AgencyErpMigrationService. */
    private mixed $agencySync;

    /**
     * @param array<string, array<string, mixed>>|null $catalog Injected catalog for tests
     */
    public function __construct(?array $catalog = null, mixed $agencySync = null)
    {
        $this->catalog = $catalog ?? $this->loadCatalogFile();
        $this->agencySync = $agencySync;
    }

    public function isEnabled(): bool
    {
        if (defined(self::FLAG_NAME)) {
            return (bool) constant(self::FLAG_NAME);
        }
        $env = getenv(self::FLAG_NAME);
        if ($env === false || $env === '') {
            $env = $_ENV[self::FLAG_NAME] ?? '';
        }
        if ($env === '' || $env === false) {
            return false;
        }

        return in_array(strtolower(trim((string) $env)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @return array<string, array{name:string, monthly:float, yearly:float, enabled:bool}>
     */
    public function catalog(): array
    {
        $out = [];
        foreach ($this->catalog as $slug => $row) {
            $slug = strtolower(trim((string) $slug));
            if ($slug === '' || !is_array($row)) {
                continue;
            }
            $out[$slug] = [
                'name' => (string) ($row['name'] ?? $slug),
                'monthly' => (float) ($row['monthly'] ?? 0),
                'yearly' => (float) ($row['yearly'] ?? 0),
                'enabled' => !empty($row['enabled']),
            ];
        }

        return $out;
    }

    public function isPurchasable(string $slug): bool
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            return false;
        }
        $known = PlanLimitService::filterKnownModules([$slug]);
        if ($known === [] || $known[0] !== $slug) {
            return false;
        }
        $item = $this->catalog()[$slug] ?? null;
        if ($item === null || empty($item['enabled'])) {
            return false;
        }
        $monthly = (float) ($item['monthly'] ?? 0);
        $yearly = (float) ($item['yearly'] ?? 0);

        return $monthly > 0 || $yearly > 0;
    }

    /**
     * Raw company.modules decoded to unique string slugs.
     * Empty list means plan fallback at the access gate — not "no modules".
     *
     * @return list<string>
     */
    public function currentJson(int $companyId): array
    {
        if ($companyId < 1) {
            return [];
        }
        $company = $this->companyRow($companyId);

        return $this->decodeModulesList($company['modules'] ?? null);
    }

    /**
     * Plan pack only (read-only). Does not apply company.modules override.
     *
     * @return list<string>
     */
    public function planModules(int $companyId): array
    {
        if ($companyId < 1) {
            return [];
        }
        $company = $this->companyRow($companyId);
        if ($company === null) {
            return [];
        }
        $planId = (int) ($company['plan_id'] ?? 0);
        $slug = '';
        if ($planId > 0) {
            $plan = (new Plan())->find($planId);
            $slug = strtolower(trim((string) ($plan['slug'] ?? '')));
        }
        $mods = $slug !== ''
            ? PlanLimitService::modulesForSlug($slug)
            : PlanLimitService::defaultModules();

        return PlanLimitService::filterKnownModules($mods);
    }

    /**
     * Diagnostics/tests only. NEVER use as HTTP access gate.
     *
     * @return list<string>
     */
    public function calculateEffectiveModules(int $companyId): array
    {
        $union = array_merge(
            $this->planModules($companyId),
            $this->activeAddonSlugs($companyId),
            $this->currentJson($companyId)
        );

        return $this->normalizeSlugList($union);
    }

    /**
     * @return array{ok:bool, code:string, disabled?:bool, addon_id?:int, company_id?:int, module?:string, preexisting_grant?:int, modules?:list<string>}
     */
    public function activateFromPaidInvoice(int $invoiceId, ?int $paymentTransactionId = null): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'code' => 'disabled', 'disabled' => true];
        }
        if ($invoiceId < 1) {
            return ['ok' => false, 'code' => 'invoice_not_found'];
        }

        $db = Database::connection();
        $started = false;
        try {
            if (!$db->inTransaction()) {
                $db->beginTransaction();
                $started = true;
            }

            $invoice = $this->lockInvoice($db, $invoiceId);
            if ($invoice === null) {
                if ($started) {
                    $db->rollBack();
                }

                return ['ok' => false, 'code' => 'invoice_not_found'];
            }

            $invoiceCompany = (int) ($invoice['company_id'] ?? 0);
            $addon = $this->lockAddonByInvoice($db, $invoiceId);
            if ($addon === null && !$this->isAddonInvoice($invoice)) {
                if ($started) {
                    $db->commit();
                }

                return ['ok' => true, 'code' => 'ignored'];
            }
            if (!$this->invoiceIsPaid($invoice)) {
                if ($started) {
                    $db->rollBack();
                }

                return ['ok' => false, 'code' => 'invoice_not_paid'];
            }

            if ($addon === null) {
                $addon = $this->ensurePendingLedgerFromPaidInvoice($db, $invoice, $paymentTransactionId);
                if ($addon === null) {
                    if ($started) {
                        $db->commit();
                    }

                    return ['ok' => true, 'code' => 'ignored'];
                }
                if ((int) ($addon['id'] ?? 0) < 1) {
                    if ($started) {
                        $db->commit();
                    }
                    $this->syncLinkedAgencyAfterCommit(
                        $started,
                        !empty($addon['_json_changed']),
                        (int) ($addon['company_id'] ?? $invoiceCompany),
                        is_array($addon['_modules'] ?? null) ? $addon['_modules'] : [],
                        0,
                        strtolower(trim((string) ($addon['module_slug'] ?? '')))
                    );

                    return [
                        'ok' => true,
                        'code' => 'already_active',
                        'company_id' => (int) ($addon['company_id'] ?? $invoiceCompany),
                        'module' => strtolower(trim((string) ($addon['module_slug'] ?? ''))),
                        'preexisting_grant' => 1,
                    ];
                }
            }

            $companyId = (int) ($addon['company_id'] ?? 0);
            if ($companyId < 1 || $invoiceCompany !== $companyId) {
                if ($started) {
                    $db->rollBack();
                }

                return ['ok' => false, 'code' => 'invoice_company_mismatch'];
            }

            $slug = strtolower(trim((string) ($addon['module_slug'] ?? '')));
            $known = PlanLimitService::filterKnownModules([$slug]);
            if ($slug === '' || $known === [] || !isset($this->catalog()[$slug])) {
                if ($started) {
                    $db->rollBack();
                }

                return ['ok' => false, 'code' => 'invalid_module'];
            }

            $this->lockCompany($db, $companyId);
            $this->forgetCompanyRowMemo($companyId);

            $snapshot = $this->materializeCurrentModules($companyId);
            $alreadyPresent = in_array($slug, $snapshot, true);
            $status = (string) ($addon['status'] ?? '');
            $addonId = (int) ($addon['id'] ?? 0);

            if ($status !== 'active' && $this->hasOtherActiveAddon($companyId, $slug, $addonId)) {
                $jsonChanged = false;
                if (!$alreadyPresent) {
                    $newModules = $this->normalizeSlugList(array_merge($snapshot, [$slug]));
                    (new Company())->updateModules($companyId, $newModules);
                    PlanLimitService::forgetCompanyLimits($companyId);
                    $this->forgetCompanyRowMemo($companyId);
                    $snapshot = $newModules;
                    $jsonChanged = true;
                }
                if ($started) {
                    $db->commit();
                }
                $this->syncLinkedAgencyAfterCommit($started, $jsonChanged, $companyId, $snapshot, $addonId, $slug);

                return [
                    'ok' => true,
                    'code' => 'already_active',
                    'addon_id' => $addonId,
                    'company_id' => $companyId,
                    'module' => $slug,
                    'preexisting_grant' => 1,
                    'modules' => $snapshot,
                ];
            }

            if ($status === 'active' && $alreadyPresent) {
                $this->markAddonActive($db, (int) $addon['id'], (int) ($addon['preexisting_grant'] ?? 0), $paymentTransactionId);
                if ($started) {
                    $db->commit();
                }

                return [
                    'ok' => true,
                    'code' => 'already_active',
                    'addon_id' => (int) $addon['id'],
                    'company_id' => $companyId,
                    'module' => $slug,
                    'preexisting_grant' => (int) ($addon['preexisting_grant'] ?? 0),
                    'modules' => $snapshot,
                ];
            }

            $preexisting = $alreadyPresent ? 1 : (int) ($addon['preexisting_grant'] ?? 0);
            if ($status !== 'active') {
                $preexisting = $alreadyPresent ? 1 : 0;
            }

            $newModules = $this->normalizeSlugList(array_merge($snapshot, [$slug]));
            $okWrite = (new Company())->updateModules($companyId, $newModules);
            if (!$okWrite) {
                if ($started) {
                    $db->rollBack();
                }

                return ['ok' => false, 'code' => 'modules_write_failed'];
            }

            $this->markAddonActive($db, (int) $addon['id'], $preexisting, $paymentTransactionId);
            PlanLimitService::forgetCompanyLimits($companyId);
            $this->forgetCompanyRowMemo($companyId);

            if ($started) {
                $db->commit();
            }
            $this->syncLinkedAgencyAfterCommit($started, true, $companyId, $newModules, (int) $addon['id'], $slug);

            return [
                'ok' => true,
                'code' => 'activated',
                'addon_id' => (int) $addon['id'],
                'company_id' => $companyId,
                'module' => $slug,
                'preexisting_grant' => $preexisting,
                'modules' => $newModules,
            ];
        } catch (Throwable $e) {
            if ($started && $db->inTransaction()) {
                $db->rollBack();
            }
            Logger::error('module_addon_activate_failed', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'code' => 'activate_failed'];
        }
    }

    /**
     * @return array{ok:bool, code:string, disabled?:bool, addon_id?:int, removed?:bool, modules?:list<string>}
     */
    public function expireAddon(int $addonId, bool $onlyIfDue = false): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'code' => 'disabled', 'disabled' => true];
        }
        if ($addonId < 1) {
            return ['ok' => false, 'code' => 'addon_not_found'];
        }

        $db = Database::connection();
        $started = false;
        try {
            if (!$db->inTransaction()) {
                $db->beginTransaction();
                $started = true;
            }

            $addon = $this->lockAddonById($db, $addonId);
            if ($addon === null) {
                if ($started) {
                    $db->rollBack();
                }

                return ['ok' => false, 'code' => 'addon_not_found'];
            }

            $companyId = (int) ($addon['company_id'] ?? 0);
            $slug = strtolower(trim((string) ($addon['module_slug'] ?? '')));
            $status = (string) ($addon['status'] ?? '');
            $this->lockCompany($db, $companyId);
            $this->forgetCompanyRowMemo($companyId);

            if ($status === 'expired') {
                if ($started) {
                    $db->commit();
                }

                return [
                    'ok' => true,
                    'code' => 'already_expired',
                    'addon_id' => $addonId,
                    'removed' => false,
                    'modules' => $this->currentJson($companyId),
                ];
            }
            if ($status !== 'active') {
                if ($started) {
                    $db->commit();
                }

                return [
                    'ok' => true,
                    'code' => 'not_eligible',
                    'addon_id' => $addonId,
                    'removed' => false,
                    'modules' => $this->currentJson($companyId),
                ];
            }
            if ($onlyIfDue && !$this->addonEndsBeforeToday($addon)) {
                if ($started) {
                    $db->commit();
                }

                return [
                    'ok' => true,
                    'code' => 'not_due',
                    'addon_id' => $addonId,
                    'removed' => false,
                    'modules' => $this->currentJson($companyId),
                ];
            }

            $explicit = $this->currentJson($companyId);
            $removed = false;
            if ($explicit !== [] && $this->shouldStripModule($companyId, $slug, $addonId, (int) ($addon['preexisting_grant'] ?? 0))) {
                $kept = $this->uniquePreserveOrder(array_values(array_filter(
                    $explicit,
                    static fn(string $m): bool => $m !== $slug
                )));
                if ($kept !== $explicit) {
                    $okWrite = (new Company())->updateModules($companyId, $kept);
                    if (!$okWrite) {
                        if ($started) {
                            $db->rollBack();
                        }

                        return ['ok' => false, 'code' => 'modules_write_failed'];
                    }
                    $removed = true;
                    $explicit = $kept;
                }
            }

            $this->markAddonExpired($db, $addonId);
            PlanLimitService::forgetCompanyLimits($companyId);
            $this->forgetCompanyRowMemo($companyId);

            if ($started) {
                $db->commit();
            }
            $this->syncLinkedAgencyAfterCommit($started, $removed, $companyId, $explicit, $addonId, $slug);

            return [
                'ok' => true,
                'code' => 'expired',
                'addon_id' => $addonId,
                'removed' => $removed,
                'modules' => $explicit,
            ];
        } catch (Throwable $e) {
            if ($started && $db->inTransaction()) {
                $db->rollBack();
            }
            Logger::error('module_addon_expire_failed', [
                'addon_id' => $addonId,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'code' => 'expire_failed'];
        }
    }

    /**
     * Expire add-ons whose ends_at calendar date has passed (valid through ends_at).
     * Wired from CronService::runAll(); never throws to the cron caller.
     */
    public function expireDueAddons(int $limit = 50): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }
        $limit = max(1, min(500, $limit));
        $todayExpr = Database::isSqlite() ? "date('now')" : 'CURDATE()';
        $count = 0;
        try {
            $db = Database::connection();
            $stmt = $db->query(
                "SELECT id FROM rateb_company_module_addons
                 WHERE status = 'active' AND ends_at IS NOT NULL AND ends_at < {$todayExpr}
                 ORDER BY id ASC LIMIT " . $limit
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $result = $this->expireAddon($id, true);
                if (($result['code'] ?? '') === 'expired') {
                    $count++;
                }
            }
        } catch (Throwable $e) {
            Logger::error('module_addon_expire_due_failed', ['error' => $e->getMessage()]);
        }

        return $count;
    }

    /**
     * Post-commit overwrite of a linked agency company.modules snapshot.
     * Failure is logged and never rolls back the local commercial transaction.
     *
     * @param list<string> $modules
     */
    private function syncLinkedAgencyAfterCommit(
        bool $committedHere,
        bool $jsonChanged,
        int $companyId,
        array $modules,
        int $addonId,
        string $slug
    ): void {
        if (!$committedHere || !$jsonChanged || $companyId < 1) {
            return;
        }
        try {
            $agency = $this->agencySync ?? new AgencyErpMigrationService();
            $agency->pushModulesToLinkedAgency($companyId, array_values($modules));
        } catch (Throwable $e) {
            Logger::error('module_addon_agency_push_failed', [
                'company_id' => $companyId,
                'module_slug' => $slug,
                'addon_id' => $addonId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @param array<string, mixed> $addon */
    private function addonEndsBeforeToday(array $addon): bool
    {
        $ends = substr(trim((string) ($addon['ends_at'] ?? '')), 0, 10);
        if ($ends === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ends)) {
            return false;
        }

        return $ends < date('Y-m-d');
    }

    /**
     * Explicit JSON if non-empty, otherwise plan pack. Then unique + implied core slugs.
     *
     * @return list<string>
     */
    public function materializeCurrentModules(int $companyId): array
    {
        $explicit = $this->currentJson($companyId);
        $base = $explicit !== [] ? $explicit : $this->planModules($companyId);

        return $this->normalizeSlugList($base);
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    public function decodeModulesList($raw): array
    {
        $list = [];
        if (is_array($raw)) {
            if ($this->isListArray($raw)) {
                foreach ($raw as $item) {
                    if (is_string($item) || is_int($item)) {
                        $list[] = strtolower(trim((string) $item));
                    }
                }
            }
        } elseif (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && $this->isListArray($decoded)) {
                foreach ($decoded as $item) {
                    if (is_string($item) || is_int($item)) {
                        $list[] = strtolower(trim((string) $item));
                    }
                }
            }
        }

        return $this->uniquePreserveOrder(array_values(array_filter(
            $list,
            static fn(string $s): bool => $s !== ''
        )));
    }

    /**
     * Phase 2 marker: po_number = ADDON:{slug}:{monthly|yearly}
     *
     * @return array{slug:string,cycle:string}|null
     */
    public function parseAddonPoNumber(string $poNumber): ?array
    {
        if (!preg_match('/^ADDON:([a-z0-9_]+):(monthly|yearly)$/i', trim($poNumber), $m)) {
            return null;
        }

        return ['slug' => strtolower($m[1]), 'cycle' => strtolower($m[2])];
    }

    /** @param array<string, mixed> $invoice */
    public function isAddonInvoice(array $invoice): bool
    {
        $parsed = $this->parseAddonPoNumber((string) ($invoice['po_number'] ?? ''));
        if ($parsed === null) {
            return false;
        }
        $known = PlanLimitService::filterKnownModules([$parsed['slug']]);

        return $known !== [] && isset($this->catalog()[$parsed['slug']]);
    }

    /**
     * Create a pending ledger row for a paid Phase 2 add-on invoice.
     * Customer invoices (non-ADDON po_number) return null — no activation.
     *
     * @param array<string, mixed> $invoice
     * @return array<string, mixed>|null
     */
    private function ensurePendingLedgerFromPaidInvoice(PDO $db, array $invoice, ?int $paymentTransactionId): ?array
    {
        $invoiceId = (int) ($invoice['id'] ?? 0);
        $companyId = (int) ($invoice['company_id'] ?? 0);
        $parsed = $this->parseAddonPoNumber((string) ($invoice['po_number'] ?? ''));
        if ($invoiceId < 1 || $companyId < 1 || $parsed === null) {
            return null;
        }
        $slug = $parsed['slug'];
        $cycle = $parsed['cycle'];
        $known = PlanLimitService::filterKnownModules([$slug]);
        if ($known === [] || !isset($this->catalog()[$slug])) {
            return null;
        }

        $existing = $this->lockAddonByInvoice($db, $invoiceId);
        if ($existing !== null) {
            return $existing;
        }

        if ($this->hasOtherActiveAddon($companyId, $slug, 0)) {
            $this->lockCompany($db, $companyId);
            $this->forgetCompanyRowMemo($companyId);
            $snapshot = $this->materializeCurrentModules($companyId);
            $modules = $snapshot;
            $wrote = false;
            if (!in_array($slug, $snapshot, true)) {
                $modules = $this->normalizeSlugList(array_merge($snapshot, [$slug]));
                (new Company())->updateModules($companyId, $modules);
                PlanLimitService::forgetCompanyLimits($companyId);
                $this->forgetCompanyRowMemo($companyId);
                $wrote = true;
            }

            return [
                'id' => 0,
                'company_id' => $companyId,
                'module_slug' => $slug,
                'status' => 'active',
                'preexisting_grant' => 1,
                '_json_changed' => $wrote,
                '_modules' => $modules,
            ];
        }

        $starts = date('Y-m-d');
        $ends = $cycle === 'yearly'
            ? date('Y-m-d', strtotime('+1 year'))
            : date('Y-m-d', strtotime('+1 month'));
        try {
            $stmt = $db->prepare(
                "INSERT INTO rateb_company_module_addons
                    (company_id, module_slug, status, starts_at, ends_at, billing_cycle, invoice_id,
                     payment_transaction_id, preexisting_grant, source)
                 VALUES
                    (:cid, :slug, 'pending', :starts, :ends, :cycle, :iid, :txid, 0, 'self_serve')"
            );
            $stmt->execute([
                'cid' => $companyId,
                'slug' => $slug,
                'starts' => $starts,
                'ends' => $ends,
                'cycle' => $cycle,
                'iid' => $invoiceId,
                'txid' => ($paymentTransactionId !== null && $paymentTransactionId > 0) ? $paymentTransactionId : null,
            ]);
        } catch (Throwable $e) {
            $again = $this->lockAddonByInvoice($db, $invoiceId);
            if ($again !== null) {
                return $again;
            }
            if ($paymentTransactionId !== null && $paymentTransactionId > 0) {
                try {
                    $retry = $db->prepare(
                        "INSERT INTO rateb_company_module_addons
                            (company_id, module_slug, status, starts_at, ends_at, billing_cycle, invoice_id,
                             payment_transaction_id, preexisting_grant, source)
                         VALUES
                            (:cid, :slug, 'pending', :starts, :ends, :cycle, :iid, NULL, 0, 'self_serve')"
                    );
                    $retry->execute([
                        'cid' => $companyId,
                        'slug' => $slug,
                        'starts' => $starts,
                        'ends' => $ends,
                        'cycle' => $cycle,
                        'iid' => $invoiceId,
                    ]);
                } catch (Throwable $retryError) {
                    return $this->lockAddonByInvoice($db, $invoiceId);
                }

                return $this->lockAddonByInvoice($db, $invoiceId);
            }

            return null;
        }

        return $this->lockAddonByInvoice($db, $invoiceId);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadCatalogFile(): array
    {
        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $file = $root . '/config/module-addons.php';
        if (!is_file($file)) {
            return [];
        }
        $data = require $file;

        return is_array($data) ? $data : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function companyRow(int $companyId): ?array
    {
        return (new Company())->find($companyId);
    }

    /**
     * @param list<string> $slugs
     * @return list<string>
     */
    private function normalizeSlugList(array $slugs): array
    {
        $known = PlanLimitService::filterKnownModules($this->uniquePreserveOrder($slugs));
        foreach (['dashboard', 'notifications'] as $implied) {
            if (!in_array($implied, $known, true)) {
                $known[] = $implied;
            }
        }

        return array_values($known);
    }

    /**
     * @param list<string> $slugs
     * @return list<string>
     */
    private function uniquePreserveOrder(array $slugs): array
    {
        $seen = [];
        $out = [];
        foreach ($slugs as $slug) {
            $slug = strtolower(trim((string) $slug));
            if ($slug === '' || isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;
            $out[] = $slug;
        }

        return $out;
    }

    /** @param array<int|string, mixed> $arr */
    private function isListArray(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }
        $i = 0;
        foreach ($arr as $k => $_) {
            if ($k !== $i) {
                return false;
            }
            $i++;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function activeAddonSlugs(int $companyId): array
    {
        if ($companyId < 1) {
            return [];
        }
        try {
            $todayExpr = Database::isSqlite() ? "date('now')" : 'CURDATE()';
            $stmt = Database::connection()->prepare(
                "SELECT module_slug FROM rateb_company_module_addons
                 WHERE company_id = :cid AND status = 'active'
                   AND (ends_at IS NULL OR ends_at >= {$todayExpr})"
            );
            $stmt->execute(['cid' => $companyId]);
            $slugs = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $slugs[] = strtolower(trim((string) ($row['module_slug'] ?? '')));
            }

            return array_values(array_filter($slugs, static fn(string $s): bool => $s !== ''));
        } catch (Throwable $e) {
            return [];
        }
    }

    private function shouldStripModule(int $companyId, string $slug, int $exceptAddonId, int $preexistingGrant): bool
    {
        if ($slug === '' || $preexistingGrant === 1) {
            return false;
        }
        if (in_array($slug, $this->planModules($companyId), true)) {
            return false;
        }

        return !$this->hasOtherActiveAddon($companyId, $slug, $exceptAddonId);
    }

    private function hasOtherActiveAddon(int $companyId, string $slug, int $exceptAddonId): bool
    {
        $todayExpr = Database::isSqlite() ? "date('now')" : 'CURDATE()';
        $stmt = Database::connection()->prepare(
            "SELECT id FROM rateb_company_module_addons
             WHERE company_id = :cid AND module_slug = :slug AND status = 'active' AND id <> :xid
               AND (ends_at IS NULL OR ends_at >= {$todayExpr})
             LIMIT 1"
        );
        $stmt->execute([
            'cid' => $companyId,
            'slug' => $slug,
            'xid' => $exceptAddonId,
        ]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** @param array<string, mixed> $invoice */
    private function invoiceIsPaid(array $invoice): bool
    {
        $st = strtolower(trim((string) ($invoice['status'] ?? '')));
        if ($st === 'cancelled') {
            return false;
        }
        $pay = strtolower(trim((string) ($invoice['payment_status'] ?? '')));

        return $pay === 'paid' || $st === 'paid';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lockInvoice(PDO $db, int $invoiceId): ?array
    {
        $sql = 'SELECT * FROM rateb_invoices WHERE id = :id LIMIT 1';
        if (!Database::isSqlite()) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $invoiceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lockAddonByInvoice(PDO $db, int $invoiceId): ?array
    {
        $sql = 'SELECT * FROM rateb_company_module_addons WHERE invoice_id = :id LIMIT 1';
        if (!Database::isSqlite()) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $invoiceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lockAddonById(PDO $db, int $addonId): ?array
    {
        $sql = 'SELECT * FROM rateb_company_module_addons WHERE id = :id LIMIT 1';
        if (!Database::isSqlite()) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $addonId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function lockCompany(PDO $db, int $companyId): void
    {
        if ($companyId < 1) {
            return;
        }
        $sql = 'SELECT id FROM rateb_companies WHERE id = :id LIMIT 1';
        if (!Database::isSqlite()) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $companyId]);
        $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function markAddonActive(PDO $db, int $addonId, int $preexisting, ?int $paymentTransactionId = null): void
    {
        $sql = "UPDATE rateb_company_module_addons
             SET status = 'active', preexisting_grant = :pg, updated_at = :ts";
        $params = [
            'pg' => $preexisting,
            'ts' => date('Y-m-d H:i:s'),
            'id' => $addonId,
        ];
        if ($paymentTransactionId !== null && $paymentTransactionId > 0) {
            $sql .= ', payment_transaction_id = :txid';
            $params['txid'] = $paymentTransactionId;
        }
        $sql .= ' WHERE id = :id';
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        } catch (Throwable $e) {
            if ($paymentTransactionId !== null && $paymentTransactionId > 0) {
                $fallback = $db->prepare(
                    "UPDATE rateb_company_module_addons
                     SET status = 'active', preexisting_grant = :pg, updated_at = :ts
                     WHERE id = :id"
                );
                $fallback->execute([
                    'pg' => $preexisting,
                    'ts' => date('Y-m-d H:i:s'),
                    'id' => $addonId,
                ]);
            } else {
                throw $e;
            }
        }
    }

    private function markAddonExpired(PDO $db, int $addonId): void
    {
        $stmt = $db->prepare(
            "UPDATE rateb_company_module_addons
             SET status = 'expired', updated_at = :ts
             WHERE id = :id"
        );
        $stmt->execute([
            'ts' => date('Y-m-d H:i:s'),
            'id' => $addonId,
        ]);
    }

    private function forgetCompanyRowMemo(int $companyId): void
    {
        if ($companyId < 1 || !function_exists('rateb_ops_company_request_state')) {
            return;
        }
        $state = &rateb_ops_company_request_state();
        unset($state['rows'][$companyId], $state['exists'][$companyId]);
    }
}
