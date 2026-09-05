<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use Rateb\App\Core\Database;
use Rateb\App\Models\Company;
use Rateb\App\Models\Plan;
use Rateb\App\Models\SystemSetting;
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

    /** Local/staging catalog overlay only. Never honor this on production hosts. */
    public const PREVIEW_FLAG_NAME = 'RATIB_MODULE_ADDON_PREVIEW';

    /** Platform-admin commerce overlay in rateb_system_settings (this ERP database). */
    public const CATALOG_SETTING_KEY = 'module_addon_commerce_catalog';

    /** @var array<string, array<string, mixed>> */
    private array $catalog;

    /** Optional test double; production uses AgencyErpMigrationService. */
    private mixed $agencySync;

    /**
     * @param array<string, array<string, mixed>>|null $catalog Injected catalog for tests
     * @param array<string, array<string, mixed>>|null $databaseOverlay Injected DB overlay for tests
     */
    public function __construct(?array $catalog = null, mixed $agencySync = null, ?array $databaseOverlay = null)
    {
        $this->agencySync = $agencySync;
        if ($catalog !== null) {
            $this->catalog = $this->mergeCatalogOverlay($catalog, $databaseOverlay ?? []);

            return;
        }
        $this->catalog = $this->mergeCatalogOverlay(
            $this->loadCatalogFile(),
            $databaseOverlay ?? $this->loadDatabaseOverlay()
        );
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

    /** Catalog overlay / demo UI — same fail-closed guards as loadCatalogFile(). */
    public function previewUiAllowed(): bool
    {
        return $this->previewCatalogOverlayAllowed();
    }

    /** Demo user bootstrap is allowed only on the exact preview host. */
    public function previewDemoHostAllowed(): bool
    {
        if (!$this->previewCatalogOverlayAllowed()) {
            return false;
        }
        $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));

        return $host === 'admin.rateb.sa';
    }

    /**
     * Super Admin on the platform host or the exact demo preview host.
     * Tenants never manage commercial availability or prices.
     */
    public function canManagePlatformCatalog(): bool
    {
        if (!function_exists('rateb_is_super_admin') || !rateb_is_super_admin()) {
            return false;
        }
        if (function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()) {
            return true;
        }

        return $this->previewDemoHostAllowed();
    }

    /**
     * Company entitlement for كتالوج الوحدات (company.modules).
     * Uses getLimits — does not apply Super Admin companyHasModule bypass.
     */
    public function companyHasCatalogEntitlement(int $companyId): bool
    {
        if ($companyId < 1) {
            return false;
        }
        $limits = (new PlanLimitService())->getLimits($companyId);
        $modules = $limits['modules'] ?? [];

        return is_array($modules) && in_array('module_addons', $modules, true);
    }

    /** Resolve tenant company for dedicated/agency catalog nav + route gate. */
    public function resolveCatalogTenantCompanyId(): int
    {
        if (function_exists('rateb_nav_tenant_company_id_for_gate')) {
            $id = (int) rateb_nav_tenant_company_id_for_gate();
            if ($id > 0) {
                return $id;
            }
        }
        if (class_exists(DedicatedTenantPolicy::class)) {
            return (int) DedicatedTenantPolicy::primaryCompanyId();
        }

        return 0;
    }

    /**
     * Dedicated/agency hosts: catalog UI only when company.modules includes module_addons.
     * Platform oversight host: Super Admin catalog is not gated by a tenant pack.
     */
    public function catalogUiAllowedForCurrentTenant(): bool
    {
        if (!$this->canManagePlatformCatalog()) {
            return false;
        }
        if (function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()) {
            return true;
        }

        return $this->companyHasCatalogEntitlement($this->resolveCatalogTenantCompanyId());
    }

    /**
     * Persist platform commerce overrides for known catalog slugs only.
     *
     * @param array<string, mixed> $postedBySlug
     * @return array{ok:bool,code:string}
     */
    public function saveCommerceOverrides(array $postedBySlug): array
    {
        $base = $this->loadCatalogFile();
        $sanitized = $this->sanitizeCommerceOverrides($postedBySlug, array_keys($base));
        $json = json_encode($sanitized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return ['ok' => false, 'code' => 'encode_failed'];
        }
        try {
            $model = new SystemSetting();
            $existing = $model->queryOne(
                'SELECT id FROM rateb_system_settings WHERE setting_key = :k LIMIT 1',
                ['k' => self::CATALOG_SETTING_KEY]
            );
            if (is_array($existing) && (int) ($existing['id'] ?? 0) > 0) {
                $model->update((int) $existing['id'], [
                    'setting_value' => $json,
                    'setting_group' => 'module_addons',
                ]);
            } else {
                $model->create([
                    'setting_key' => self::CATALOG_SETTING_KEY,
                    'setting_value' => $json,
                    'setting_group' => 'module_addons',
                ]);
            }
            $this->catalog = $this->mergeCatalogOverlay($base, $sanitized);

            return ['ok' => true, 'code' => 'saved'];
        } catch (Throwable $e) {
            return ['ok' => false, 'code' => 'save_failed'];
        }
    }

    /**
     * @param array<string, mixed> $postedBySlug
     * @param list<string> $knownSlugs
     * @return array<string, array<string, mixed>>
     */
    public function sanitizeCommerceOverrides(array $postedBySlug, array $knownSlugs): array
    {
        $known = [];
        foreach ($knownSlugs as $slug) {
            $slug = strtolower(trim((string) $slug));
            if ($slug !== '') {
                $known[$slug] = true;
            }
        }
        $out = [];
        foreach ($postedBySlug as $slug => $row) {
            $slug = strtolower(trim((string) $slug));
            if ($slug === '' || !isset($known[$slug]) || !is_array($row)) {
                continue;
            }
            $out[$slug] = $this->sanitizeCommerceRow($row);
        }

        return $out;
    }

    /**
     * @param list<array{en?:string,ar?:string}> $features
     */
    public static function featuresToTextarea(array $features): string
    {
        $lines = [];
        foreach ($features as $feature) {
            if (!is_array($feature)) {
                $text = trim((string) $feature);
                if ($text !== '') {
                    $lines[] = $text;
                }
                continue;
            }
            $en = trim((string) ($feature['en'] ?? ''));
            $ar = trim((string) ($feature['ar'] ?? ''));
            if ($en === '' && $ar === '') {
                continue;
            }
            $lines[] = $ar !== '' ? ($en . ' | ' . $ar) : $en;
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<array{en:string,ar:string}>
     */
    public static function parseFeaturesTextarea(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s*\|\s*/u', $line, 2) ?: [];
            $en = trim((string) ($parts[0] ?? ''));
            $ar = trim((string) ($parts[1] ?? ''));
            if ($en === '' && $ar === '') {
                continue;
            }
            $out[] = ['en' => $en !== '' ? $en : $ar, 'ar' => $ar];
            if (count($out) >= 20) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function catalog(): array
    {
        $out = [];
        foreach ($this->catalog as $slug => $row) {
            $slug = strtolower(trim((string) $slug));
            if ($slug === '' || !is_array($row)) {
                continue;
            }
            $out[$slug] = $this->normalizeCatalogItem($slug, $row);
        }
        uasort($out, static function (array $a, array $b): int {
            $oa = (int) ($a['sort_order'] ?? 100);
            $ob = (int) ($b['sort_order'] ?? 100);
            if ($oa === $ob) {
                return strcmp((string) ($a['slug'] ?? ''), (string) ($b['slug'] ?? ''));
            }

            return $oa <=> $ob;
        });

        return $out;
    }

    /**
     * Localized marketing copy from the platform catalog. Informational only.
     *
     * @return array{slug:string,name:string,description:string,features:list<string>,promo_label:string,icon:string,featured:bool}|null
     */
    public function localizedDisplay(string $slug, string $locale = ''): ?array
    {
        $item = $this->catalog()[strtolower(trim($slug))] ?? null;
        if ($item === null) {
            return null;
        }
        $ar = str_starts_with(strtolower(trim($locale)), 'ar');
        $name = $ar && (string) ($item['name_ar'] ?? '') !== ''
            ? (string) $item['name_ar']
            : (string) ($item['name'] ?? $slug);
        $description = $ar && (string) ($item['description_ar'] ?? '') !== ''
            ? (string) $item['description_ar']
            : (string) ($item['description'] ?? '');
        $features = [];
        foreach ((array) ($item['features'] ?? []) as $feature) {
            if (!is_array($feature)) {
                $text = trim((string) $feature);
                if ($text !== '') {
                    $features[] = $text;
                }
                continue;
            }
            $text = $ar && trim((string) ($feature['ar'] ?? '')) !== ''
                ? (string) $feature['ar']
                : (string) ($feature['en'] ?? '');
            $text = trim($text);
            if ($text !== '') {
                $features[] = $text;
            }
        }
        $promo = (string) ($item['promo_label'] ?? '');
        $promoMap = [
            'popular' => $ar ? 'الأكثر طلبًا' : 'POPULAR',
            'best_value' => $ar ? 'أفضل قيمة' : 'BEST VALUE',
            'recommended' => $ar ? 'موصى به' : 'RECOMMENDED',
        ];

        return [
            'slug' => (string) ($item['slug'] ?? $slug),
            'name' => $name,
            'description' => $description,
            'features' => $features,
            'promo_label' => $promoMap[$promo] ?? '',
            'icon' => (string) ($item['icon'] ?? 'default'),
            'featured' => !empty($item['featured']),
        ];
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
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeCatalogItem(string $slug, array $row): array
    {
        $monthly = (float) ($row['monthly'] ?? $row['monthly_price'] ?? 0);
        $yearly = (float) ($row['yearly'] ?? $row['yearly_price'] ?? 0);
        $icon = strtolower(trim((string) ($row['icon'] ?? $slug)));
        if ($icon === '' || str_contains($icon, '://') || str_contains($icon, '/') || str_contains($icon, '.')) {
            $icon = 'default';
        }
        $promo = strtolower(trim((string) ($row['promo_label'] ?? $row['badge'] ?? '')));
        $promo = str_replace([' ', '-'], '_', $promo);
        if (!in_array($promo, ['popular', 'best_value', 'recommended'], true)) {
            $promo = '';
        }
        $features = [];
        foreach ((array) ($row['features'] ?? []) as $feature) {
            if (is_array($feature)) {
                $en = trim((string) ($feature['en'] ?? $feature['name'] ?? ''));
                $ar = trim((string) ($feature['ar'] ?? $feature['name_ar'] ?? ''));
                if ($en === '' && $ar === '') {
                    continue;
                }
                $features[] = ['en' => $en !== '' ? $en : $ar, 'ar' => $ar];
                continue;
            }
            $text = trim((string) $feature);
            if ($text !== '') {
                $features[] = ['en' => $text, 'ar' => ''];
            }
        }

        return [
            'slug' => $slug,
            'name' => (string) ($row['name'] ?? $slug),
            'name_ar' => (string) ($row['name_ar'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'description_ar' => (string) ($row['description_ar'] ?? ''),
            'icon' => $icon,
            'monthly' => $monthly,
            'yearly' => $yearly,
            'enabled' => !empty($row['enabled']),
            'featured' => !empty($row['featured']),
            'sort_order' => (int) ($row['sort_order'] ?? 100),
            'promo_label' => $promo,
            'features' => $features,
        ];
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
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function sanitizeCommerceRow(array $row): array
    {
        $monthly = (float) ($row['monthly'] ?? $row['monthly_price'] ?? 0);
        $yearly = (float) ($row['yearly'] ?? $row['yearly_price'] ?? 0);
        if ($monthly < 0) {
            $monthly = 0.0;
        }
        if ($yearly < 0) {
            $yearly = 0.0;
        }
        $monthly = min($monthly, 1000000.0);
        $yearly = min($yearly, 1000000.0);
        $promo = strtolower(trim((string) ($row['promo_label'] ?? '')));
        $promo = str_replace([' ', '-'], '_', $promo);
        if (!in_array($promo, ['popular', 'best_value', 'recommended'], true)) {
            $promo = '';
        }
        $features = $row['features'] ?? [];
        if (is_string($features)) {
            $features = self::parseFeaturesTextarea($features);
        }
        $sort = (int) ($row['sort_order'] ?? 100);
        if ($sort < 0) {
            $sort = 0;
        }
        if ($sort > 9999) {
            $sort = 9999;
        }
        $icon = strtolower(trim((string) ($row['icon'] ?? '')));
        if ($icon === '' || str_contains($icon, '://') || str_contains($icon, '/') || str_contains($icon, '.')) {
            $icon = '';
        }

        $out = [
            'enabled' => !empty($row['enabled']),
            'monthly' => $monthly,
            'yearly' => $yearly,
            'featured' => !empty($row['featured']),
            'sort_order' => $sort,
            'promo_label' => $promo,
        ];
        $description = mb_substr(trim((string) ($row['description'] ?? '')), 0, 500);
        $descriptionAr = mb_substr(trim((string) ($row['description_ar'] ?? '')), 0, 500);
        $name = mb_substr(trim((string) ($row['name'] ?? '')), 0, 80);
        $nameAr = mb_substr(trim((string) ($row['name_ar'] ?? '')), 0, 80);
        if ($description !== '') {
            $out['description'] = $description;
        }
        if ($descriptionAr !== '') {
            $out['description_ar'] = $descriptionAr;
        }
        if ($name !== '') {
            $out['name'] = $name;
        }
        if ($nameAr !== '') {
            $out['name_ar'] = $nameAr;
        }
        if ($icon !== '') {
            $out['icon'] = $icon;
        }
        if (array_key_exists('features', $row) && is_array($features)) {
            $out['features'] = array_slice($features, 0, 20);
        }

        return $out;
    }

    /**
     * @param array<string, array<string, mixed>> $base
     * @param array<string, mixed> $overlay
     * @return array<string, array<string, mixed>>
     */
    private function mergeCatalogOverlay(array $base, array $overlay): array
    {
        foreach ($overlay as $slug => $row) {
            $slug = strtolower(trim((string) $slug));
            if ($slug === '' || !isset($base[$slug]) || !is_array($row)) {
                continue;
            }
            $base[$slug] = array_merge($base[$slug], $row);
        }

        return $base;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadDatabaseOverlay(): array
    {
        if (PHP_SAPI === 'cli') {
            $force = getenv('RATEB_MODULE_ADDON_LOAD_DB_CATALOG');
            if ($force === false || $force === '') {
                $force = $_ENV['RATEB_MODULE_ADDON_LOAD_DB_CATALOG'] ?? '';
            }
            if (!in_array(strtolower(trim((string) $force)), ['1', 'true', 'yes', 'on'], true)) {
                return [];
            }
        }
        try {
            $raw = (new SystemSetting())->get(self::CATALOG_SETTING_KEY, '');
            if (!is_string($raw) || trim($raw) === '') {
                return [];
            }
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        } catch (Throwable $e) {
            return [];
        }
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
        $base = is_array($data) ? $data : [];
        if ($base === [] || !$this->previewCatalogOverlayAllowed()) {
            return $base;
        }
        $overlayFile = $this->previewOverlayCatalogPath($root);
        if ($overlayFile === null) {
            return $base;
        }
        $overlay = require $overlayFile;
        if (!is_array($overlay)) {
            return $base;
        }

        return $this->mergeCatalogOverlay($base, $overlay);
    }

    /**
     * Gitignored local overlay first. On exact demo host admin.rateb.sa, fall back to
     * the tracked admin-demo catalog so preview prices can deploy without the gitignored file.
     */
    private function previewOverlayCatalogPath(string $root): ?string
    {
        $local = $root . '/config/module-addons.local.php';
        if (is_file($local)) {
            return $local;
        }
        $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        if ($host !== 'admin.rateb.sa') {
            return null;
        }
        $demo = $root . '/config/module-addons.admin-demo.php';

        return is_file($demo) ? $demo : null;
    }

    /**
     * Gitignored catalog overlay is allowed only when ALL are true:
     * explicit RATIB_MODULE_ADDON_PREVIEW, RATEB_ENV/APP_ENV is local/staging,
     * process is not production, and host is not rateb.sa / *.rateb.sa —
     * except the exact demo host admin.rateb.sa.
     */
    private function previewCatalogOverlayAllowed(): bool
    {
        $preview = getenv(self::PREVIEW_FLAG_NAME);
        if ($preview === false || $preview === '') {
            $preview = $_ENV[self::PREVIEW_FLAG_NAME] ?? '';
        }
        if (!in_array(strtolower(trim((string) $preview)), ['1', 'true', 'yes', 'on'], true)) {
            return false;
        }

        $env = strtolower(trim((string) (getenv('RATEB_ENV') ?: getenv('APP_ENV') ?: ($_ENV['RATEB_ENV'] ?? $_ENV['APP_ENV'] ?? ''))));
        if (!in_array($env, ['local', 'staging', 'stage', 'dev', 'development'], true)) {
            return false;
        }

        if (function_exists('rateb_is_production') && rateb_is_production()) {
            return false;
        }

        $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        if ($host === 'admin.rateb.sa') {
            return true;
        }
        if ($host === 'rateb.sa') {
            return false;
        }
        $suffix = '.rateb.sa';
        if ($host !== '' && strlen($host) >= strlen($suffix) && substr($host, -strlen($suffix)) === $suffix) {
            return false;
        }

        return true;
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
