<?php
declare(strict_types=1);

/**
 * Phase 1 — Module Add-on Commerce foundation (flag, catalog, JSON rules, ledger).
 * Run: php rateb-erp/tests/ModuleAddonCommerceTest.php
 *
 * Does not enable MODULE_ADDON_COMMERCE_ENABLED in production.
 * DB cases run inside a transaction and roll back. Skipped if the ledger table is absent.
 */

$root = dirname(__DIR__);
if (!defined('RATEB_ENV_NO_SESSION')) {
    define('RATEB_ENV_NO_SESSION', true);
}
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Core\Database;
use Rateb\App\Models\Company;
use Rateb\App\Models\Plan;
use Rateb\App\Services\ModuleAddonService;
use Rateb\App\Services\PlanLimitService;

$passed = 0;
$failed = 0;
$skipped = 0;

function mac_assert(bool $cond, string $label): void
{
    global $passed, $failed;
    if ($cond) {
        ++$passed;
        echo "PASS: {$label}\n";
    } else {
        ++$failed;
        echo "FAIL: {$label}\n";
    }
}

function mac_set_flag(?string $value): void
{
    $name = ModuleAddonService::FLAG_NAME;
    if ($value === null || $value === '') {
        putenv($name);
        unset($_ENV[$name]);
        return;
    }
    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
}

function mac_set_env(string $name, ?string $value): void
{
    if ($value === null || $value === '') {
        putenv($name);
        unset($_ENV[$name]);
        return;
    }
    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
}

function mac_overlay_env(bool $on): void
{
    if ($on) {
        mac_set_env(ModuleAddonService::PREVIEW_FLAG_NAME, '1');
        mac_set_env('RATEB_ENV', 'local');
        mac_set_env('APP_ENV', 'local');
        return;
    }
    mac_set_env(ModuleAddonService::PREVIEW_FLAG_NAME, null);
    mac_set_env('RATEB_ENV', null);
    mac_set_env('APP_ENV', null);
}

$prodCatalog = require $root . '/config/module-addons.php';
mac_assert(is_array($prodCatalog) && isset($prodCatalog['crm']), 'production catalog file loads');
$adminDemoCatalog = is_file($root . '/config/module-addons.admin-demo.php')
    ? require $root . '/config/module-addons.admin-demo.php'
    : [];
mac_assert(is_array($adminDemoCatalog) && !empty($adminDemoCatalog['crm']['enabled']), 'tracked admin-demo catalog exists');
mac_assert(
    (float) ($adminDemoCatalog['crm']['monthly'] ?? 0) === 49.0
    && (float) ($adminDemoCatalog['crm']['yearly'] ?? 0) === 490.0,
    'tracked admin-demo CRM is 49 / 490'
);
mac_assert(!isset($adminDemoCatalog['pos']) || empty($adminDemoCatalog['pos']['enabled']), 'admin-demo catalog does not enable POS');
mac_assert(
    (float) ($prodCatalog['crm']['monthly'] ?? 0) <= 0
    && (float) ($prodCatalog['crm']['yearly'] ?? 0) <= 0
    && empty($prodCatalog['crm']['enabled']),
    'production catalog prices are unset (fail closed)'
);
mac_assert(
    (float) ($prodCatalog['crm']['monthly'] ?? 0) === 0.0
    && empty($prodCatalog['crm']['enabled']),
    'tracked production CRM remains disabled'
);

$savedPreview = getenv(ModuleAddonService::PREVIEW_FLAG_NAME);
$savedRatebEnv = getenv('RATEB_ENV');
$savedAppEnv = getenv('APP_ENV');
$savedHost = $_SERVER['HTTP_HOST'] ?? null;

mac_overlay_env(false);
mac_set_flag(null);
unset($_SERVER['HTTP_HOST']);
$fileLoaded = new ModuleAddonService();
mac_assert($fileLoaded->isPurchasable('crm') === false, 'file catalog without preview overlay is not purchasable');

