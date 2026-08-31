<?php
declare(strict_types=1);

/**
 * Phase 3 — paid webhook / status-page module activation (no second entitlement engine).
 * Run: php rateb-erp/tests/ModuleAddonActivationTest.php
 *
 * DB cases run inside a transaction and roll back. Skipped if MySQL / ledger is unavailable.
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
use Rateb\App\Services\ModuleAddonCheckoutService;
use Rateb\App\Services\ModuleAddonService;
use Rateb\App\Services\PlanLimitService;

$passed = 0;
$failed = 0;
$skipped = 0;

function mac3_assert(bool $cond, string $label): void
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

function mac3_flag(?string $value): void
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

$priced = [
    'crm' => ['name' => 'CRM', 'monthly' => 49.0, 'yearly' => 441.0, 'enabled' => true],
];
$addons = new ModuleAddonService($priced);

mac3_flag(null);
mac3_assert($addons->isEnabled() === false, 'feature flag OFF');
$off = $addons->activateFromPaidInvoice(1);
mac3_assert(($off['disabled'] ?? false) === true, 'flag OFF produces no activation');
mac3_assert(($off['code'] ?? '') === 'disabled', 'flag OFF returns disabled');

mac3_flag('1');
$on = new ModuleAddonService($priced);

$parsed = $on->parseAddonPoNumber('ADDON:crm:monthly');
mac3_assert(is_array($parsed) && $parsed['slug'] === 'crm' && $parsed['cycle'] === 'monthly', 'Phase 2 ADDON po_number parsed');
mac3_assert($on->parseAddonPoNumber('INV-2026-00001') === null, 'customer invoice po_number is not an add-on');
mac3_assert($on->isAddonInvoice(['po_number' => 'ADDON:crm:yearly']) === true, 'add-on invoice detected from po_number');
mac3_assert($on->isAddonInvoice(['po_number' => 'PO-99']) === false, 'normal customer invoice does not activate add-on');

$unpaid = $on->activateFromPaidInvoice(0);
mac3_assert(($unpaid['code'] ?? '') === 'invoice_not_found', 'missing invoice id does not activate');

$wh = (string) file_get_contents($root . '/app/controllers/Api/PaymentWebhookController.php');
mac3_assert(str_contains($wh, 'handleMoyasar'), 'webhook still calls existing handleMoyasar');
mac3_assert(str_contains($wh, 'ModuleAddonActivationHook'), 'add-on hook after payment webhook');
mac3_assert(str_contains($wh, 'catch (Throwable $activationError)'), 'activation failure is caught');
mac3_assert(str_contains($wh, 'module_addon_webhook_activation_failed'), 'activation failure is logged');
mac3_assert(
    strpos($wh, 'http_response_code((int) ($result[\'http\'] ?? 200))') > strpos($wh, 'catch (Throwable $activationError)'),
    'webhook HTTP status is emitted after activation try/catch (existing status preserved)'
);
mac3_assert(!str_contains($wh, '$_POST[\'company_id\']'), 'POST company_id cannot redirect activation');
mac3_assert(!str_contains($wh, '$_GET[\'company_id\']'), 'GET company_id is not used');
mac3_assert(
    strpos($wh, '(new PaymentWebhookService())->handleMoyasar') < strpos($wh, '(new ModuleAddonActivationHook())'),
    'add-on hook runs after existing handleMoyasar'
);

$hook = (string) file_get_contents($root . '/app/services/ModuleAddonActivationHook.php');
mac3_assert(str_contains($hook, 'activateFromPaidInvoice($invoiceId, $txId'), 'hook passes invoice + local transaction id');
mac3_assert(str_contains($hook, "findByExternalId('moyasar'"), 'transaction resolved from existing payment tables');
mac3_assert(str_contains($hook, "txStatus !== 'completed'"), 'webhook activation requires completed local transaction');
mac3_assert(!str_contains($hook, 'updateModules'), 'hook does not write company.modules directly');
mac3_assert(!str_contains($hook, 'company_id'), 'hook does not take company_id from the webhook payload');

$svc = (string) file_get_contents($root . '/app/services/ModuleAddonService.php');
mac3_assert(str_contains($svc, 'ensurePendingLedgerFromPaidInvoice'), 'paid add-on invoice creates ledger if Phase 2 omitted it');
mac3_assert(str_contains($svc, 'materializeCurrentModules'), 'empty JSON uses plan materialization');
mac3_assert(str_contains($svc, 'preexisting_grant'), 'preexisting_grant is recorded');
mac3_assert(str_contains($svc, 'payment_transaction_id'), 'payment_transaction_id is stored');
mac3_assert(str_contains($svc, "\$st === 'cancelled'"), 'cancelled invoice does not activate');
mac3_assert(str_contains($svc, 'already_active'), 'second activation is idempotent');
mac3_assert(str_contains($svc, 'invoice_company_mismatch'), 'invoice company is authoritative');
mac3_assert(str_contains($svc, "'code' => 'ignored'"), 'non-add-on invoices are ignored');
mac3_assert(str_contains($svc, 'pushModulesToLinkedAgency'), 'activation/expiration can push linked agency after commit');
mac3_assert(str_contains($svc, 'module_addon_agency_push_failed'), 'agency push failure is logged and does not roll back');
mac3_assert(!str_contains($svc, 'new CronService') && !str_contains($svc, 'erp-cron.php'), 'no cron coupling');

$chk = (string) file_get_contents($root . '/app/services/ModuleAddonCheckoutService.php');
$ctrl = (string) file_get_contents($root . '/app/controllers/Company/ModuleAddonCheckoutController.php');
mac3_assert(str_contains($chk, 'retryPaidActivation'), 'status-page activation retry exists');
mac3_assert(str_contains($chk, 'invoice_not_paid'), 'status retry requires paid invoice');
mac3_assert(str_contains($chk, 'forbidden'), 'status retry rejects other-company invoice');
mac3_assert(str_contains($chk, 'findLatestAddonInvoice($sessionCompanyId'), 'status retry loads invoice by session company');
mac3_assert(str_contains($ctrl, "SessionManager::get('rateb_company_id')"), 'status uses session company');
mac3_assert(str_contains($ctrl, "unset(\$_GET['company_id'], \$_GET['invoice_id']"), 'status ignores client invoice/company ids');
mac3_assert(str_contains($ctrl, 'retryPaidActivation'), 'controller retries activation on status');
mac3_assert(str_contains($ctrl, 'module_addon_status_retry_failed'), 'status retry failure is logged');

$pay = (string) file_get_contents($root . '/app/Payment/PaymentService.php');
$whs = (string) file_get_contents($root . '/app/Payment/PaymentWebhookService.php');
$moy = (string) file_get_contents($root . '/app/Payment/Gateways/MoyasarGateway.php');
mac3_assert(str_contains($pay, 'function initiate') && str_contains($pay, 'function finalizeSuccess'), 'existing customer payment flow remains in PaymentService');
mac3_assert(str_contains($whs, "'http' => 200"), 'existing webhook HTTP 200 behavior present');
mac3_assert(!str_contains($moy, 'ModuleAddon'), 'MoyasarGateway has no add-on coupling');
mac3_assert(!str_contains($pay, 'ModuleAddon'), 'PaymentService has no add-on coupling');
mac3_assert(!str_contains($whs, 'ModuleAddon'), 'PaymentWebhookService has no add-on coupling');

$frozen = [
    'app/Core/Middleware/Middleware.php',
    'app/services/PlanLimitService.php',
    'app/Payment/PaymentService.php',
    'app/Payment/PaymentWebhookService.php',
    'app/Payment/Gateways/MoyasarGateway.php',
    'app/services/CronService.php',
    'bin/erp-cron.php',
    'routes/manifest.php',
    'views/partials/sidebar-nav.php',
    'views/partials/sidebar-hr-nav.php',
];
foreach ($frozen as $rel) {
    mac3_assert(is_file($root . '/' . $rel), 'frozen present ' . $rel);
}

$quote = (new ModuleAddonCheckoutService($on))->quote('crm', 'monthly');
mac3_assert(is_array($quote), 'checkout quote still works');
$fields = (new ModuleAddonCheckoutService($on))->invoiceFieldsFromQuote($quote, 9);
mac3_assert((int) $fields['company_id'] === 9, 'invoice company remains session-owned');
mac3_assert((string) ($fields['po_number'] ?? '') === 'ADDON:crm:monthly', 'Phase 2 add-on marker is ADDON po_number');

function mac3_table_ready(): bool
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
        Database::connection()->query('SELECT 1 FROM rateb_company_module_addons LIMIT 0');

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

$dbReady = mac3_table_ready();
if (!$dbReady) {
    ++$skipped;
    echo "SKIP: MySQL / ledger table unavailable — DB activation cases not faked\n";
} else {
    $pdo = Database::connection();
    $pdo->beginTransaction();
    try {
        mac3_flag('1');
        $svcOn = new ModuleAddonService($priced);
        $checkout = new ModuleAddonCheckoutService($svcOn);
        $stamp = substr(bin2hex(random_bytes(6)), 0, 12);
        $starterRow = (new Plan())->queryOne("SELECT id FROM rateb_plans WHERE slug = 'starter' LIMIT 1");
        $starterId = (int) ($starterRow['id'] ?? 0);
        if ($starterId < 1) {
            echo "SKIP: starter plan row missing — DB activation cases need rateb_plans.slug=starter\n";
            ++$skipped;
        } else {
            $insCompany = static function (PDO $pdo, int $planId, string $stamp, string $tag, $modules): int {
                $email = 'mac3-' . $tag . '-' . $stamp . '@example.test';
                $slug = 'mac3-' . $tag . '-' . $stamp;
                $pdo->prepare(
                    'INSERT INTO rateb_companies (name, slug, email, status, plan_id, modules)
                     VALUES (:n, :slug, :em, \'active\', :pid, :mod)'
                )->execute([
                    'n' => 'MAC3 ' . $tag,
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

                return $id;
            };

            $insInvoice = static function (
                PDO $pdo,
                int $companyId,
                string $stamp,
                string $tag,
                string $payStatus = 'paid',
                string $status = 'sent',
                string $poNumber = 'ADDON:crm:monthly'
            ): int {
                $no = 'MAC3-' . $tag . '-' . $stamp;
                $pdo->prepare(
                    "INSERT INTO rateb_invoices
                        (company_id, invoice_no, invoice_type, amount, tax_amount, total_amount, currency,
                         status, payment_status, issued_at, po_number)
                     VALUES
                        (:cid, :no, 'tax', 49, 0, 49, 'SAR', :st, :ps, :iss, :po)"
                )->execute([
                    'cid' => $companyId,
                    'no' => $no,
                    'st' => $status,
                    'ps' => $payStatus,
                    'iss' => date('Y-m-d'),
                    'po' => $poNumber,
                ]);
                $id = (int) $pdo->lastInsertId();
                if ($id < 1) {
                    $found = $pdo->prepare('SELECT id FROM rateb_invoices WHERE invoice_no = :n LIMIT 1');
                    $found->execute(['n' => $no]);
                    $id = (int) ($found->fetchColumn() ?: 0);
                }

                return $id;
            };

            $insTx = static function (PDO $pdo, int $companyId, int $invoiceId, string $stamp, string $tag): int {
                $key = 'mac3-' . $tag . '-' . $stamp;
                $tok = 'tok-' . $tag . '-' . $stamp;
                $pdo->prepare(
                    "INSERT INTO rateb_payment_transactions
                        (company_id, invoice_id, gateway_slug, external_id, idempotency_key, amount, currency,
                         status, callback_token, initiated_at, completed_at)
                     VALUES
                        (:cid, :iid, 'moyasar', :ext, :idem, 49, 'SAR', 'completed', :tok, :ts, :ts)"
                )->execute([
                    'cid' => $companyId,
                    'iid' => $invoiceId,
                    'ext' => 'pay_' . $key,
                    'idem' => $key,
                    'tok' => $tok,
                    'ts' => date('Y-m-d H:i:s'),
                ]);
                $id = (int) $pdo->lastInsertId();
                if ($id < 1) {
                    $found = $pdo->prepare('SELECT id FROM rateb_payment_transactions WHERE idempotency_key = :k LIMIT 1');
                    $found->execute(['k' => $key]);
                    $id = (int) ($found->fetchColumn() ?: 0);
                }

                return $id;
            };

            $cidEmpty = $insCompany($pdo, $starterId, $stamp, 'empty', null);
            $planList = $svcOn->planModules($cidEmpty);
            mac3_assert($planList !== [] && in_array('procurement', $planList, true), 'starter plan modules available');

            $invPaid = $insInvoice($pdo, $cidEmpty, $stamp, 'paid');
            $txPaid = $insTx($pdo, $cidEmpty, $invPaid, $stamp, 'paid');
            $act = $svcOn->activateFromPaidInvoice($invPaid, $txPaid);
            mac3_assert(($act['ok'] ?? false) === true && ($act['code'] ?? '') === 'activated', 'paid add-on invoice activates');
            $mods = $svcOn->currentJson($cidEmpty);
            foreach ($planList as $planMod) {
                mac3_assert(in_array($planMod, $mods, true), 'empty JSON preserves plan module ' . $planMod);
            }
            mac3_assert(in_array('crm', $mods, true), 'purchased module is added');
            mac3_assert(in_array('dashboard', $mods, true) && in_array('notifications', $mods, true), 'dashboard and notifications remain');
            mac3_assert((int) ($act['preexisting_grant'] ?? -1) === 0, 'preexisting_grant=0 when CRM was absent');
            $led = $pdo->prepare('SELECT payment_transaction_id, status, company_id FROM rateb_company_module_addons WHERE invoice_id = :i LIMIT 1');
            $led->execute(['i' => $invPaid]);
            $ledRow = $led->fetch(PDO::FETCH_ASSOC) ?: [];
            mac3_assert((int) ($ledRow['payment_transaction_id'] ?? 0) === $txPaid, 'payment_transaction_id is local transaction PK');
            mac3_assert((string) ($ledRow['status'] ?? '') === 'active', 'ledger status is active');
            mac3_assert((int) ($ledRow['company_id'] ?? 0) === $cidEmpty, 'invoice company is authoritative');

            $act2 = $svcOn->activateFromPaidInvoice($invPaid, $txPaid);
            mac3_assert(($act2['ok'] ?? false) === true, 'duplicate webhook is idempotent');
            mac3_assert(($act2['code'] ?? '') === 'already_active', 'second activation is no-op');
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM rateb_company_module_addons WHERE company_id = :c AND module_slug = 'crm' AND status = 'active'");
            $cnt->execute(['c' => $cidEmpty]);
            mac3_assert((int) $cnt->fetchColumn() === 1, 'duplicate active addon does not appear');

            $cidUnpaid = $insCompany($pdo, $starterId, $stamp, 'unp', '[]');
            $invUnpaid = $insInvoice($pdo, $cidUnpaid, $stamp, 'unp', 'unpaid');
            $unp = $svcOn->activateFromPaidInvoice($invUnpaid);
            mac3_assert(($unp['ok'] ?? true) === false && ($unp['code'] ?? '') === 'invoice_not_paid', 'unpaid add-on does not activate');
            mac3_assert($svcOn->currentJson($cidUnpaid) === [], 'unpaid add-on does not write JSON');

            $cidCan = $insCompany($pdo, $starterId, $stamp, 'can', '[]');
            $invCan = $insInvoice($pdo, $cidCan, $stamp, 'can', 'unpaid', 'cancelled');
            $can = $svcOn->activateFromPaidInvoice($invCan);
            mac3_assert(($can['ok'] ?? true) === false, 'cancelled invoice does not activate');
            mac3_assert($svcOn->currentJson($cidCan) === [], 'cancelled invoice does not write JSON');

            $cidCust = $insCompany($pdo, $starterId, $stamp, 'cust', '[]');
            $invCust = $insInvoice($pdo, $cidCust, $stamp, 'cust', 'paid', 'sent', 'INV-CUST-1');
            $cust = $svcOn->activateFromPaidInvoice($invCust);
            mac3_assert(($cust['ok'] ?? false) === true && ($cust['code'] ?? '') === 'ignored', 'normal customer invoice does not activate add-on');
            mac3_assert($svcOn->currentJson($cidCust) === [], 'customer payment does not write company.modules');
            $custLed = $pdo->prepare('SELECT COUNT(*) FROM rateb_company_module_addons WHERE invoice_id = :i');
            $custLed->execute(['i' => $invCust]);
            mac3_assert((int) $custLed->fetchColumn() === 0, 'customer invoice creates no add-on ledger row');

            $exJson = json_encode(['procurement', 'inventory'], JSON_UNESCAPED_UNICODE);
            $cidExp = $insCompany($pdo, $starterId, $stamp, 'exp', $exJson);
            $invExp = $insInvoice($pdo, $cidExp, $stamp, 'exp');
            $actExp = $svcOn->activateFromPaidInvoice($invExp);
            $modsExp = $svcOn->currentJson($cidExp);
            mac3_assert(in_array('procurement', $modsExp, true) && in_array('inventory', $modsExp, true), 'explicit JSON preserves existing modules');
            mac3_assert(in_array('crm', $modsExp, true), 'purchased module added to explicit JSON');
            mac3_assert(in_array('dashboard', $modsExp, true) && in_array('notifications', $modsExp, true), 'explicit JSON still includes dashboard+notifications');
            mac3_assert((int) ($actExp['preexisting_grant'] ?? -1) === 0, 'preexisting_grant=0 when CRM absent from explicit JSON');

            $grantJson = json_encode(['procurement', 'inventory', 'crm'], JSON_UNESCAPED_UNICODE);
            $cidGrant = $insCompany($pdo, $starterId, $stamp, 'pre', $grantJson);
            $invGrant = $insInvoice($pdo, $cidGrant, $stamp, 'pre');
            $actGrant = $svcOn->activateFromPaidInvoice($invGrant);
            mac3_assert((int) ($actGrant['preexisting_grant'] ?? 0) === 1, 'preexisting_grant=1 when CRM already present');

            $cidA = $insCompany($pdo, $starterId, $stamp, 'ida', '[]');
            $cidB = $insCompany($pdo, $starterId, $stamp, 'idb', '[]');
            $invB = $insInvoice($pdo, $cidB, $stamp, 'idb');
            $retryOther = $checkout->retryPaidActivation($cidA, 'crm');
            mac3_assert(($retryOther['ok'] ?? true) === false, 'status-page cannot access another company');
            mac3_assert($svcOn->currentJson($cidB) === [], 'other-company retry does not activate');

            $cidRetry = $insCompany($pdo, $starterId, $stamp, 'rty', null);
            $invRetry = $insInvoice($pdo, $cidRetry, $stamp, 'rty');
            $txRetry = $insTx($pdo, $cidRetry, $invRetry, $stamp, 'rty');
            $retry = $checkout->retryPaidActivation($cidRetry, 'crm');
            mac3_assert(($retry['ok'] ?? false) === true, 'status-page activation retry works');
            mac3_assert(in_array('crm', $svcOn->currentJson($cidRetry), true), 'status retry added purchased module');
            $retryLed = $pdo->prepare('SELECT payment_transaction_id FROM rateb_company_module_addons WHERE invoice_id = :i LIMIT 1');
            $retryLed->execute(['i' => $invRetry]);
            mac3_assert((int) ($retryLed->fetchColumn() ?: 0) === $txRetry, 'status retry records local payment_transaction_id');

            $cidFlag = $insCompany($pdo, $starterId, $stamp, 'flg', '[]');
            $invFlag = $insInvoice($pdo, $cidFlag, $stamp, 'flg');
            mac3_flag('0');
            $svcOff = new ModuleAddonService($priced);
            $offAct = $svcOff->activateFromPaidInvoice($invFlag);
            mac3_assert(!empty($offAct['disabled']), 'feature flag OFF produces no activation (DB)');
            mac3_assert($svcOff->currentJson($cidFlag) === [], 'flag OFF writes no company.modules');
            mac3_flag('1');

            $cidMis = $insCompany($pdo, $starterId, $stamp, 'mis', '[]');
            $cidMis2 = $insCompany($pdo, $starterId, $stamp, 'mi2', '[]');
            $invMis = $insInvoice($pdo, $cidMis, $stamp, 'mis');
            $pdo->prepare(
                "INSERT INTO rateb_company_module_addons
                    (company_id, module_slug, status, starts_at, ends_at, billing_cycle, invoice_id, preexisting_grant, source)
                 VALUES
                    (:cid, 'crm', 'pending', :starts, :ends, 'monthly', :iid, 0, 'self_serve')"
            )->execute([
                'cid' => $cidMis2,
                'starts' => date('Y-m-d'),
                'ends' => date('Y-m-d', strtotime('+1 month')),
                'iid' => $invMis,
            ]);
            $mis = $svcOn->activateFromPaidInvoice($invMis);
            mac3_assert(($mis['code'] ?? '') === 'invoice_company_mismatch', 'ledger/invoice company mismatch is rejected');
            mac3_assert($svcOn->currentJson($cidMis) === [] && $svcOn->currentJson($cidMis2) === [], 'mismatch does not write modules');
        }
    } catch (Throwable $e) {
        echo 'FAIL: db fixture exception: ' . $e->getMessage() . "\n";
        ++$failed;
    }
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mac3_flag(null);
}

mac3_flag(null);
echo "\nModule addon activation tests: {$passed} passed, {$failed} failed, {$skipped} skipped\n";
exit($failed > 0 ? 1 : 0);
