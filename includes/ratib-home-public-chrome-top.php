<?php
/**
 * Public marketing chrome: decorative bg + top bar + sticky nav (same markup as pages/home.php).
 *
 * Optional URL prefix for hash links (e.g. partner-portal-login.php → home.php#section):
 *   $ratibHomeNavHrefPrefix = $baseUrl . '/pages/home.php';
 *
 * Highlight Partner Login when already on that page:
 *   $ratibHomeHeaderPartnerIsCurrent = true;
 *
 * @var string $baseUrl
 * @var array<string,mixed> $ratibHome
 * @var string $ratibHomeUiRev
 * @var string $ratibHomePhpMtime
 * @var string $ratibNavProductTourHref
 * @var string $ratibNavProductTourLabel
 * @var string $ratibPhoneDigits
 * @var string $ratibPhoneRaw
 * @var int $ratibTopbarNodesNum
 * @var string $ratibTopbarNodesDigits
 */
$ratibNavPrefix = isset($ratibHomeNavHrefPrefix) ? (string) $ratibHomeNavHrefPrefix : '';
$ratibPartnerNavIsCurrent = !empty($ratibHomeHeaderPartnerIsCurrent);

require_once __DIR__ . '/ratib-mega-nav-render.php';
if (!function_exists('ratib_home_nav_emit_sync_guard_style')) {
    require_once __DIR__ . '/ratib-home-public-nav-sync.php';
}
?>
    <div class="ratib-saas-bg" aria-hidden="true">
        <div class="ratib-saas-bg__gradient"></div>
        <div class="ratib-saas-bg__grid"></div>
        <div class="ratib-saas-bg__orb ratib-saas-bg__orb--a"></div>
        <div class="ratib-saas-bg__orb ratib-saas-bg__orb--b"></div>
    </div>

    <div class="ratib-topbar">
        <div class="ratib-topbar__inner ratib-container">
            <div class="ratib-topbar__left">
                <a href="tel:+<?php echo htmlspecialchars($ratibPhoneDigits, ENT_QUOTES, 'UTF-8'); ?>" class="ratib-topbar__link" dir="ltr"><i class="fas fa-phone-alt" aria-hidden="true"></i> <span class="ratib-topbar__phone-text"><?php echo htmlspecialchars($ratibPhoneRaw, ENT_QUOTES, 'UTF-8'); ?></span></a>
                <a href="https://wa.me/<?php echo htmlspecialchars($ratibPhoneDigits, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="ratib-topbar__wa" title="WhatsApp">
                    <span class="ratib-live-dot" aria-hidden="true"></span>
                    <?php echo htmlspecialchars($ratibHome['home.topbar.wa_label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </div>
            <div class="ratib-topbar__right">
                <span class="ratib-topbar__ops" aria-hidden="true" dir="ltr"><span class="ratib-mono-tag"><?php echo htmlspecialchars($ratibHome['home.topbar.tls_label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><span class="ratib-topbar__ops-sep">·</span><span class="ratib-mono-tag"><span id="ratib-topbar-nodes-counter" class="ratib-live-counter" data-ratib-counter="<?php echo htmlspecialchars($ratibTopbarNodesDigits, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $ratibTopbarNodesNum, ENT_QUOTES, 'UTF-8'); ?></span> <?php echo htmlspecialchars($ratibHome['home.topbar.nodes_suffix'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></span>
                <a href="<?php echo htmlspecialchars($baseUrl . '/pages/customer-portal.php'); ?>" class="ratib-topbar__link"><?php echo htmlspecialchars($ratibHome['home.topbar.client_login'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                <span class="ratib-topbar__lang" role="group" aria-label="Language">
                    <span class="ratib-lang ratib-lang--active">EN</span>
                    <span class="ratib-lang-sep">·</span>
                    <a href="<?php echo htmlspecialchars($baseUrl . '/pages/home.php'); ?>" class="ratib-lang" title="Arabic experience inside partner portals">AR</a>
                </span>
            </div>
        </div>
    </div>

    <header class="ratib-nav-shell" id="ratib-main-header">
        <noscript><style>#ratibNavMenu{visibility:visible!important;opacity:1!important;pointer-events:auto!important}</style></noscript>
        <div class="ratib-container ratib-nav-shell__inner">
            <a href="<?php echo htmlspecialchars($baseUrl . '/pages/home.php'); ?>" class="ratib-nav__brand">
                <img src="<?php echo htmlspecialchars($baseUrl . '/assets/ratib-logo.svg?v=3'); ?>" alt="RATIB" width="120" height="36">
                <span class="ratib-nav__brand-text">RATIB</span>
            </a>
            <button type="button" class="ratib-nav__toggle" id="ratibNavToggle" aria-label="Open menu" aria-expanded="false" aria-controls="ratibNavMenu">
                <span></span><span></span><span></span>
            </button>
            <!-- ratib-home-nav-build: chrome=<?php echo htmlspecialchars($ratibChromeBundleHash ?? '', ENT_QUOTES, 'UTF-8'); ?> ui-rev=<?php echo htmlspecialchars($ratibHomeUiRev, ENT_QUOTES, 'UTF-8'); ?> home.php-mtime=<?php echo htmlspecialchars($ratibHomePhpMtime, ENT_QUOTES, 'UTF-8'); ?> primary-links=6 mega-nav-root=#ratibMegaNavRoot -->
            <nav class="ratib-nav__menu" id="ratibNavMenu" aria-label="Primary" data-ratib-primary-nav-links="6" data-ratib-ui-rev="<?php echo htmlspecialchars($ratibHomeUiRev, ENT_QUOTES, 'UTF-8'); ?>" data-ratib-nav-visual="svg-glyphs-semantic-<?php echo htmlspecialchars($ratibHomeUiRev, ENT_QUOTES, 'UTF-8'); ?>" style="visibility:hidden;opacity:0;pointer-events:none">
                <?php ratib_mega_nav_render($baseUrl, $ratibNavPrefix); ?>
                <div class="ratib-nav__platform-links" role="group" aria-label="Platform">
                <a href="<?php echo htmlspecialchars($ratibNavPrefix . '#platform', ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link"><span class="ratib-nav__icon" aria-hidden="true"><svg class="ratib-nav__glyph" viewBox="0 0 24 24" focusable="false"><use href="#ratib-ng-platform"/></svg></span><span class="ratib-nav__label"><?php echo htmlspecialchars($ratibHome['home.nav.platform'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                <a href="<?php echo htmlspecialchars($ratibNavPrefix . $ratibNavProductTourHref, ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link ratib-nav__link--product-tour" data-ratib-product-tour-tab="1"><span class="ratib-nav__icon" aria-hidden="true"><svg class="ratib-nav__glyph" viewBox="0 0 24 24" focusable="false"><use href="#ratib-ng-video"/></svg></span><span class="ratib-nav__label"><?php echo htmlspecialchars($ratibNavProductTourLabel, ENT_QUOTES, 'UTF-8'); ?></span></a>
                <a href="<?php echo htmlspecialchars($ratibNavPrefix . '#features', ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link"><span class="ratib-nav__icon" aria-hidden="true"><svg class="ratib-nav__glyph" viewBox="0 0 24 24" focusable="false"><use href="#ratib-ng-features"/></svg></span><span class="ratib-nav__label"><?php echo htmlspecialchars(trim((string) ($ratibHome['home.nav.product'] ?? '')) ?: (string) ($ratibHome['home.nav.features'] ?? 'Product'), ENT_QUOTES, 'UTF-8'); ?></span></a>
                <a href="<?php echo htmlspecialchars($ratibNavPrefix . '#programs', ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link"><span class="ratib-nav__icon" aria-hidden="true"><svg class="ratib-nav__glyph" viewBox="0 0 24 24" focusable="false"><use href="#ratib-ng-programs"/></svg></span><span class="ratib-nav__label"><?php echo htmlspecialchars(trim((string) ($ratibHome['home.nav.pricing'] ?? '')) ?: (string) ($ratibHome['home.nav.programs'] ?? 'Pricing'), ENT_QUOTES, 'UTF-8'); ?></span></a>
                <a href="<?php echo htmlspecialchars($ratibNavPrefix . '#agencies', ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link"><span class="ratib-nav__icon" aria-hidden="true"><svg class="ratib-nav__glyph" viewBox="0 0 24 24" focusable="false"><use href="#ratib-ng-agencies"/></svg></span><span class="ratib-nav__label"><?php echo htmlspecialchars(trim((string) ($ratibHome['home.nav.partners'] ?? '')) ?: (string) ($ratibHome['home.nav.agencies'] ?? 'Partners'), ENT_QUOTES, 'UTF-8'); ?></span></a>
                <a href="<?php echo htmlspecialchars($ratibNavPrefix . '#contact', ENT_QUOTES, 'UTF-8'); ?>" class="ratib-nav__link"><span class="ratib-nav__icon" aria-hidden="true"><svg class="ratib-nav__glyph" viewBox="0 0 24 24" focusable="false"><use href="#ratib-ng-contact"/></svg></span><span class="ratib-nav__label"><?php echo htmlspecialchars($ratibHome['home.nav.contact'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                </div>
            </nav>
            <?php ratib_home_nav_emit_sync_script(); ?>
            <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" width="0" height="0" class="ratib-nav-glyph-sprite" style="position:absolute;width:0;height:0;overflow:hidden">
                <symbol id="ratib-ng-platform" viewBox="0 0 24 24"><path fill="currentColor" d="M3 3h7v7H3V3zm11 0h7v7h-7V3zM3 14h7v7H3v-7zm11 0h7v7h-7v-7z"/></symbol>
                <symbol id="ratib-ng-video" viewBox="0 0 24 24"><path fill="currentColor" d="M5 8h9v8H5a2 2 0 01-2-2v-4a2 2 0 012-2zm11 2l6 4v4l-6 4V10z"/></symbol>
                <symbol id="ratib-ng-agency" viewBox="0 0 24 24"><path fill="currentColor" d="M6 21h12V11l-6-3.6L6 11v10zm3-10h2v5H9v-5zm5 0h2v5h-2v-5z"/></symbol>
                <symbol id="ratib-ng-features" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2l2.2 5.5H20l-4.4 3.2 1.7 5.3L12 15.2 7.7 16l1.7-5.3L5 7.5h5.8L12 2z"/></symbol>
                <symbol id="ratib-ng-solutions" viewBox="0 0 24 24"><path fill="currentColor" d="M11 3h2v5h5v2h-5v5h-2v-5H6V8h5V3z"/></symbol>
                <symbol id="ratib-ng-programs" viewBox="0 0 24 24"><path fill="currentColor" d="M5 11l7-5 8 8-7 5H5v-8zm2 2v7h8v-7l-4-3-4 3z"/></symbol>
                <symbol id="ratib-ng-agencies" viewBox="0 0 24 24"><path fill="currentColor" d="M4 22h4v-9H4v9zm7 0h4V11h-4v11zm7 0h4v-6h-4v6zM7 11h2v2H7v-2zm9 0h2v5h-2v-5z"/></symbol>
                <symbol id="ratib-ng-tracking" viewBox="0 0 24 24"><path fill="currentColor" d="M12 4a8 8 0 100 16 8 8 0 000-16zm0 3v3H9v2h3v3h2v-3h3v-2h-3V7h-2z"/></symbol>
                <symbol id="ratib-ng-operational" viewBox="0 0 24 24"><path fill="currentColor" d="M12 5c-5.45 0-9.33 3.56-10 7 .67 3.44 4.55 7 10 7s9.33-3.56 10-7c-.67-3.44-4.55-7-10-7zm0 11a4 4 0 110-8 4 4 0 010 8z"/></symbol>
                <symbol id="ratib-ng-api" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M7 8l-4 4 4 4 1.4-1.4L5.8 12l2.6-2.6L7 8zm10 0l-1.4 1.4L18.2 12l-2.6 2.6L17 16l4-4-4-4z"/>
                    <path fill="currentColor" d="M11 4h2l-2 16h-2l2-16z"/>
                </symbol>
                <symbol id="ratib-ng-contact" viewBox="0 0 24 24"><path fill="currentColor" d="M4 7h16v11H4V7zm1 2 7 4.5 7-4.5V17H5V9z"/></symbol>
                <symbol id="ratib-ng-partner" viewBox="0 0 24 24"><path fill="currentColor" d="M8 12a3 3 0 116 0 3 3 0 01-6 0zm9-1a2 2 0 11-4 0 2 2 0 014 0zM4 18c0-2.5 3-4 7-4s7 1.5 7 4v1H4v-1zm12 0v-.5c0-1.5 2-2.5 4-2.5s4 1 4 2.5V19h-8z"/></symbol>
            </svg>
            <div class="ratib-nav__cta">
                <?php if ($ratibPartnerNavIsCurrent): ?>
                <span class="ratib-btn ratib-btn--ghost ratib-nav__partner-login is-current" aria-current="page"><span class="ratib-nav__partner-icon" aria-hidden="true"><svg class="ratib-nav__glyph" viewBox="0 0 24 24" focusable="false"><use href="#ratib-ng-partner"/></svg></span><span class="ratib-nav__partner-label"><?php echo htmlspecialchars($ratibHome['home.nav.cta_partner'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></span>
                <?php else: ?>
                <a href="<?php echo htmlspecialchars($baseUrl . '/pages/partner-portal-login.php'); ?>" class="ratib-btn ratib-btn--ghost ratib-nav__partner-login"><span class="ratib-nav__partner-icon" aria-hidden="true"><svg class="ratib-nav__glyph" viewBox="0 0 24 24" focusable="false"><use href="#ratib-ng-partner"/></svg></span><span class="ratib-nav__partner-label"><?php echo htmlspecialchars($ratibHome['home.nav.cta_partner'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                <?php endif; ?>
            </div>
        </div>
    </header>
