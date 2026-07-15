#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * PRODUCTION BOOTSTRAP FATAL PROBE (CLI only)
 *
 * Finds which shared-bootstrap step kills WebsiteKernel and/or ERP login.
 * Does not mutate DB. Safe to run on cPanel Terminal.
 *
 * Usage (cPanel → Terminal):
 *   cd ~/public_html   # or wherever rateb-erp lives
 *   php rateb-erp/bin/bootstrap-fatal-probe.php
 *   php rateb-erp/bin/bootstrap-fatal-probe.php --mode=website
 *   php rateb-erp/bin/bootstrap-fatal-probe.php --mode=erp
 *   php rateb-erp/bin/bootstrap-fatal-probe.php --mode=lint
 *
 * Copy the FIRST "[FAIL]" / "[FATAL]" / ">>> THIS IS THE PRODUCTION ROOT CAUSE" block.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only. Refuse web execution.\n");
    exit(2);
}

$root = dirname(__DIR__);
$root = str_replace('\\', '/', realpath($root) ?: $root);

$mode = 'all';
$isChild = false;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--mode=')) {
        $mode = strtolower(substr($arg, 7));
    }
    if ($arg === '--child=1') {
        $isChild = true;
    }
}

// Child process: run one boot probe and exit (never spawn again).
if ($isChild || getenv('RATEB_PROBE_CHILD')) {
    $runMode = getenv('RATEB_PROBE_CHILD') ?: $mode;
    if (!in_array($runMode, ['website', 'erp'], true)) {
        fwrite(STDERR, "Child mode must be website|erp, got: {$runMode}\n");
        exit(2);
    }
    exit(rateb_probe_boot($root, $runMode));
}

if (!in_array($mode, ['all', 'lint', 'website', 'erp'], true)) {
    fwrite(STDERR, "Unknown --mode={$mode}. Use: all|lint|website|erp\n");
    exit(2);
}

echo "=== RATEB bootstrap fatal probe ===\n";
echo 'PHP: ' . PHP_VERSION . ' | SAPI: ' . PHP_SAPI . "\n";
echo "ROOT: {$root}\n";
echo 'TIME: ' . gmdate('Y-m-d H:i:s') . "Z\n\n";

$failures = 0;
$runLint = $mode === 'all' || $mode === 'lint';
$runWebsite = $mode === 'all' || $mode === 'website';
$runErp = $mode === 'all' || $mode === 'erp';

if ($runLint) {
    $failures += rateb_probe_lint($root) ? 0 : 1;
}
if ($runWebsite) {
    $failures += rateb_probe_spawn($root, 'website') === 0 ? 0 : 1;
}
if ($runErp) {
    $failures += rateb_probe_spawn($root, 'erp') === 0 ? 0 : 1;
}

echo "\n=== DONE failures={$failures} ===\n";
if ($failures > 0) {
    echo "Next: paste the FIRST FAIL/FATAL section above. That file:line is the root cause.\n";
    exit(1);
}
echo "All probed steps OK in this CLI PHP. If site still 500, Apache/LiteSpeed may use a different php.ini/binary — check error_log.\n";
exit(0);