mac_set_env(ModuleAddonService::PREVIEW_FLAG_NAME, '1');
mac_set_env('RATEB_ENV', 'production');
mac_set_env('APP_ENV', 'production');
$prodHost = new ModuleAddonService();
mac_assert($prodHost->isPurchasable('crm') === false, 'preview overlay refused when RATEB_ENV=production');

mac_set_env(ModuleAddonService::PREVIEW_FLAG_NAME, '1');
mac_set_env('RATEB_ENV', 'staging');
mac_set_env('APP_ENV', 'staging');
$_SERVER['HTTP_HOST'] = 'rateb.sa';
$saHost = new ModuleAddonService();
mac_assert($saHost->isPurchasable('crm') === false, 'preview overlay refused on rateb.sa host');

$_SERVER['HTTP_HOST'] = 'foo.rateb.sa';
mac_assert((new ModuleAddonService())->isPurchasable('crm') === false, 'preview overlay refused on foo.rateb.sa');

$_SERVER['HTTP_HOST'] = 'eviladmin.rateb.sa';
mac_assert((new ModuleAddonService())->isPurchasable('crm') === false, 'preview overlay refused on eviladmin.rateb.sa');

$localOverlay = is_file($root . '/config/module-addons.local.php');
$adminDemoFile = is_file($root . '/config/module-addons.admin-demo.php');
if ($adminDemoFile || $localOverlay) {
    $_SERVER['HTTP_HOST'] = 'admin.rateb.sa';
    $demoHost = new ModuleAddonService();
    mac_assert($demoHost->isPurchasable('crm') === true, 'preview overlay allowed on exact admin.rateb.sa');
    mac_assert($demoHost->isPurchasable('pos') === false, 'demo overlay does not enable other modules');
    $crm = $demoHost->catalog()['crm'];
    mac_assert((float) $crm['monthly'] === 49.0 && (float) $crm['yearly'] === 490.0, 'preview CRM prices are 49 / 490');

    $_SERVER['HTTP_HOST'] = 'localhost';
    mac_assert(
        (new ModuleAddonService())->isPurchasable('crm') === $localOverlay,
        'localhost preview overlay requires gitignored local catalog'
    );

    unset($_SERVER['HTTP_HOST']);
    mac_overlay_env(true);
    $previewSvc = new ModuleAddonService();
    mac_assert(
        $previewSvc->isPurchasable('crm') === $localOverlay,
        'CLI/local overlay without host uses gitignored catalog only'
    );
    mac_assert($previewSvc->isPurchasable('pos') === false, 'local overlay does not enable other modules');
} else {
    unset($_SERVER['HTTP_HOST']);
    ++$skipped;
    echo "SKIP: no admin-demo or local overlay catalog\n";
}

mac_overlay_env(false);
if ($savedPreview !== false && $savedPreview !== '') {
    mac_set_env(ModuleAddonService::PREVIEW_FLAG_NAME, (string) $savedPreview);
}
if ($savedRatebEnv !== false && $savedRatebEnv !== '') {
    mac_set_env('RATEB_ENV', (string) $savedRatebEnv);
}
if ($savedAppEnv !== false && $savedAppEnv !== '') {
    mac_set_env('APP_ENV', (string) $savedAppEnv);
}
if ($savedHost !== null) {
    $_SERVER['HTTP_HOST'] = $savedHost;
} else {
    unset($_SERVER['HTTP_HOST']);
}

$svcOff = new ModuleAddonService($prodCatalog);
mac_set_flag(null);
mac_assert($svcOff->isEnabled() === false, 'flag default OFF');
mac_set_flag('0');
mac_assert((new ModuleAddonService($prodCatalog))->isEnabled() === false, 'flag 0 is OFF');
mac_set_flag('false');
mac_assert((new ModuleAddonService($prodCatalog))->isEnabled() === false, 'flag false is OFF');

$priced = [
    'crm' => ['name' => 'CRM', 'monthly' => 49.0, 'yearly' => 441.0, 'enabled' => true],
    'pos' => ['name' => 'POS', 'monthly' => 29.0, 'yearly' => 261.0, 'enabled' => true],
    'hr' => ['name' => 'HR', 'monthly' => 0.0, 'yearly' => 0.0, 'enabled' => true],
    'not_a_module' => ['name' => 'X', 'monthly' => 10.0, 'yearly' => 100.0, 'enabled' => true],
];
$svc = new ModuleAddonService($priced);

