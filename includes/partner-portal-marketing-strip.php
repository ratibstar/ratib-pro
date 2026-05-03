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
    <div class="partner-portal-masthead-brand">
        <span class="partner-portal-ratib-wordmark">Ratib Company</span>
        <span class="partner-portal-ratib-sub">RATIB — Recruitment Automation &amp; Tracking Intelligence Base</span>
    </div>
</div>