/** @return list<string> */
function rateb_probe_website_requires(): array
{
    return [
        '/app/helpers/Request.php',
        '/app/Core/Router.php',
        '/app/Core/View.php',
        '/app/Core/Response.php',
        '/app/Core/Controller.php',
        '/app/Core/Csrf.php',
        '/app/Core/SessionManager.php',
        '/app/Core/Auth.php',
        '/app/Core/Database.php',
        '/app/Core/HybridRuntime.php',
        '/app/Core/Model.php',
        '/app/Core/RouteModuleLoader.php',
        '/app/Core/SecurityHeaders.php',
        '/app/Core/Middleware/Middleware.php',
        '/app/models/Entities.php',
        '/app/models/CmsModels.php',
        '/app/services/AuditService.php',
        '/app/services/CmsService.php',
        '/app/services/CmsLeadNotificationService.php',
        '/app/services/CmsArticleTagService.php',
        '/app/services/CmsMediaService.php',
        '/app/services/LegacyHomeContentService.php',
        '/app/services/DatabaseErrorService.php',
        '/app/controllers/Marketing/MarketingController.php',
        '/app/controllers/Marketing/MarketingAuthController.php',
        '/app/controllers/Marketing/CustomerPortalController.php',
        '/app/controllers/Marketing/CareerPortalController.php',
        '/app/controllers/Marketing/CareerCandidateController.php',
        '/app/controllers/Marketing/WebsitePortalController.php',
        '/app/Core/Middleware/CareerPortalAuthMiddleware.php',
        '/app/Core/Middleware/WebsitePortalAuthMiddleware.php',
        '/app/Website/Career/CareerJobService.php',
        '/app/Website/Career/CareerApplicationService.php',
        '/app/Website/Career/CareerPortalAuthService.php',
        '/app/Website/Career/CareerSeoService.php',
        '/app/Website/Career/CareerNotificationService.php',
        '/app/Website/Career/CareerBlockRenderer.php',
        '/app/Website/Portal/PortalAuthService.php',
        '/app/Website/Portal/PortalRequestService.php',
        '/app/Website/Portal/PortalDocumentService.php',
        '/app/Website/Portal/PortalFinanceService.php',
        '/app/Website/Portal/PortalRecruitmentService.php',
        '/app/Website/Portal/PortalSupportService.php',
        '/app/Website/Portal/PortalAppointmentService.php',
        '/app/Website/Portal/PortalWorkflowService.php',
        '/app/Website/Portal/PortalNotificationService.php',
        '/app/Website/Portal/PortalDashboardService.php',
        '/app/Website/Portal/PortalBlockRenderer.php',
        '/app/Website/Portal/PortalContractService.php',
        '/app/Website/Portal/CustomerWorkspaceService.php',
        '/app/Website/Portal/PortalRateLimit.php',
        '/app/Website/Portal/PortalContactService.php',
        '/app/Website/Portal/PortalTimelineService.php',
        '/app/Website/Portal/PortalBookingService.php',
        '/app/Website/Portal/OnlineServiceService.php',
        '/app/controllers/Admin/LocaleController.php',
        '/app/Website/TenantContext.php',
        '/app/Website/WebsiteContext.php',
        '/app/Website/TenantWebsiteRepository.php',
        '/app/Website/TenantThemeService.php',
        '/app/Website/TenantSeoService.php',
        '/app/Website/TenantMenuService.php',
        '/app/Website/TenantBlockService.php',
        '/app/Website/TenantMediaService.php',
        '/app/Website/TenantWebsiteService.php',
        '/app/Website/WebsiteKernel.php',
        '/app/Website/WebsiteBlockRegistry.php',
        '/app/Website/WebsiteBlockRenderer.php',
        '/app/Website/WebsiteFormService.php',
        '/app/Website/WebsiteBuilderService.php',
        '/app/Website/WebsiteVersionService.php',
        '/app/Website/WebsiteSeoEditorService.php',
        '/app/Website/WebsiteThemeEditorService.php',
        '/app/Website/WebsiteMediaManagerService.php',
        '/app/Website/WebsiteMenuBuilderService.php',
        '/app/Website/Theme/ThemeManifest.php',
        '/app/Website/Theme/ThemePackage.php',
        '/app/Website/Theme/ThemeValidator.php',
        '/app/Website/Theme/ThemeCatalogService.php',
        '/app/Website/Theme/ThemeOverrideService.php',
        '/app/Website/Theme/ThemeResolver.php',
        '/app/Website/Theme/ThemeInstaller.php',
        '/app/Website/Theme/ThemeBackupService.php',
        '/app/Website/Theme/ThemeExportService.php',
        '/app/Website/Theme/ThemeImportService.php',
        '/app/Website/Theme/ThemeDemoImportService.php',
        '/app/Website/Theme/ThemeMarketplaceService.php',
        '/app/services/DedicatedTenantPolicy.php',
        '/app/models/Company.php',
        '/app/helpers/StorageHelper.php',
    ];
}

