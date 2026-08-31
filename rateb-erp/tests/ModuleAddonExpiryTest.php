<?php
declare(strict_types=1);

/**
 * Phase 4 — add-on expiration, cron isolation, post-commit agency push.
 * Run: php rateb-erp/tests/ModuleAddonExpiryTest.php
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
use Rateb\App\Services\ModuleAddonService;
use Rateb\App\Services\PlanLimitService;

$passed = 0;
$failed = 0;
$skipped = 0;

function mac4_assert(bool $cond, string $label): void
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

function mac4_flag(?string $value): void
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
    'pos' => ['name' => 'POS', 'monthly' => 29.0, 'yearly' => 261.0, 'enabled' => true],
];

mac4_flag(null);
$off = new ModuleAddonService($priced);
mac4_assert($off->expireDueAddons(50) === 0, 'flag OFF expireDueAddons returns 0');
$offOne = $off->expireAddon(1);
mac4_assert(!empty($offOne['disabled']), 'flag OFF expireAddon is disabled');

$svcSrc = (string) file_get_contents($root . '/app/services/ModuleAddonService.php');
$cronSrc = (string) file_get_contents($root . '/app/services/CronService.php');
$binSrc = (string) file_get_contents($root . '/bin/erp-cron.php');
$hookSrc = (string) file_get_contents($root . '/app/services/ModuleAddonActivationHook.php');
$agencySrc = (string) file_get_contents($root . '/app/services/AgencyErpMigrationService.php');

mac4_assert(str_contains($svcSrc, 'function expireAddon'), 'expireAddon exists (refined, not a second engine)');
mac4_assert(str_contains($svcSrc, 'function expireDueAddons'), 'expireDueAddons exists');
mac4_assert(str_contains($svcSrc, 'shouldStripModule'), 'surgical strip uses plan / other add-on / preexisting_grant');
mac4_assert(str_contains($svcSrc, 'preexisting_grant'), 'preexisting_grant is a preservation source');
mac4_assert(str_contains($svcSrc, 'hasOtherActiveAddon'), 'other active add-on is a preservation source');
mac4_assert(str_contains($svcSrc, 'planModules'), 'plan membership is a preservation source');
mac4_assert(str_contains($svcSrc, 'FOR UPDATE'), 'expiration re-reads under FOR UPDATE');
mac4_assert(str_contains($svcSrc, 'onlyIfDue'), 'due expiration revalidates ends_at under lock');
mac4_assert(str_contains($svcSrc, "status !== 'active'"), 'pending/cancelled add-ons are not expired');
mac4_assert(str_contains($svcSrc, 'already_expired'), 'duplicate expiration is idempotent');
mac4_assert(str_contains($svcSrc, 'syncLinkedAgencyAfterCommit'), 'agency push is after source commit');
mac4_assert(str_contains($svcSrc, 'pushModulesToLinkedAgency'), 'uses existing agency push only');
mac4_assert(str_contains($svcSrc, 'module_addon_agency_push_failed'), 'agency push failure is logged');
mac4_assert(!str_contains($svcSrc, 'GET_LOCK'), 'no GET_LOCK in add-on service');
mac4_assert(!str_contains($svcSrc, 'rateb_module_entitlements'), 'no second entitlement table');
mac4_assert(!preg_match('/company\.modules\s*=\s*plan/', $svcSrc), 'does not rebuild JSON from plan + add-ons');

mac4_assert(str_contains($cronSrc, "'module_addons_expired'"), 'CronService::runAll includes module_addons_expired');
mac4_assert(str_contains($cronSrc, 'expireDueModuleAddons'), 'cron job is isolated in a private method');
mac4_assert(str_contains($cronSrc, 'expireDueAddons(50)'), 'cron processes at most 50 add-ons');
mac4_assert(str_contains($cronSrc, 'cron_module_addons_expire_failed'), 'cron logs expiration failure');
mac4_assert(str_contains($cronSrc, 'catch (\Throwable $e)'), 'expiration exception is isolated');
mac4_assert(!str_contains($cronSrc, 'GET_LOCK'), 'cron does not use GET_LOCK');
mac4_assert(str_contains($binSrc, 'CronService') && str_contains($binSrc, 'runAll'), 'existing cron binary still calls runAll');
mac4_assert(!str_contains($binSrc, 'ModuleAddon'), 'no new cron binary / no add-on coupling in erp-cron.php');

mac4_assert(str_contains($agencySrc, 'function pushModulesToLinkedAgency'), 'existing agency push method unchanged');
mac4_assert(!str_contains($hookSrc, 'pushModulesToLinkedAgency'), 'webhook hook does not own agency push');
mac4_assert(!str_contains($hookSrc, 'AgencyErpMigrationService'), 'payment webhook hook is unchanged');

$pay = (string) file_get_contents($root . '/app/Payment/PaymentService.php');
$whs = (string) file_get_contents($root . '/app/Payment/PaymentWebhookService.php');
$whc = (string) file_get_contents($root . '/app/controllers/Api/PaymentWebhookController.php');
$moy = (string) file_get_contents($root . '/app/Payment/Gateways/MoyasarGateway.php');
mac4_assert(!str_contains($pay, 'expireDueAddons'), 'PaymentService has no expiry coupling');
mac4_assert(!str_contains($whs, 'expireDueAddons'), 'PaymentWebhookService has no expiry coupling');
mac4_assert(str_contains($whc, 'ModuleAddonActivationHook'), 'Phase 3 webhook hook remains');
mac4_assert(!str_contains($moy, 'ModuleAddon'), 'MoyasarGateway unchanged');

$frozen = [
    'app/Core/Middleware/Middleware.php',
    'app/services/PlanLimitService.php',
    'app/services/AuthorizationService.php',
    'config/app.php',
    'app/Payment/PaymentService.php',
    'app/Payment/PaymentWebhookService.php',
    'app/Payment/Gateways/MoyasarGateway.php',
    'app/controllers/Api/PaymentWebhookController.php',
    'app/services/SaaSAutomationService.php',
    'app/services/AgencyErpMigrationService.php',
    'bin/erp-cron.php',
    'routes/manifest.php',
    'views/partials/sidebar-nav.php',
    'views/partials/sidebar-hr-nav.php',
];
foreach ($frozen as $rel) {
    mac4_assert(is_file($root . '/' . $rel), 'frozen present ' . $rel);
}

$throwingAgency = new class {
    public int $calls = 0;
    public function pushModulesToLinkedAgency(int $platformCompanyId, array $modules): array
    {
        $this->calls++;
        throw new RuntimeException('agency db unavailable');
    }
};
mac4_flag('1');
$svcThrow = new ModuleAddonService($priced, $throwingAgency);
mac4_assert($svcThrow->isEnabled() === true, 'flag 1 is ON for expiry tests');

$recordingAgency = new class {
    /** @var list<array{company_id:int,modules:list<string>}> */
    public array $pushes = [];
    public function pushModulesToLinkedAgency(int $platformCompanyId, array $modules): array
    {
        $this->pushes[] = ['company_id' => $platformCompanyId, 'modules' => array_values($modules)];

        return ['synced' => true, 'agency_id' => 1, 'agency_company_id' => 1];
    }
};