mac_assert($svc->isPurchasable('crm') === true, 'priced enabled CRM is purchasable');
mac_assert($svc->isPurchasable('unknown-slug') === false, 'unknown slug rejected');
mac_assert($svc->isPurchasable('not_a_module') === false, 'unknown ERP module rejected even with price');
mac_assert($svc->isPurchasable('hr') === false, 'known module with zero price rejected');
mac_assert($svc->isPurchasable('pos') === true, 'priced POS is purchasable');

$prodSvc = new ModuleAddonService();
mac_assert($prodSvc->isPurchasable('crm') === false, 'production CRM not purchasable until prices configured');
mac_assert($prodSvc->isPurchasable('crm') === $prodSvc->isPurchasable('crm'), 'client price is irrelevant (no price argument)');
mac_assert($prodSvc->catalog() !== [], 'catalog() works while feature flag is OFF');
$pricedDisabled = ['crm' => ['name' => 'CRM', 'monthly' => 49.0, 'yearly' => 441.0, 'enabled' => false]];
mac_assert((new ModuleAddonService($pricedDisabled))->isPurchasable('crm') === false, 'known priced module rejected when not enabled for add-on commerce');

$migFiles = glob($root . '/migrations/*_company_module_addons.sql') ?: [];
mac_assert(count($migFiles) === 1, 'exactly one module-addons migration file');
if ($migFiles !== []) {
    $migSql = (string) file_get_contents($migFiles[0]);
    mac_assert(str_contains($migSql, 'CREATE TABLE IF NOT EXISTS rateb_company_module_addons'), 'migration creates ledger table');
    mac_assert(str_contains($migSql, 'preexisting_grant'), 'migration includes preexisting_grant');
    mac_assert(!str_contains($migSql, 'UNIQUE KEY') || str_contains($migSql, 'uq_module_addons_invoice'), 'nullable unique invoice_id present');
}

mac_assert($svc->decodeModulesList(null) === [], 'NULL JSON → empty list');
mac_assert($svc->decodeModulesList('') === [], 'empty string JSON → empty list');
mac_assert($svc->decodeModulesList('[]') === [], '[] JSON → empty list');
mac_assert($svc->decodeModulesList([]) === [], '[] array → empty list');
mac_assert($svc->decodeModulesList('["pos","hr","pos"]') === ['pos', 'hr'], 'duplicates normalized, order preserved');
mac_assert($svc->decodeModulesList('{"pos":true}') === [], 'associative JSON is not treated as a slug list');

$starterPack = PlanLimitService::modulesForSlug('starter');
mac_assert($starterPack !== [] && !in_array('crm', $starterPack, true), 'starter pack is non-empty and excludes CRM');
$emptyExplicit = $svc->decodeModulesList('[]');
mac_assert($emptyExplicit === [], 'empty JSON is not "no modules" — caller must use plan fallback');
$formulaSnapshot = $emptyExplicit !== [] ? $emptyExplicit : $starterPack;
$formulaActivated = $svc->decodeModulesList(json_encode(
    array_values(array_unique(array_merge($formulaSnapshot, ['crm', 'dashboard', 'notifications']))),
    JSON_UNESCAPED_UNICODE
));
mac_assert(in_array('procurement', $formulaActivated, true), 'empty-JSON formula keeps plan procurement');
mac_assert(in_array('crm', $formulaActivated, true), 'empty-JSON formula adds purchased CRM');
mac_assert(in_array('dashboard', $formulaActivated, true) && in_array('notifications', $formulaActivated, true), 'empty-JSON formula includes dashboard+notifications');
mac_assert(!in_array('pos', $formulaActivated, true) || in_array('pos', $starterPack, true), 'empty-JSON formula does not invent POS');

