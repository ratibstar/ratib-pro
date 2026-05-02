<?php
/**
 * Public Ratib strip for partner portal pages — matches marketing home header cues (EN).
 */
if (!function_exists('pageUrl')) {
    require_once __DIR__ . '/config.php';
}
$ppStripHome = htmlspecialchars(pageUrl('home.php'), ENT_QUOTES, 'UTF-8');
$ppStripContact = htmlspecialchars(pageUrl('contact.php'), ENT_QUOTES, 'UTF-8');
$ppStripWa = 'https://wa.me/966599863868';
?>
<div class="partner-portal-site-bar">
    <div class="partner-portal-site-bar-inner">
        <a class="partner-portal-site-link partner-portal-phone" href="tel:+966599863868">+966 59 986 3868</a>
        <span class="partner-portal-site-bar-sep" aria-hidden="true">|</span>
        <a class="partner-portal-site-link" href="<?php echo $ppStripContact; ?>">Contact Us</a>
        <span class="partner-portal-site-bar-sep" aria-hidden="true">|</span>
        <a
            class="partner-portal-site-link partner-portal-wa-link"
            href="<?php echo htmlspecialchars($ppStripWa, ENT_QUOTES, 'UTF-8'); ?>"
            target="_blank"
            rel="noopener noreferrer"
        >
            <span class="partner-portal-wa-dot" aria-hidden="true"></span>
            Live via WhatsApp
        </a>
    </div>
</div>
<div class="partner-portal-masthead">
    <nav class="partner-portal-nav" aria-label="Ratib website">
        <a href="<?php echo $ppStripHome; ?>">Home</a>
        <span class="partner-portal-nav-item partner-portal-nav-item--badge-wrap">
            <a href="<?php echo $ppStripHome; ?>#programs">Our Programs</a>
            <span class="partner-portal-nav-badge">Important</span>
        </span>
        <a href="<?php echo $ppStripHome; ?>#register">Register</a>
        <a href="<?php echo $ppStripHome; ?>#video">Video</a>
        <a href="<?php echo $ppStripHome; ?>#featured">Features</a>
        <a href="<?php echo $ppStripHome; ?>#hosting">Hosting</a>
        <a href="<?php echo $ppStripHome; ?>#payment">Payment Methods</a>
        <a href="<?php echo $ppStripHome; ?>#support">Technical Support</a>
        <a href="<?php echo $ppStripHome; ?>#contact-options">Contact Options</a>
    </nav>
    <div class="partner-portal-masthead-brand">
        <span class="partner-portal-ratib-wordmark">Ratib Company</span>
        <span class="partner-portal-ratib-sub">RATIB — Recruitment Automation &amp; Tracking Intelligence Base</span>
    </div>
</div>