function mac4_table_ready(): bool
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

$dbReady = mac4_table_ready();
if (!$dbReady) {
    ++$skipped;
    echo "SKIP: MySQL / ledger table unavailable — ledger integration tests skipped (not faked)\n";
} else {
    $pdo = Database::connection();
    $pdo->beginTransaction();
    try {
        mac4_flag('1');
        $stamp = substr(bin2hex(random_bytes(6)), 0, 12);
        $starterRow = (new Plan())->queryOne("SELECT id FROM rateb_plans WHERE slug = 'starter' LIMIT 1");
        $commerceRow = (new Plan())->queryOne("SELECT id FROM rateb_plans WHERE slug = 'commerce' LIMIT 1");
        $starterId = (int) ($starterRow['id'] ?? 0);
        $commerceId = (int) ($commerceRow['id'] ?? 0);
        if ($starterId < 1) {
            echo "SKIP: starter plan row missing — DB expiry cases need rateb_plans.slug=starter\n";
            ++$skipped;
        } else {
            $insCompany = static function (PDO $pdo, int $planId, string $stamp, string $tag, $modules): int {
                $email = 'mac4-' . $tag . '-' . $stamp . '@example.test';
                $slug = 'mac4-' . $tag . '-' . $stamp;
                $pdo->prepare(
                    'INSERT INTO rateb_companies (name, slug, email, status, plan_id, modules)
                     VALUES (:n, :slug, :em, \'active\', :pid, :mod)'
                )->execute([
                    'n' => 'MAC4 ' . $tag,
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
            $insInvoice = static function (PDO $pdo, int $companyId, string $stamp, string $tag): int {
                $no = 'MAC4-' . $tag . '-' . $stamp;
                $pdo->prepare(
                    "INSERT INTO rateb_invoices
                        (company_id, invoice_no, invoice_type, amount, tax_amount, total_amount, currency,
                         status, payment_status, issued_at)
                     VALUES
                        (:cid, :no, 'tax', 49, 0, 49, 'SAR', 'sent', 'paid', :iss)"
                )->execute(['cid' => $companyId, 'no' => $no, 'iss' => date('Y-m-d')]);
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
                string $status = 'active',
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
                    $found = $pdo->prepare('SELECT id FROM rateb_company_module_addons WHERE invoice_id = :i LIMIT 1');
                    $found->execute(['i' => $invoiceId]);
                    $id = (int) ($found->fetchColumn() ?: 0);
                }

                return $id;
            };

            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            $exJson = json_encode(['procurement', 'inventory', 'crm', 'dashboard', 'notifications'], JSON_UNESCAPED_UNICODE);
            $agency = $recordingAgency;
            $svcOn = new ModuleAddonService($priced, $agency);

            $cidA = $insCompany($pdo, $starterId, $stamp, 'a', $exJson);
            $invA = $insInvoice($pdo, $cidA, $stamp, 'a');
            $addA = $insAddon($pdo, $cidA, 'crm', $invA, 'active', 0, $yesterday);
            $exA = $svcOn->expireAddon($addA, true);
            mac4_assert(($exA['code'] ?? '') === 'expired', 'expired add-on is marked expired');
            $jsonA = $svcOn->currentJson($cidA);
            mac4_assert(!in_array('crm', $jsonA, true), 'CRM removed when not in plan, no other add-on, grant=0');
            mac4_assert(in_array('procurement', $jsonA, true) && in_array('inventory', $jsonA, true), 'other modules remain untouched');
            mac4_assert(in_array('dashboard', $jsonA, true), 'dashboard remains intact');
            mac4_assert(in_array('notifications', $jsonA, true), 'notifications remain intact');
            mac4_assert($agency->pushes !== [], 'successful expiration can push existing agency synchronization');
            mac4_assert((int) ($agency->pushes[0]['company_id'] ?? 0) === $cidA, 'agency push uses invoice/ledger company');

            $dup = $svcOn->expireAddon($addA, true);
            mac4_assert(($dup['code'] ?? '') === 'already_expired', 'duplicate expiration is idempotent');
            $pushCount = count($agency->pushes);
            $svcOn->expireAddon($addA, true);
            mac4_assert(count($agency->pushes) === $pushCount, 'second expiration does not push again');

            if ($commerceId > 0) {
                $cidB = $insCompany($pdo, $commerceId, $stamp, 'b', json_encode(['pos', 'hr', 'crm'], JSON_UNESCAPED_UNICODE));
                $invB = $insInvoice($pdo, $cidB, $stamp, 'b');
                $addB = $insAddon($pdo, $cidB, 'crm', $invB, 'active', 0, $yesterday);
                $svcOn->expireAddon($addB, true);
                mac4_assert(in_array('crm', $svcOn->currentJson($cidB), true), 'CRM remains when in plan');
            } else {
                echo "SKIP: commerce plan missing — CRM-in-plan expire example\n";
                ++$skipped;
            }

            $cidC = $insCompany($pdo, $starterId, $stamp, 'c', $exJson);
            $invC1 = $insInvoice($pdo, $cidC, $stamp, 'c1');
            $invC2 = $insInvoice($pdo, $cidC, $stamp, 'c2');
            $addC1 = $insAddon($pdo, $cidC, 'crm', $invC1, 'active', 0, $yesterday);
            $insAddon($pdo, $cidC, 'crm', $invC2, 'active', 0, $tomorrow);
            $svcOn->expireAddon($addC1, true);
            mac4_assert(in_array('crm', $svcOn->currentJson($cidC), true), 'CRM remains when another active CRM add-on exists');

            $cidD = $insCompany($pdo, $starterId, $stamp, 'd', $exJson);
            $invD = $insInvoice($pdo, $cidD, $stamp, 'd');
            $addD = $insAddon($pdo, $cidD, 'crm', $invD, 'active', 1, $yesterday);
            $svcOn->expireAddon($addD, true);
            mac4_assert(in_array('crm', $svcOn->currentJson($cidD), true), 'CRM remains when preexisting_grant=1');

            $cidE = $insCompany($pdo, $starterId, $stamp, 'e', null);
            $invE = $insInvoice($pdo, $cidE, $stamp, 'e');
            $addE = $insAddon($pdo, $cidE, 'crm', $invE, 'active', 0, $yesterday);
            $beforeE = (new Company())->queryOne('SELECT modules FROM rateb_companies WHERE id = :id', ['id' => $cidE]);
            $svcOn->expireAddon($addE, true);
            $afterE = (new Company())->queryOne('SELECT modules FROM rateb_companies WHERE id = :id', ['id' => $cidE]);
            mac4_assert(($afterE['modules'] ?? null) === ($beforeE['modules'] ?? null), 'empty JSON is NOT written/rebuilt');

            $cidF = $insCompany($pdo, $starterId, $stamp, 'f', $exJson);
            $invF = $insInvoice($pdo, $cidF, $stamp, 'f');
            $addF = $insAddon($pdo, $cidF, 'crm', $invF, 'active', 0, $tomorrow);
            $exF = $svcOn->expireAddon($addF, true);
            mac4_assert(($exF['code'] ?? '') === 'not_due', 'non-expired add-on remains active');
            $stF = $pdo->prepare('SELECT status FROM rateb_company_module_addons WHERE id = :id');
            $stF->execute(['id' => $addF]);
            mac4_assert((string) $stF->fetchColumn() === 'active', 'renewal extending ends_at prevents expiration');

            $cidG = $insCompany($pdo, $starterId, $stamp, 'g', $exJson);
            $invG = $insInvoice($pdo, $cidG, $stamp, 'g');
            $addG = $insAddon($pdo, $cidG, 'crm', $invG, 'cancelled', 0, $yesterday);
            $exG = $svcOn->expireAddon($addG, true);
            mac4_assert(($exG['code'] ?? '') === 'not_eligible', 'cancelled add-on is untouched');
            $stG = $pdo->prepare('SELECT status FROM rateb_company_module_addons WHERE id = :id');
            $stG->execute(['id' => $addG]);
            mac4_assert((string) $stG->fetchColumn() === 'cancelled', 'cancelled status remains cancelled');

            $cidH = $insCompany($pdo, $starterId, $stamp, 'h', $exJson);
            $invH = $insInvoice($pdo, $cidH, $stamp, 'h');
            $addH = $insAddon($pdo, $cidH, 'crm', $invH, 'expired', 0, $yesterday);
            $exH = $svcOn->expireAddon($addH, true);
            mac4_assert(($exH['code'] ?? '') === 'already_expired', 'already expired add-on is untouched');

            $cidI = $insCompany($pdo, $starterId, $stamp, 'i', $exJson);
            $invI = $insInvoice($pdo, $cidI, $stamp, 'i');
            $addI = $insAddon($pdo, $cidI, 'crm', $invI, 'active', 0, $yesterday);
            $failAgency = $throwingAgency;
            $svcFail = new ModuleAddonService($priced, $failAgency);
            $callsBefore = $failAgency->calls;
            $exI = $svcFail->expireAddon($addI, true);
            mac4_assert(($exI['code'] ?? '') === 'expired', 'agency push failure does NOT fail expiration');
            $stI = $pdo->prepare('SELECT status FROM rateb_company_module_addons WHERE id = :id');
            $stI->execute(['id' => $addI]);
            mac4_assert((string) $stI->fetchColumn() === 'expired', 'addon stays expired after agency failure');
            mac4_assert($failAgency->calls > $callsBefore, 'agency push was attempted');
            mac4_assert(!in_array('crm', $svcFail->currentJson($cidI), true), 'JSON strip survives agency failure');

            mac4_flag('0');
            $cidJ = $insCompany($pdo, $starterId, $stamp, 'j', $exJson);
            $invJ = $insInvoice($pdo, $cidJ, $stamp, 'j');
            $addJ = $insAddon($pdo, $cidJ, 'crm', $invJ, 'active', 0, $yesterday);
            $svcFlag = new ModuleAddonService($priced, $recordingAgency);
            $pushesBefore = count($recordingAgency->pushes);
            mac4_assert($svcFlag->expireDueAddons(50) === 0, 'flag OFF expireDueAddons is a no-op');
            $stJ = $pdo->prepare('SELECT status FROM rateb_company_module_addons WHERE id = :id');
            $stJ->execute(['id' => $addJ]);
            mac4_assert((string) $stJ->fetchColumn() === 'active', 'flag OFF does not expire ledger');
            mac4_assert($svcFlag->currentJson($cidJ) === json_decode($exJson, true), 'flag OFF does not write JSON');
            mac4_assert(count($recordingAgency->pushes) === $pushesBefore, 'flag OFF does not agency-push');
            mac4_flag('1');

            $cidK = $insCompany($pdo, $starterId, $stamp, 'k', $exJson);
            $invK = $insInvoice($pdo, $cidK, $stamp, 'k');
            $addK = $insAddon($pdo, $cidK, 'crm', $invK, 'active', 0, $yesterday);
            $svcDue = new ModuleAddonService($priced);
            $n = $svcDue->expireDueAddons(50);
            mac4_assert($n >= 1, 'expireDueAddons transitions due active rows');
            $stK = $pdo->prepare('SELECT status FROM rateb_company_module_addons WHERE id = :id');
            $stK->execute(['id' => $addK]);
            mac4_assert((string) $stK->fetchColumn() === 'expired', 'locked row is revalidated then expired');
            $n2 = $svcDue->expireDueAddons(50);
            mac4_assert($n2 === 0 || $n2 < $n, 'two expiration attempts cannot duplicate the transition for the same row');
        }
    } catch (Throwable $e) {
        echo 'FAIL: db fixture exception: ' . $e->getMessage() . "\n";
        ++$failed;
    }
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mac4_flag(null);
}

mac4_flag(null);
echo "\nModule addon expiry tests: {$passed} passed, {$failed} failed, {$skipped} skipped\n";
exit($failed > 0 ? 1 : 0);
