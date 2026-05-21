<?php
/**
 * Public Ratib strip for partner portal pages — top contact line + brand (EN).
 * Full marketing nav links intentionally omitted so partners stay focused on portal tasks.
 */
if (!function_exists('pageUrl')) {
    require_once __DIR__ . '/config.php';
}
$ppStripContact = htmlspecialchars(pageUrl('contact.php'), ENT_QUOTES, 'UTF-8');
$ppStripWa = 'https://wa.me/966599863868';
$ppHome = htmlspecialchars(pageUrl('home.php'), ENT_QUOTES, 'UTF-8');
$ppCustomerPortal = htmlspecialchars(pageUrl('customer-portal.php'), ENT_QUOTES, 'UTF-8');
?>
<div class="partner-portal-home-header">
    <div class="header-left">
        <a href="tel:+966599863868" class="phone"><i class="fas fa-phone-alt"></i> +966 59 986 3868</a>
        <a href="<?php echo $ppStripContact; ?>">Contact Us</a>
        <a
            class="live-status"
            href="<?php echo htmlspecialchars($ppStripWa, ENT_QUOTES, 'UTF-8'); ?>"
            target="_blank"
            rel="noopener noreferrer"
            title="Chat on WhatsApp"
        >
            <span class="live-dots" aria-hidden="true"><span></span><span></span><span></span></span>
            <span>Live via WhatsApp</span>
        </a>
    </div>
    <div class="header-center">
        <a href="<?php echo $ppHome; ?>" class="logo" aria-label="Ratib Home">
            <img src="<?php echo htmlspecialchars(asset('assets/ratib-logo.svg?v=4'), ENT_QUOTES, 'UTF-8'); ?>" alt="RATEB — Rateb Software Foundation for Information Technology">
        </a>
        <div class="tagline">RATEB — Recruitment Automation &amp; Telemetry Enterprise Base</div>
    </div>
    <div class="header-right">
        <a href="<?php echo $ppHome; ?>" class="nav-link">Home</a>
        <a href="<?php echo $ppHome; ?>#programs" class="nav-link active">Our Programs <span class="badge-nav">Important</span></a>
        <a href="<?php echo $ppHome; ?>#register" class="nav-link">Register</a>
        <a href="<?php echo $ppHome; ?>#video" class="nav-link">Video</a>
        <a href="<?php echo $ppHome; ?>#featured" class="nav-link">Features</a>
        <a href="<?php echo $ppHome; ?>#hosting" class="nav-link">Hosting</a>
        <a href="<?php echo $ppHome; ?>#payment" class="nav-link">Payment Methods</a>
        <a href="<?php echo $ppHome; ?>#support" class="nav-link">Technical Support</a>
        <a href="<?php echo $ppHome; ?>#contact-options" class="nav-link">Contact Options</a>
        <a href="<?php echo $ppCustomerPortal; ?>" class="btn-client"><i class="fas fa-user"></i> Customer Portal</a>
    </div>
</div>
