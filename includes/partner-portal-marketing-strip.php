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
    <div class="partner-portal-home-header__left">
        <a href="tel:+966599863868" class="partner-portal-home-header__phone">+966 59 986 3868</a>
        <a href="<?php echo $ppStripContact; ?>" class="partner-portal-home-header__link">Contact Us</a>
        <a
            class="partner-portal-home-header__wa"
            href="<?php echo htmlspecialchars($ppStripWa, ENT_QUOTES, 'UTF-8'); ?>"
            target="_blank"
            rel="noopener noreferrer"
        >
            <span class="partner-portal-wa-dot" aria-hidden="true"></span>
            <span>Live via WhatsApp</span>
        </a>
    </div>
    <div class="partner-portal-home-header__center">
        <a href="<?php echo $ppHome; ?>" class="partner-portal-home-header__brand">Ratib Company</a>
        <div class="partner-portal-home-header__tagline">RATIB — Recruitment Automation &amp; Tracking Intelligence Base</div>
    </div>
    <div class="partner-portal-home-header__right">
        <a href="<?php echo $ppHome; ?>" class="partner-portal-home-header__nav">Home</a>
        <a href="<?php echo $ppHome; ?>#programs" class="partner-portal-home-header__nav is-active">Our Programs</a>
        <a href="<?php echo $ppHome; ?>#register" class="partner-portal-home-header__nav">Register</a>
        <a href="<?php echo $ppHome; ?>#video" class="partner-portal-home-header__nav">Video</a>
        <a href="<?php echo $ppHome; ?>#featured" class="partner-portal-home-header__nav">Features</a>
        <a href="<?php echo $ppHome; ?>#hosting" class="partner-portal-home-header__nav">Hosting</a>
        <a href="<?php echo $ppHome; ?>#payment" class="partner-portal-home-header__nav">Payment Methods</a>
        <a href="<?php echo $ppHome; ?>#support" class="partner-portal-home-header__nav">Technical Support</a>
        <a href="<?php echo $ppHome; ?>#contact-options" class="partner-portal-home-header__nav">Contact Options</a>
        <a href="<?php echo $ppCustomerPortal; ?>" class="partner-portal-home-header__portal-btn">Customer Portal</a>
    </div>
</div>