/** @return list<string> */
function rateb_probe_shared_files(): array
{
    return [
        '/app/Core/Bootstrap.php',
        '/app/Website/WebsiteKernel.php',
        '/public/index.php',
        '/app/Core/Controller.php',
        '/app/Core/Database.php',
        '/app/Core/HybridRuntime.php',
        '/app/Core/SessionManager.php',
        '/app/Core/Auth.php',
        '/app/helpers/StorageHelper.php',
        '/config/app.php',
        '/config/database.php',
        '/config/lang/en.php',
        '/config/lang/ar.php',
    ];
}

function rateb_probe_step(string $label): void
{
    echo "[STEP] {$label}\n";
    $GLOBALS['RATEB_PROBE_LAST_STEP'] = $label;
}

function rateb_probe_ok(string $label): void
{
    echo "[OK]   {$label}\n";
}

function rateb_probe_fail(string $label, Throwable $e): void
{
    echo "[FAIL] {$label}\n";
    echo '       ' . $e::class . ': ' . $e->getMessage() . "\n";
    echo '       at ' . $e->getFile() . ':' . $e->getLine() . "\n";
}

function rateb_probe_lint(string $root): bool
{
    echo "--- MODE lint (php -l) ---\n";
    $files = array_values(array_unique(array_merge(
        rateb_probe_shared_files(),
        rateb_probe_website_requires()
    )));
    $bad = 0;
    $missing = 0;
    $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
    foreach ($files as $rel) {
        $path = $root . $rel;
        if (!is_file($path)) {
            echo "[MISS] {$rel}\n";
            $missing++;
            continue;
        }
        $out = [];
        $code = 0;
        exec(escapeshellarg($php) . ' -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
        if ($code !== 0) {
            echo "[PARSE] {$rel}\n       " . trim(implode(' ', $out)) . "\n";
            $bad++;
        }
    }
    echo "lint summary: parse_errors={$bad} missing={$missing} checked=" . count($files) . "\n\n";

    return $bad === 0;
}

function rateb_probe_spawn(string $root, string $childMode): int
{
    $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
    $script = str_replace('\\', '/', __FILE__);
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script)
        . ' --mode=' . escapeshellarg($childMode) . ' --child=1';
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $env = array_merge($_ENV, [
        'RATEB_PROBE_CHILD' => $childMode,
    ]);
    $proc = proc_open($cmd, $descriptors, $pipes, $root, $env);
    if (!is_resource($proc)) {
        echo "[FAIL] could not spawn child mode={$childMode}\n";

        return 1;
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    echo $stdout;
    if ($stderr !== '') {
        echo $stderr;
    }
    if ($code !== 0) {
        echo "[CHILD_EXIT] mode={$childMode} code={$code}\n\n";
    }

    return $code;
}

function rateb_probe_boot(string $root, string $mode): int
{
    echo "--- MODE {$mode} (step require + boot) ---\n";

    $GLOBALS['RATEB_PROBE_LAST_STEP'] = 'start';
    register_shutdown_function(static function (): void {
        $err = error_get_last();
        if ($err === null) {
            return;
        }
        if (!in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        $last = (string) ($GLOBALS['RATEB_PROBE_LAST_STEP'] ?? '?');
        fwrite(STDERR, "[FATAL] after STEP={$last}\n");
        fwrite(STDERR, '        type=' . $err['type'] . ' message=' . $err['message'] . "\n");
        fwrite(STDERR, '        at ' . $err['file'] . ':' . $err['line'] . "\n");
        fwrite(STDERR, ">>> THIS IS THE PRODUCTION ROOT CAUSE LINE <<<\n");
    });

    try {
        rateb_probe_step('php_version');
        if (PHP_VERSION_ID < 70400) {
            throw new RuntimeException('PHP < 7.4');
        }
        rateb_probe_ok('php_version=' . PHP_VERSION);

        rateb_probe_step('define_RATEB_ROOT');
        if (!defined('RATEB_ROOT')) {
            define('RATEB_ROOT', $root);
        }
        if ($mode === 'website' && !defined('RATEB_WEBSITE_KERNEL')) {
            define('RATEB_WEBSITE_KERNEL', true);
        }
        if (!defined('RATEB_ENV_NO_SESSION')) {
            define('RATEB_ENV_NO_SESSION', true);
        }
        $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'rateb.sa';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $mode === 'website' ? '/site/about' : '/login';
        rateb_probe_ok('RATEB_ROOT=' . RATEB_ROOT);

        rateb_probe_step('require Bootstrap.php');
        require_once $root . '/app/Core/Bootstrap.php';
        rateb_probe_ok('Bootstrap.php loaded');

        rateb_probe_step('BranchServeEnvBootstrap (optional)');
        $branch = $root . '/app/Core/BranchServeEnvBootstrap.php';
        if (is_file($branch)) {
            require_once $branch;
            \Rateb\App\Core\BranchServeEnvBootstrap::apply($root);
            rateb_probe_ok('BranchServeEnvBootstrap applied');
        } else {
            rateb_probe_ok('BranchServeEnvBootstrap absent (skip)');
        }

        rateb_probe_step('mbstring-polyfill (optional)');
        $mb = $root . '/app/helpers/mbstring-polyfill.php';
        if (is_file($mb)) {
            require_once $mb;
            rateb_probe_ok('mbstring-polyfill');
        } else {
            rateb_probe_ok('mbstring-polyfill absent (skip)');
        }

        if ($mode === 'website') {
            foreach (rateb_probe_website_requires() as $rel) {
                $path = $root . $rel;
                rateb_probe_step('require ' . $rel);
                if (!is_file($path)) {
                    echo "[MISS] {$rel} (Bootstrap skips missing — OK)\n";
                    continue;
                }
                require_once $path;
                rateb_probe_ok($rel);
            }

            rateb_probe_step('SessionManager::start (NO_SESSION → no-op)');
            \Rateb\App\Core\SessionManager::start();
            rateb_probe_ok('SessionManager::start');

            rateb_probe_step('loadConfig → config/app.php');
            require_once $root . '/config/app.php';
            rateb_probe_ok('config/app.php');

            rateb_probe_step('loadConfig → config/database.php');
            require_once $root . '/config/database.php';
            rateb_probe_ok('config/database.php');

            rateb_probe_step('StorageHelper::ensureStorageTree');
            \Rateb\App\Helpers\StorageHelper::ensureStorageTree($root);
            rateb_probe_ok('ensureStorageTree');

            rateb_probe_step('Database::connection');
            $pdo = \Rateb\App\Core\Database::connection();
            rateb_probe_ok('Database::connection=' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

            rateb_probe_step('WebsiteContext::bootFromRequest');
            \Rateb\App\Website\WebsiteContext::bootFromRequest();
            rateb_probe_ok('WebsiteContext::bootFromRequest');

            echo "[PASS] website stepwise boot completed\n\n";

            return 0;
        }

        rateb_probe_step('Bootstrap::init (full ERP)');
        \Rateb\App\Core\Bootstrap::init($root);
        rateb_probe_ok('Bootstrap::init');

        rateb_probe_step('Database::connection after ERP init');
        $pdo = \Rateb\App\Core\Database::connection();
        rateb_probe_ok('Database::connection=' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        rateb_probe_step('Auth::bootstrapFromSession');
        \Rateb\App\Core\Auth::bootstrapFromSession();
        rateb_probe_ok('Auth::bootstrapFromSession');

        echo "[PASS] erp stepwise boot completed\n\n";

        return 0;
    } catch (Throwable $e) {
        rateb_probe_fail((string) ($GLOBALS['RATEB_PROBE_LAST_STEP'] ?? 'unknown'), $e);
        echo ">>> THIS IS THE PRODUCTION ROOT CAUSE LINE <<<\n\n";

        return 1;
    }
}
