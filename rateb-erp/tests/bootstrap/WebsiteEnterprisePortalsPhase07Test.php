<?php
declare(strict_types=1);

/**
 * Phase WEBSITE-07 — Enterprise Customer / Employer / Partner Portal verification gate.
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
    'migrations/199_website_enterprise_portals.sql',
    'app/Website/Portal/PortalAuthService.php',
    'app/Website/Portal/PortalRequestService.php',
    'app/Website/Portal/PortalDocumentService.php',
    'app/Website/Portal/PortalFinanceService.php',
    'app/Website/Portal/PortalRecruitmentService.php',
    'app/Website/Portal/PortalSupportService.php',
    'app/Website/Portal/PortalAppointmentService.php',
    'app/Website/Portal/PortalWorkflowService.php',
    'app/Website/Portal/PortalNotificationService.php',
    'app/Website/Portal/PortalDashboardService.php',
    'app/Website/Portal/PortalBlockRenderer.php',
    'app/controllers/Marketing/WebsitePortalController.php',
    'app/Core/Middleware/WebsitePortalAuthMiddleware.php',
    'views/layouts/marketing-portals.php',
    'views/marketing/portals/dashboard.php',
    'views/marketing/portals/recruitment.php',
    'public/assets/css/website-portals.css',
    'public/assets/js/website-portals.js',
];
foreach ($files as $rel) {
    $check('exists ' . $rel, is_file($root . '/' . $rel));
}

$mig = (string) file_get_contents($root . '/migrations/199_website_enterprise_portals.sql');
foreach ([
    'rateb_website_portal_users',
    'rateb_website_portal_requests',
    'rateb_website_portal_documents',
    'rateb_website_portal_shortlists',
    'rateb_website_portal_appointments',
    'rateb_website_portal_ticket_links',
    'website.portal.view',
    'website.employer.manage',
    'website.customer.manage',
    'website.partner.manage',
] as $needle) {
    $check('migration has ' . $needle, str_contains($mig, $needle));
}

$check('no duplicate clients table', !str_contains($mig, 'CREATE TABLE IF NOT EXISTS rateb_clients'));
$check('no duplicate invoices table', !str_contains($mig, 'CREATE TABLE IF NOT EXISTS rateb_invoices'));
$check('no duplicate candidates table', !str_contains($mig, 'CREATE TABLE IF NOT EXISTS rateb_recruitment_candidates'));

$reqSrc = (string) file_get_contents($root . '/app/Website/Portal/PortalRequestService.php');
$check('requests reuse LeadService', str_contains($reqSrc, 'LeadService'));

$recSrc = (string) file_get_contents($root . '/app/Website/Portal/PortalRecruitmentService.php');
$check('recruitment reuses CandidateService', str_contains($recSrc, 'CandidateService'));

$finSrc = (string) file_get_contents($root . '/app/Website/Portal/PortalFinanceService.php');
$check('finance reads rateb_invoices', str_contains($finSrc, 'rateb_invoices'));
$check('finance reads rateb_payments', str_contains($finSrc, 'rateb_payments'));

$wfSrc = (string) file_get_contents($root . '/app/Website/Portal/PortalWorkflowService.php');
$check('workflow uses WorkflowService', str_contains($wfSrc, 'WorkflowService'));

$supSrc = (string) file_get_contents($root . '/app/Website/Portal/PortalSupportService.php');
$check('support uses SupportTicket', str_contains($supSrc, 'SupportTicket'));

$authSrc = (string) file_get_contents($root . '/app/Website/Portal/PortalAuthService.php');
$check('portal users isolated from ERP User', !str_contains($authSrc, 'Auth::attempt') && str_contains($authSrc, 'rateb_website_portal_users'));

$routes = (string) file_get_contents($root . '/routes/modules/marketing.php');
$check('employer portal routes', str_contains($routes, '/site/employer') && str_contains($routes, 'WebsitePortalController'));
$check('customer portal routes', str_contains($routes, "'customer'") && str_contains($routes, 'WebsitePortalController'));
$check('partner portal routes', str_contains($routes, "'partner'") && str_contains($routes, 'foreach ([\'employer\', \'customer\', \'partner\']'));
$check('portal routes before catch-all', strpos($routes, "/site/employer") < strpos($routes, "get('/site/{slug}'"));

$registry = (string) file_get_contents($root . '/app/Website/WebsiteBlockRegistry.php');
foreach ([
    'employer_dashboard', 'customer_dashboard', 'outstanding_invoices', 'active_contracts',
    'recent_requests', 'recruitment_status', 'candidate_pipeline', 'portal_documents',
    'portal_payments', 'portal_support_tickets', 'portal_notifications', 'portal_calendar',
] as $block) {
    $check('block registry ' . $block, str_contains($registry, "'" . $block . "'"));
}

$renderer = (string) file_get_contents($root . '/app/Website/WebsiteBlockRenderer.php');
$check('renderer delegates portal blocks', str_contains($renderer, 'PortalBlockRenderer'));

$layout = (string) file_get_contents($root . '/views/layouts/marketing-portals.php');
$check('no inline script body in portals layout', !preg_match('/<script(?![^>]*\bsrc=)[^>]*>/i', $layout));
$check('external portals css', str_contains($layout, 'website-portals.css'));
$check('external portals js', str_contains($layout, 'website-portals.js'));

$mw = (string) file_get_contents($root . '/routes/middleware-helpers.php');
$check('portal middleware helper', str_contains($mw, 'rateb_website_portal_mw'));

$boot = (string) file_get_contents($root . '/app/Core/Bootstrap.php');
$check('website boot loads PortalAuthService', preg_match('/function initWebsite[\s\S]*PortalAuthService\.php/', $boot) === 1);
$check('website boot omits POS', !preg_match('/function initWebsite[\s\S]*PosModule::init/', $boot));
$check('website boot omits Offline', !preg_match('/function initWebsite[\s\S]*OfflineModule::init/', $boot));

$phpFiles = [
    'app/Website/Portal/PortalAuthService.php',
    'app/Website/Portal/PortalRequestService.php',
    'app/Website/Portal/PortalDocumentService.php',
    'app/Website/Portal/PortalFinanceService.php',
    'app/Website/Portal/PortalRecruitmentService.php',
    'app/Website/Portal/PortalSupportService.php',
    'app/Website/Portal/PortalAppointmentService.php',
    'app/Website/Portal/PortalWorkflowService.php',
    'app/Website/Portal/PortalNotificationService.php',
    'app/Website/Portal/PortalDashboardService.php',
    'app/Website/Portal/PortalBlockRenderer.php',
    'app/controllers/Marketing/WebsitePortalController.php',
    'app/Core/Middleware/WebsitePortalAuthMiddleware.php',
];
foreach ($phpFiles as $rel) {
    $out = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($root . '/' . $rel) . ' 2>&1', $out, $code);
    $check('php -l ' . $rel, $code === 0);
}

require $root . '/app/Website/WebsiteKernel.php';
$check('employer login is public path', \Rateb\App\Website\WebsiteKernel::isPublicPath('/site/employer/login'));
$check('customer portal is public path', \Rateb\App\Website\WebsiteKernel::isPublicPath('/site/customer'));
$check('partner portal is public path', \Rateb\App\Website\WebsiteKernel::isPublicPath('/site/partner'));
$check('admin still blocked', !\Rateb\App\Website\WebsiteKernel::isPublicPath('/admin'));

echo "\nGATE: " . ($fail === 0 ? 'CLEAR' : "BLOCKED ($fail failures)") . "\n";
exit($fail === 0 ? 0 : 1);