mac_set_flag(null);
$disabled = (new ModuleAddonService($priced))->activateFromPaidInvoice(1);
mac_assert(($disabled['disabled'] ?? false) === true && ($disabled['code'] ?? '') === 'disabled', 'flag OFF → activate disabled');
$expOff = (new ModuleAddonService($priced))->expireAddon(1);
mac_assert(($expOff['disabled'] ?? false) === true, 'flag OFF → expire disabled');
mac_assert((new ModuleAddonService($priced))->expireDueAddons(10) === 0, 'flag OFF → expireDueAddons is 0');

function mac_table_ready(): bool
{
    try {
        $pdo = Database::connection();
        $stmt = $pdo->query("SHOW TABLES LIKE 'rateb_company_module_addons'");
        if ($stmt && $stmt->fetch()) {
            return true;
        }
    } catch (Throwable $e) {
        return false;
    }
    try {
        $pdo = Database::connection();
        $pdo->query('SELECT 1 FROM rateb_company_module_addons LIMIT 0');

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

$dbReady = mac_table_ready();
if (!$dbReady) {
    ++$skipped;
    echo "SKIP: ledger table rateb_company_module_addons not present (apply migration 262 locally to run DB cases)\n";
} else {
    $pdo = Database::connection();
    $pdo->beginTransaction();
    try {
        mac_set_flag('1');
        $svcOn = new ModuleAddonService($priced);
        mac_assert($svcOn->isEnabled() === true, 'flag 1 is ON');

        $stamp = substr(bin2hex(random_bytes(6)), 0, 12);
        $starterRow = (new Plan())->queryOne("SELECT id FROM rateb_plans WHERE slug = 'starter' LIMIT 1");
        $commerceRow = (new Plan())->queryOne("SELECT id FROM rateb_plans WHERE slug = 'commerce' LIMIT 1");
        $starterId = (int) ($starterRow['id'] ?? 0);
        $commerceId = (int) ($commerceRow['id'] ?? 0);

        $insCompany = static function (PDO $pdo, int $planId, string $stamp, string $tag, $modules) {
            $email = 'mac-' . $tag . '-' . $stamp . '@example.test';
            $slug = 'mac-' . $tag . '-' . $stamp;
            $pdo->prepare(
                'INSERT INTO rateb_companies (name, slug, email, status, plan_id, modules)
                 VALUES (:n, :slug, :em, \'active\', :pid, :mod)'
            )->execute([
                'n' => 'MAC ' . $tag,
                'slug' => $slug,
                'em' => $email,
                'pid' => $planId,
                'mod' => $modules,
            ]);
            $id = (int) $pdo->lastInsertId();
            if ($id < 1) {
                $found = (new Company())->queryOne('SELECT id FROM rateb_companies WHERE slug = :s LIMIT 1', ['s' => $slug]);
                $id = (int) ($found['id'] ?? 0);
            }
            PlanLimitService::forgetCompanyLimits($id);
            if (function_exists('rateb_ops_company_request_state')) {
                $state = &rateb_ops_company_request_state();
                unset($state['rows'][$id], $state['exists'][$id]);
            }

            return $id;
        };

        $insInvoice = static function (PDO $pdo, int $companyId, string $stamp, string $tag, string $payStatus = 'paid'): int {
            $no = 'MAC-' . $tag . '-' . $stamp;
            $pdo->prepare(
                "INSERT INTO rateb_invoices
                    (company_id, invoice_no, invoice_type, amount, tax_amount, total_amount, currency,
                     status, payment_status, issued_at)
                 VALUES
                    (:cid, :no, 'tax', 49, 0, 49, 'SAR', 'sent', :ps, :iss)"
            )->execute(['cid' => $companyId, 'no' => $no, 'ps' => $payStatus, 'iss' => date('Y-m-d')]);
            $id = (int) $pdo->lastInsertId();
            if ($id < 1) {
                $found = $pdo->prepare('SELECT id FROM rateb_invoices WHERE invoice_no = :n LIMIT 1');
                $found->execute(['n' => $no]);
                $id = (int) ($found->fetchColumn() ?: 0);
            }

            return $id;
        };

        $insAddon = static function (
            PDO $pdo,
            int $companyId,
            string $slug,
            int $invoiceId,
            string $status = 'pending',
            int $pre = 0,
            ?string $ends = null
        ): int {
            $pdo->prepare(
                "INSERT INTO rateb_company_module_addons
                    (company_id, module_slug, status, starts_at, ends_at, billing_cycle, invoice_id, preexisting_grant, source)
                 VALUES
                    (:cid, :slug, :st, :starts, :ends, 'monthly', :iid, :pg, 'self_serve')"
            )->execute([
                'cid' => $companyId,
                'slug' => $slug,
                'st' => $status,
                'starts' => date('Y-m-d'),
                'ends' => $ends,
                'iid' => $invoiceId,
                'pg' => $pre,
            ]);

            $id = (int) $pdo->lastInsertId();
            if ($id < 1) {
                $found = $pdo->prepare(
                    'SELECT id FROM rateb_company_module_addons WHERE invoice_id = :i LIMIT 1'
                );
                $found->execute(['i' => $invoiceId]);
                $id = (int) ($found->fetchColumn() ?: 0);
            }

            return $id;
        };

        if ($starterId < 1) {
            echo "SKIP: starter plan row missing — DB cases need rateb_plans.slug=starter\n";
            ++$skipped;
        } else {
        $cidEmpty = $insCompany($pdo, $starterId, $stamp, 'empty', null);
        mac_assert($svcOn->currentJson($cidEmpty) === [], 'NULL JSON currentJson is empty (plan fallback)');
        $planList = $svcOn->planModules($cidEmpty);
        mac_assert($planList !== [] && in_array('procurement', $planList, true), 'planModules reads starter pack');
        mac_assert(!in_array('crm', $planList, true), 'starter pack does not include CRM');
        $mat = $svcOn->materializeCurrentModules($cidEmpty);
        foreach ($planList as $planMod) {
            mac_assert(in_array($planMod, $mat, true), 'empty JSON materialize includes plan ' . $planMod);
        }
        mac_assert(in_array('dashboard', $mat, true) && in_array('notifications', $mat, true), 'materialize implies dashboard+notifications');

        $cidEmptyArr = $insCompany($pdo, $starterId, $stamp, 'arr', '[]');
        mac_assert($svcOn->currentJson($cidEmptyArr) === [], '[] JSON currentJson is empty');

        $cidOverride = $insCompany($pdo, $starterId, $stamp, 'ovr', json_encode(['crm'], JSON_UNESCAPED_UNICODE));
        mac_assert($svcOn->currentJson($cidOverride) === ['crm'], 'non-empty JSON is full override snapshot');
        $matOvr = $svcOn->materializeCurrentModules($cidOverride);
        mac_assert(in_array('crm', $matOvr, true), 'override materialize keeps CRM');
        mac_assert(!in_array('procurement', $svcOn->currentJson($cidOverride), true), 'override JSON does not list plan procurement');

        // Example 5 — empty JSON purchase CRM (must keep plan modules)
        $inv1 = $insInvoice($pdo, $cidEmpty, $stamp, 'e5');
        $insAddon($pdo, $cidEmpty, 'crm', $inv1, 'pending');
        $act = $svcOn->activateFromPaidInvoice($inv1);
        mac_assert(($act['ok'] ?? false) === true, 'activate empty-JSON CRM ok');
        $mods = $svcOn->currentJson($cidEmpty);
        foreach ($planList as $planMod) {
            mac_assert(in_array($planMod, $mods, true), 'activation keeps plan module ' . $planMod);
        }
        mac_assert(in_array('crm', $mods, true), 'activation adds crm');
        mac_assert(in_array('dashboard', $mods, true) && in_array('notifications', $mods, true), 'activation adds dashboard+notifications');
        mac_assert((int) ($act['preexisting_grant'] ?? -1) === 0, 'CRM absent → preexisting_grant=0');

        $act2 = $svcOn->activateFromPaidInvoice($inv1);
        mac_assert(($act2['ok'] ?? false) === true, 'second activate is ok');
        $stmtCnt = $pdo->prepare('SELECT COUNT(*) FROM rateb_company_module_addons WHERE invoice_id = :i');
        $stmtCnt->execute(['i' => $inv1]);
        mac_assert((int) $stmtCnt->fetchColumn() === 1, 'duplicate invoice does not create another ledger row');
        $crmCount = 0;
        foreach ($svcOn->currentJson($cidEmpty) as $m) {
            if ($m === 'crm') {
                $crmCount++;
            }
        }
        mac_assert($crmCount === 1, 'duplicate activate does not duplicate module slug');

        $exJson = json_encode(['procurement', 'inventory', 'crm'], JSON_UNESCAPED_UNICODE);

        // preexisting grant
        $cidGrant = $insCompany($pdo, $starterId, $stamp, 'pre', $exJson);
        $invG = $insInvoice($pdo, $cidGrant, $stamp, 'pre');
        $insAddon($pdo, $cidGrant, 'crm', $invG, 'pending');
        $actG = $svcOn->activateFromPaidInvoice($invG);
        mac_assert((int) ($actG['preexisting_grant'] ?? 0) === 1, 'CRM already present → preexisting_grant=1');

        // Example 1 — CRM expires, not in plan, grant 0
        $cidEx1 = $insCompany($pdo, $starterId, $stamp, 'ex1', $exJson);
        $invEx1 = $insInvoice($pdo, $cidEx1, $stamp, 'ex1');
        $addEx1 = $insAddon($pdo, $cidEx1, 'crm', $invEx1, 'active', 0);
        $ex1 = $svcOn->expireAddon($addEx1);
        mac_assert(($ex1['ok'] ?? false) === true, 'example1 expire ok');
        $jsonEx1 = $svcOn->currentJson($cidEx1);
        mac_assert(in_array('procurement', $jsonEx1, true) && in_array('inventory', $jsonEx1, true), 'example1 keeps plan modules');
        mac_assert(!in_array('crm', $jsonEx1, true), 'example1 removes crm');

        // Example 2 — POS add-on expires, POS in commerce plan
        if ($commerceId > 0) {
            $cidEx2 = $insCompany($pdo, $commerceId, $stamp, 'ex2', json_encode(['pos', 'crm'], JSON_UNESCAPED_UNICODE));
            $invEx2 = $insInvoice($pdo, $cidEx2, $stamp, 'ex2');
            $addEx2 = $insAddon($pdo, $cidEx2, 'pos', $invEx2, 'active', 0);
            $svcOn->expireAddon($addEx2);
            $jsonEx2 = $svcOn->currentJson($cidEx2);
            mac_assert(in_array('pos', $jsonEx2, true), 'example2 POS remains (plan)');
            mac_assert(in_array('crm', $jsonEx2, true), 'example2 CRM untouched');
        } else {
            echo "SKIP: commerce plan row missing — POS-in-plan expire example\n";
            ++$skipped;
        }

        // Example 3 — two active CRM, one expires
        $cidEx3 = $insCompany($pdo, $starterId, $stamp, 'ex3', $exJson);
        $invEx3a = $insInvoice($pdo, $cidEx3, $stamp, 'ex3a');
        $invEx3b = $insInvoice($pdo, $cidEx3, $stamp, 'ex3b');
        $addEx3a = $insAddon($pdo, $cidEx3, 'crm', $invEx3a, 'active', 0);
        $insAddon($pdo, $cidEx3, 'crm', $invEx3b, 'active', 0);
        $svcOn->expireAddon($addEx3a);
        mac_assert(in_array('crm', $svcOn->currentJson($cidEx3), true), 'example3 CRM remains while other add-on active');

        // Example 4 — preexisting_grant=1
        $cidEx4 = $insCompany($pdo, $starterId, $stamp, 'ex4', $exJson);
        $invEx4 = $insInvoice($pdo, $cidEx4, $stamp, 'ex4');
        $addEx4 = $insAddon($pdo, $cidEx4, 'crm', $invEx4, 'active', 1);
        $svcOn->expireAddon($addEx4);
        mac_assert(in_array('crm', $svcOn->currentJson($cidEx4), true), 'example4 CRM remains (preexisting_grant)');

        // Example 6 — empty JSON, expire must not write empty override if still empty
        $cidEx6 = $insCompany($pdo, $starterId, $stamp, 'ex6', null);
        $invEx6 = $insInvoice($pdo, $cidEx6, $stamp, 'ex6');
        $addEx6 = $insAddon($pdo, $cidEx6, 'crm', $invEx6, 'active', 0);
        $svcOn->expireAddon($addEx6);
        $raw6 = (new Company())->queryOne('SELECT modules FROM rateb_companies WHERE id = :id', ['id' => $cidEx6]);
        $raw6v = $raw6['modules'] ?? null;
        mac_assert($raw6v === null || $raw6v === '' || $raw6v === '[]', 'example6 leaves empty JSON as plan fallback');

        // Admin uncheck: paid CRM, JSON without CRM, expire must not re-add
        $cidAdm = $insCompany($pdo, $starterId, $stamp, 'adm', json_encode(['procurement', 'inventory'], JSON_UNESCAPED_UNICODE));
        $invAdm = $insInvoice($pdo, $cidAdm, $stamp, 'adm');
        $addAdm = $insAddon($pdo, $cidAdm, 'crm', $invAdm, 'active', 0);
        $svcOn->expireAddon($addAdm);
        mac_assert(!in_array('crm', $svcOn->currentJson($cidAdm), true), 'admin-uncheck JSON is not rebuilt with CRM on expire');

        // Unpaid invoice does not activate
        $cidPay = $insCompany($pdo, $starterId, $stamp, 'pay', '[]');
        $invUnpaid = $insInvoice($pdo, $cidPay, $stamp, 'unp', 'unpaid');
        $insAddon($pdo, $cidPay, 'crm', $invUnpaid, 'pending');
        $unpaid = $svcOn->activateFromPaidInvoice($invUnpaid);
        mac_assert(($unpaid['ok'] ?? true) === false && ($unpaid['code'] ?? '') === 'invoice_not_paid', 'unpaid invoice not activated');
        mac_assert($svcOn->currentJson($cidPay) === [], 'unpaid activate does not write JSON');

        // Flag OFF does not mutate JSON
        $cidFlag = $insCompany($pdo, $starterId, $stamp, 'flg', $exJson);
        $invFlag = $insInvoice($pdo, $cidFlag, $stamp, 'flg');
        $addFlag = $insAddon($pdo, $cidFlag, 'crm', $invFlag, 'active', 0);
        mac_set_flag('0');
        $svcFlagOff = new ModuleAddonService($priced);
        $svcFlagOff->activateFromPaidInvoice($invFlag);
        $svcFlagOff->expireAddon($addFlag);
        mac_assert($svcFlagOff->currentJson($cidFlag) === ['procurement', 'inventory', 'crm'], 'flag OFF leaves company.modules unchanged');
        mac_set_flag('1');

        // expireDueAddons — assert this fixture row, not a global count
        $cidDue = $insCompany($pdo, $starterId, $stamp, 'due', $exJson);
        $invDue = $insInvoice($pdo, $cidDue, $stamp, 'due');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $addDue = $insAddon($pdo, $cidDue, 'crm', $invDue, 'active', 0, $yesterday);
        $svcOn->expireDueAddons(500);
        $dueSt = $pdo->prepare('SELECT status FROM rateb_company_module_addons WHERE id = :id');
        $dueSt->execute(['id' => $addDue]);
        mac_assert((string) $dueSt->fetchColumn() === 'expired', 'expireDueAddons expired our due row');
        mac_assert(!in_array('crm', $svcOn->currentJson($cidDue), true), 'due CRM removed from JSON');
        }
    } catch (Throwable $e) {
        echo 'FAIL: db fixture exception: ' . $e->getMessage() . "\n";
        ++$failed;
    }
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mac_set_flag(null);
}

echo "\nModule addon commerce tests: {$passed} passed, {$failed} failed, {$skipped} skipped\n";
exit($failed > 0 ? 1 : 0);
