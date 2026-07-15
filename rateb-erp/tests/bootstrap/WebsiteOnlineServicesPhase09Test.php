<?php
declare(strict_types=1);

/**
 * Phase WEBSITE-09 — Enterprise Online Services & Booking Platform verification gate.
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
    'migrations/201_website_online_services.sql',
    'app/Website/Portal/OnlineServiceService.php',
    'app/Website/Portal/PortalBookingService.php',
    'app/Website/Portal/PortalAppointmentService.php',
    'app/Website/Portal/PortalTimelineService.php',
    'views/marketing/portals/services.php',
    'views/marketing/portals/service-new.php',
    'views/marketing/portals/service-track.php',
    'views/marketing/portals/service-book.php',
    'public/assets/css/website-portals.css',
    'public/assets/js/website-portals.js',
];
foreach ($files as $rel) {
    $check('exists ' . $rel, is_file($root . '/' . $rel));
}

$mig = (string) file_get_contents($root . '/migrations/201_website_online_services.sql');
$check('migration service requests bridge', str_contains($mig, 'rateb_website_service_requests'));
$check('migration service appointments bridge', str_contains($mig, 'rateb_website_service_appointments'));
$check('migration service timeline bridge', str_contains($mig, 'rateb_website_service_timeline'));
$check('migration permission services', str_contains($mig, 'website.services.manage'));
$check('no duplicate customers table', !str_contains($mig, 'CREATE TABLE IF NOT EXISTS rateb_customers'));
$check('no duplicate contracts table', !str_contains($mig, 'CREATE TABLE IF NOT EXISTS rateb_contracts'));
$check('no duplicate candidates table', !str_contains($mig, 'CREATE TABLE IF NOT EXISTS rateb_recruitment_candidates'));
$check('no duplicate invoices table', !str_contains($mig, 'CREATE TABLE IF NOT EXISTS rateb_invoices'));
$check('no duplicate payments table', !str_contains($mig, 'CREATE TABLE IF NOT EXISTS rateb_payments'));

$online = (string) file_get_contents($root . '/app/Website/Portal/OnlineServiceService.php');
$check('online uses PortalRequestService', str_contains($online, 'PortalRequestService'));
$check('online uses PortalBookingService', str_contains($online, 'PortalBookingService'));
$check('online uses PortalTimelineService', str_contains($online, 'PortalTimelineService'));
$check('online uses PortalFinanceService', str_contains($online, 'PortalFinanceService'));
$check('online uses PortalNotificationService', str_contains($online, 'PortalNotificationService'));
$check('online asserts company', str_contains($online, 'assertRowCompany'));
$check('online agreement gate', str_contains($online, 'agreement_required'));
$check('online payment callback verify', str_contains($online, 'completePaymentCallback') && str_contains($online, 'verifyServicePaymentToken'));

$book = (string) file_get_contents($root . '/app/Website/Portal/PortalBookingService.php');
$check('booking reuses PortalAppointmentService', str_contains($book, 'PortalAppointmentService'));
$check('booking writes service appointments', str_contains($book, 'rateb_website_service_appointments'));

$fin = (string) file_get_contents($root . '/app/Website/Portal/PortalFinanceService.php');
$check('finance HMAC payment tokens', str_contains($fin, 'createServicePaymentToken') && str_contains($fin, 'hash_hmac'));
$check('finance verify payment token', str_contains($fin, 'verifyServicePaymentToken'));

$notif = (string) file_get_contents($root . '/app/Website/Portal/PortalNotificationService.php');
$check('notifications service status', str_contains($notif, 'notifyServiceStatus'));

$req = (string) file_get_contents($root . '/app/Website/Portal/PortalRequestService.php');
$check('requests still use LeadService', str_contains($req, 'LeadService'));
$check('requests allow domestic_worker', str_contains($req, 'domestic_worker'));

$ctrl = (string) file_get_contents($root . '/app/controllers/Marketing/WebsitePortalController.php');
$check('controller OnlineServiceService', str_contains($ctrl, 'OnlineServiceService'));
$check('controller servicePay', str_contains($ctrl, 'servicePay'));
$check('controller payment callback', str_contains($ctrl, 'servicePaymentCallback'));
$check('controller rate limit service', str_contains($ctrl, 'service_create'));
$check('no duplicate online controller', !is_file($root . '/app/controllers/Marketing/OnlineServiceController.php'));

$routes = (string) file_get_contents($root . '/routes/modules/marketing.php');
$check('services list route', str_contains($routes, '/site/customer/services'));
$check('services book route', str_contains($routes, '/site/customer/services/book'));
$check('payment callback route', str_contains($routes, '/site/customer/services/payment/callback'));

$registry = (string) file_get_contents($root . '/app/Website/WebsiteBlockRegistry.php');
foreach ([
    'service_packages', 'online_booking', 'recruitment_wizard', 'pricing_cards',
    'service_timeline', 'appointment_calendar', 'customer_reviews', 'cta_banner',
    'online_contact_form', 'faq',
] as $block) {
    $check('block ' . $block, str_contains($registry, "'" . $block . "'"));
}

$renderer = (string) file_get_contents($root . '/app/Website/WebsiteBlockRenderer.php');
$check('renderer service_packages', str_contains($renderer, 'service_packages'));
$check('single PortalBlockRenderer path', str_contains($renderer, 'PortalBlockRenderer'));
$check('no separate OnlineServiceBlockRenderer', !is_file($root . '/app/Website/Portal/OnlineServiceBlockRenderer.php'));

$layout = (string) file_get_contents($root . '/views/layouts/marketing-portals.php');
$check('no inline script in portals layout', !preg_match('/<script(?![^>]*\bsrc=)[^>]*>/i', $layout));

$boot = (string) file_get_contents($root . '/app/Core/Bootstrap.php');
$check('boot loads OnlineServiceService', preg_match('/function initWebsite[\s\S]*OnlineServiceService\.php/', $boot) === 1);
$check('boot loads PortalBookingService', preg_match('/function initWebsite[\s\S]*PortalBookingService\.php/', $boot) === 1);
$check('boot loads PortalTimelineService', preg_match('/function initWebsite[\s\S]*PortalTimelineService\.php/', $boot) === 1);
$check('boot omits POS', !preg_match('/function initWebsite[\s\S]*PosModule::init/', $boot));
$check('boot omits Offline', !preg_match('/function initWebsite[\s\S]*OfflineModule::init/', $boot));

$perms = (string) file_get_contents($root . '/config/permissions-system.php');
$check('permissions website.services.manage', str_contains($perms, 'website.services.manage'));

$phpFiles = [
    'app/Website/Portal/OnlineServiceService.php',
    'app/Website/Portal/PortalBookingService.php',
    'app/Website/Portal/PortalTimelineService.php',
    'app/Website/Portal/PortalAppointmentService.php',
    'app/Website/Portal/PortalFinanceService.php',
    'app/Website/Portal/PortalNotificationService.php',
    'app/Website/Portal/PortalRequestService.php',
    'app/Website/Portal/PortalBlockRenderer.php',
    'app/controllers/Marketing/WebsitePortalController.php',
];
foreach ($phpFiles as $rel) {
    $out = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($root . '/' . $rel) . ' 2>&1', $out, $code);
    $check('php -l ' . $rel, $code === 0);
}

require $root . '/app/Website/WebsiteKernel.php';
$check('customer services public path', \Rateb\App\Website\WebsiteKernel::isPublicPath('/site/customer/services'));
$check('admin still blocked', !\Rateb\App\Website\WebsiteKernel::isPublicPath('/admin'));

echo "\nGATE: " . ($fail === 0 ? 'CLEAR' : "BLOCKED ($fail failures)") . "\n";
exit($fail === 0 ? 0 : 1);
