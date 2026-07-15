<?php
declare(strict_types=1);

/**
 * Phase WEBSITE-08 — Customer Self-Service & Client Workspace verification gate.
 */
$root = dirname(__DIR__, 2);
$fail = 0;
$check = static function (string $name, bool $ok) use (&$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ": $name\n";
    if (!$ok) {
        $fail++;
    }
};

$files = [
    'migrations/200_website_customer_workspace.sql',
    'app/Website/Portal/CustomerWorkspaceService.php',
    'app/Website/Portal/PortalContractService.php',
    'app/Website/Portal/PortalRateLimit.php',
    'app/Website/Portal/PortalContactService.php',
    'views/marketing/portals/workspace.php',
    'views/marketing/portals/contracts.php',
    'views/marketing/portals/pipeline.php',
    'public/assets/css/website-portals.css',
    'public/assets/js/website-portals.js',
];
foreach ($files as $rel) {
    $check('exists ' . $rel, is_file($root . '/' . $rel));
}

$mig = (string) file_get_contents($root . '/migrations/200_website_customer_workspace.sql');
$check('migration ticket replies bridge', str_contains($mig, 'rateb_website_portal_ticket_replies'));
$check('migration contacts bridge', str_contains($mig, 'rateb_website_portal_contacts'));
$check('migration permission workspace', str_contains($mig, 'website.customer.workspace'));
$check('no duplicate contracts table', !str_contains($mig, 'CREATE TABLE IF NOT EXISTS rateb_contracts'));
$check('no duplicate invoices table', !str_contains($mig, 'CREATE TABLE IF NOT EXISTS rateb_invoices'));
$check('no duplicate candidates table', !str_contains($mig, 'CREATE TABLE IF NOT EXISTS rateb_recruitment_candidates'));

$ws = (string) file_get_contents($root . '/app/Website/Portal/CustomerWorkspaceService.php');
$check('workspace reuses PortalDashboardService', str_contains($ws, 'PortalDashboardService'));
$check('workspace reuses PortalContractService', str_contains($ws, 'PortalContractService'));
$check('workspace reuses PortalFinanceService', str_contains($ws, 'PortalFinanceService'));
$check('workspace reuses PortalRecruitmentService', str_contains($ws, 'PortalRecruitmentService'));
$check('workspace reuses PortalWorkflowService', str_contains($ws, 'PortalWorkflowService'));

$ctr = (string) file_get_contents($root . '/app/Website/Portal/PortalContractService.php');
$check('contracts read rateb_contracts', str_contains($ctr, 'rateb_contracts'));
$check('contracts assertRowCompany', str_contains($ctr, 'assertRowCompany'));

$req = (string) file_get_contents($root . '/app/Website/Portal/PortalRequestService.php');
$check('requests support draft', str_contains($req, 'updateDraft') && str_contains($req, 'as_draft'));
$check('requests still use LeadService', str_contains($req, 'LeadService'));

$rec = (string) file_get_contents($root . '/app/Website/Portal/PortalRecruitmentService.php');
$check('pipeline summary read-only ATS', str_contains($rec, 'pipelineSummary') && str_contains($rec, 'rateb_recruitment_candidates'));

$fin = (string) file_get_contents($root . '/app/Website/Portal/PortalFinanceService.php');
$check('finance statement + findInvoice', str_contains($fin, 'statement') && str_contains($fin, 'findInvoice'));

$sup = (string) file_get_contents($root . '/app/Website/Portal/PortalSupportService.php');
$check('support replies bridge', str_contains($sup, 'addReply') && str_contains($sup, 'rateb_website_portal_ticket_replies'));

$ctrl = (string) file_get_contents($root . '/app/controllers/Marketing/WebsitePortalController.php');
$check('controller uses CustomerWorkspaceService', str_contains($ctrl, 'CustomerWorkspaceService'));
$check('controller rate limit', str_contains($ctrl, 'PortalRateLimit'));
$check('controller audit invoice download', str_contains($ctrl, 'portal.invoice_download'));
$check('no new duplicate portal controller file', !is_file($root . '/app/controllers/Marketing/CustomerWorkspaceController.php'));

$routes = (string) file_get_contents($root . '/routes/modules/marketing.php');
$check('customer contracts route', str_contains($routes, '/site/customer/contracts'));
$check('customer pipeline route', str_contains($routes, '/site/customer/pipeline'));
$check('customer invoice download route', str_contains($routes, '/site/customer/finance/download'));
$check('customer reply route', str_contains($routes, '/site/customer/support/reply'));

$registry = (string) file_get_contents($root . '/app/Website/WebsiteBlockRegistry.php');
foreach ([
    'invoice_summary', 'contract_summary', 'recruitment_progress', 'recent_candidates',
    'pending_approvals', 'payment_status', 'support_widget', 'documents_widget',
    'statistics_cards', 'timeline', 'quick_actions',
] as $block) {
    $check('block ' . $block, str_contains($registry, "'" . $block . "'"));
}

$renderer = (string) file_get_contents($root . '/app/Website/WebsiteBlockRenderer.php');
$check('single PortalBlockRenderer path', str_contains($renderer, 'PortalBlockRenderer'));
$check('no second workspace renderer class', !is_file($root . '/app/Website/Portal/CustomerWorkspaceBlockRenderer.php'));

$layout = (string) file_get_contents($root . '/views/layouts/marketing-portals.php');
$check('no inline script in portals layout', !preg_match('/<script(?![^>]*\bsrc=)[^>]*>/i', $layout));

$boot = (string) file_get_contents($root . '/app/Core/Bootstrap.php');
$check('boot loads CustomerWorkspaceService', preg_match('/function initWebsite[\s\S]*CustomerWorkspaceService\.php/', $boot) === 1);
$check('boot omits POS', !preg_match('/function initWebsite[\s\S]*PosModule::init/', $boot));
$check('boot omits Offline', !preg_match('/function initWebsite[\s\S]*OfflineModule::init/', $boot));

$phpFiles = [
    'app/Website/Portal/CustomerWorkspaceService.php',
    'app/Website/Portal/PortalContractService.php',
    'app/Website/Portal/PortalRateLimit.php',
    'app/Website/Portal/PortalContactService.php',
    'app/Website/Portal/PortalRequestService.php',
    'app/Website/Portal/PortalFinanceService.php',
    'app/Website/Portal/PortalSupportService.php',
    'app/Website/Portal/PortalRecruitmentService.php',
    'app/controllers/Marketing/WebsitePortalController.php',
];
foreach ($phpFiles as $rel) {
    $out = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($root . '/' . $rel) . ' 2>&1', $out, $code);
    $check('php -l ' . $rel, $code === 0);
}

require $root . '/app/Website/WebsiteKernel.php';
$check('customer contracts public path', \Rateb\App\Website\WebsiteKernel::isPublicPath('/site/customer/contracts'));
$check('admin still blocked', !\Rateb\App\Website\WebsiteKernel::isPublicPath('/admin'));

echo "\nGATE: " . ($fail === 0 ? 'CLEAR' : "BLOCKED ($fail failures)") . "\n";
exit($fail === 0 ? 0 : 1);
