<?php
/**
 * Public marketing chrome: decorative bg + top bar + sticky header stack (same markup as pages/home.php).
 *
 * Optional URL prefix for hash links (e.g. partner-portal-login.php → home.php#section):
 *   $ratebHomeNavHrefPrefix = $baseUrl . '/pages/home.php';
 *
 * Highlight Partner Login when already on that page:
 *   $ratebHomeHeaderPartnerIsCurrent = true;
 *
 * @var string $baseUrl
 * @var array<string,mixed> $ratebHome
 * @var string $ratebHomeUiRev
 * @var string $ratebHomePhpMtime
 * @var string $ratebNavProductTourHref
 * @var string $ratebNavProductTourLabel
 * @var string $ratebPhoneDigits
 * @var string $ratebPhoneRaw
 * @var int $ratebTopbarNodesNum
 * @var string $ratebTopbarNodesDigits
 */
if (!isset($baseUrl) || !is_string($baseUrl) || $baseUrl === '') {
    if (!function_exists('rateb_public_site_base_url')) {
        require_once __DIR__ . '/rateb-public-base-url.php';
    }
    $baseUrl = rateb_public_site_base_url();
}
if (!isset($ratebHome) || !is_array($ratebHome)) {
    $ratebHome = [];
}
$ratebNavPrefix = isset($ratebHomeNavHrefPrefix) ? (string) $ratebHomeNavHrefPrefix : '';
$ratebPartnerNavIsCurrent = !empty($ratebHomeHeaderPartnerIsCurrent);

require_once __DIR__ . '/rateb-mega-nav-render.php';
if (!function_exists('rateb_mega_nav_resolve_href')) {
    $resolveMain = __DIR__ . '/rateb-mega-nav-resolve.php';
    if (is_file($resolveMain)) {
        require_once $resolveMain;
    } else {
        require_once __DIR__ . '/rateb-mega-nav-resolve.fallback.php';
    }
}
if (!function_exists('rateb_home_nav_emit_sync_guard_style')) {
    require_once __DIR__ . '/rateb-home-public-nav-sync.php';
}
?>
    <div class="rateb-saas-bg" aria-hidden="true">
        <div class="rateb-saas-bg__gradient"></div>
        <div class="rateb-saas-bg__grid"></div>
        <div class="rateb-saas-bg__orb rateb-saas-bg__orb--a"></div>
        <div class="rateb-saas-bg__orb rateb-saas-bg__orb--b"></div>
    </div>

    <div class="rateb-public-header-pin" id="rateb-public-header-pin">
    <div class="rateb-topbar">
        <div class="rateb-topbar__inner rateb-container">
            <div class="rateb-topbar__left">
                <a href="tel:+<?php echo htmlspecialchars($ratebPhoneDigits, ENT_QUOTES, 'UTF-8'); ?>" class="rateb-topbar__link" dir="ltr"><i class="fas fa-phone-alt" aria-hidden="true"></i> <span class="rateb-topbar__phone-text"><?php echo htmlspecialchars($ratebPhoneRaw, ENT_QUOTES, 'UTF-8'); ?></span></a>
                <a href="https://wa.me/<?php echo htmlspecialchars($ratebPhoneDigits, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="rateb-topbar__wa" title="WhatsApp">
                    <span class="rateb-live-dot" aria-hidden="true"></span>
                    <?php echo htmlspecialchars($ratebHome['home.topbar.wa_label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </div>
            <div class="rateb-topbar__right">
                <span class="rateb-topbar__ops" dir="ltr"><span class="rateb-mono-tag"><?php echo htmlspecialchars($ratebHome['home.topbar.tls_label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span><?php
                    $ratebTopbarOpsLine = trim((string) ($ratebHome['home.topbar.ops_line'] ?? ($ratebTopbarOpsLine ?? '')));
                    if ($ratebTopbarOpsLine !== '') {
                        echo '<span class="rateb-topbar__ops-sep">·</span><span class="rateb-mono-tag">' . htmlspecialchars($ratebTopbarOpsLine, ENT_QUOTES, 'UTF-8') . '</span>';
                    } elseif (!empty($ratebTopbarNodesDigits) && (int) $ratebTopbarNodesNum > 0) {
                        echo '<span class="rateb-topbar__ops-sep">·</span><span class="rateb-mono-tag"><span id="rateb-topbar-nodes-counter" class="rateb-live-counter" data-rateb-counter="' . htmlspecialchars($ratebTopbarNodesDigits, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string) $ratebTopbarNodesNum, ENT_QUOTES, 'UTF-8') . '</span> ' . htmlspecialchars($ratebHome['home.topbar.nodes_suffix'] ?? '', ENT_QUOTES, 'UTF-8') . '</span>';
                    }
                ?></span>
                <a href="<?php echo htmlspecialchars(function_exists('rateb_public_page_url') ? rateb_public_page_url($baseUrl, 'customer-portal.php') : rtrim($baseUrl, '/') . '/pages/customer-portal'); ?>" class="rateb-topbar__link"><?php echo htmlspecialchars($ratebHome['home.topbar.client_login'] ?? '', ENT_QUOTES, 'UTF-8'); ?></a>
                <span class="rateb-topbar__lang" role="group" aria-label="Language">
                    <span class="rateb-lang rateb-lang--active">EN</span>
                    <span class="rateb-lang-sep">·</span>
                    <?php $ratebMarketingHomeHref = function_exists('rateb_public_marketing_home_url') ? rateb_public_marketing_home_url($baseUrl) : rtrim($baseUrl, '/') . (function_exists('rateb_marketing_home_path') ? rateb_marketing_home_path() : '/home'); ?>
                <a href="<?php echo htmlspecialchars($ratebMarketingHomeHref, ENT_QUOTES, 'UTF-8'); ?>" class="rateb-lang" aria-label="Arabic experience inside partner portals">AR</a>
                </span>
            </div>
        </div>
    </div>

    <header class="rateb-nav-shell" id="rateb-main-header">
        <noscript><style>#ratebNavMenu{visibility:visible!important;opacity:1!important;pointer-events:auto!important}</style></noscript>
        <div class="rateb-container rateb-nav-shell__inner">
            <?php
                $ratebBrandName = trim((string) ($ratebHome['home.brand.name'] ?? ''));
                if (function_exists('rateb_site_content_rebrand_resolve_brand_name')) {
                    $ratebBrandName = rateb_site_content_rebrand_resolve_brand_name($ratebBrandName);
                } elseif ($ratebBrandName === '' || preg_match('/rateb/i', $ratebBrandName)) {
                    $ratebBrandName = function_exists('rateb_brand_name') ? rateb_brand_name() : 'RATEB';
                }
                if ($ratebBrandName === '') {
                    $ratebBrandName = 'RATEB';
                }
                $ratebHideBrandText = preg_match('/^RATEB(\s+Company)?$/i', $ratebBrandName) === 1
                    || preg_match('/^rateb$/i', $ratebBrandName) === 1;
                $ratebBrandProfileLabel = trim((string) ($ratebHome['home.brand.profile_tab'] ?? ''));
                if ($ratebBrandProfileLabel === '') {
                    $ratebBrandProfileLabel = 'Profile';
                }
                $ratebOnProfilePage = !empty($ratebAboutPageActive);
                $ratebProfileUrl = rtrim($baseUrl, '/') . '/profile/#company-profile';
                $ratebBrandProfileHref = $ratebProfileUrl;
                $ratebBrandProfileCurrent = $ratebOnProfilePage;
                $ratebProfileClickJs = '';
                $ratebProfileNavPrefix = $ratebNavPrefix !== ''
                    ? $ratebNavPrefix
                    : rtrim($baseUrl, '/') . '/profile/';
                $ratebPillHref = static function (string $pillKey) use ($ratebOnProfilePage, $ratebNavPrefix, $ratebProfileNavPrefix, $baseUrl): string {
                    if ($ratebOnProfilePage) {
                        return rateb_mega_nav_resolve_href(
                            $pillKey,
                            $baseUrl,
                            $ratebProfileNavPrefix
                        );
                    }
                    $resolvePrefix = !empty($GLOBALS['rateb_public_nav_on_marketing_home']) ? '' : $ratebNavPrefix;

                    return rateb_mega_nav_resolve_href($pillKey, $baseUrl, $resolvePrefix);
                };
                $ratebTourResolvePrefix = $ratebOnProfilePage
                    ? $ratebProfileNavPrefix
                    : (!empty($GLOBALS['rateb_public_nav_on_marketing_home']) ? '' : $ratebNavPrefix);
                $ratebTourHref = rateb_public_nav_tour_href(
                    $baseUrl,
                    $ratebTourResolvePrefix,
                    $ratebNavProductTourHref
                );
                ?>
            <div class="rateb-nav__brand-block<?php echo $ratebHideBrandText ? ' rateb-nav__brand-block--animated rateb-nav__brand-block--row' : ''; ?>"<?php echo $ratebHideBrandText ? ' data-rateb-brand-nav="row-v1"' : ''; ?>>
                <?php
                if (!function_exists('rateb_render_brand_full_title')) {
                    require_once __DIR__ . '/rateb-brand-full-title.php';
                }
                ?>
                <a href="<?php echo htmlspecialchars($ratebMarketingHomeHref ?? (function_exists('rateb_public_marketing_home_url') ? rateb_public_marketing_home_url($baseUrl) : rtrim($baseUrl, '/') . (function_exists('rateb_marketing_home_path') ? rateb_marketing_home_path() : '/home')), ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__brand<?php echo $ratebHideBrandText ? ' rateb-nav__brand--animated-title' : ' rateb-nav__brand--stacked'; ?>">
                    <?php if (!$ratebHideBrandText) { ?>
                    <img src="<?php echo htmlspecialchars($baseUrl . '/assets/rateb-logo.svg?v=6'); ?>" alt="<?php echo htmlspecialchars($ratebBrandName, ENT_QUOTES, 'UTF-8'); ?>" width="120" height="36" class="rateb-nav__brand-logo">
                    <?php } ?>
                    <?php if ($ratebHideBrandText) { ?>
                    <?php rateb_render_brand_full_title(['variant' => 'nav', 'show_tagline' => false]); ?>
                    <?php } else { ?>
                    <span class="rateb-nav__brand-text"><?php echo htmlspecialchars($ratebBrandName, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php } ?>
                </a>
                <a href="<?php echo htmlspecialchars($ratebBrandProfileHref, ENT_QUOTES, 'UTF-8'); ?>" class="rateb-nav__brand-profile rateb-nav__go-profile<?php echo $ratebBrandProfileCurrent ? ' is-current' : ''; ?>" data-rateb-profile-nav="1" data-rateb-go-profile="1"<?php echo $ratebBrandProfileCurrent ? ' aria-current="page"' : ''; ?>><?php echo htmlspecialchars($ratebBrandProfileLabel, ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
            <button type="button" class="rateb-nav__toggle" id="ratebNavToggle" aria-label="Open menu" aria-expanded="false" aria-controls="ratebNavMenu">
                <span></span><span></span><span></span>
            </button>
            <!-- rateb-home-nav-build: chrome=<?php echo htmlspecialchars($ratebChromeBundleHash ?? '', ENT_QUOTES, 'UTF-8'); ?> ui-rev=<?php echo htmlspecialchars($ratebHomeUiRev, ENT_QUOTES, 'UTF-8'); ?> home.php-mtime=<?php echo htmlspecialchars($ratebHomePhpMtime, ENT_QUOTES, 'UTF-8'); ?> primary-links=8 brand-profile=plain-href-v6 mega-nav-root=#ratebMegaNavRoot -->
            <!-- rateb-profile-nav=plain-href-v5 -->
            <nav class="rateb-nav__menu" id="ratebNavMenu" aria-label="Primary" data-rateb-primary-nav-links="8" data-rateb-ui-rev="<?php echo htmlspecialchars($ratebHomeUiRev, ENT_QUOTES, 'UTF-8'); ?>" data-rateb-nav-visual="svg-glyphs-semantic-<?php echo htmlspecialchars($ratebHomeUiRev, ENT_QUOTES, 'UTF-8'); ?>">
                <?php rateb_mega_nav_render($baseUrl, $ratebNavPrefix); ?>
            </nav>
            <?php rateb_home_nav_emit_sync_script($ratebProfileUrl); ?>
            <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" width="0" height="0" class="rateb-nav-glyph-sprite" style="position:absolute;width:0;height:0;overflow:hidden">
                <symbol id="rateb-ng-platform" viewBox="0 0 24 24"><path fill="currentColor" d="M3 3h7v7H3V3zm11 0h7v7h-7V3zM3 14h7v7H3v-7zm11 0h7v7h-7v-7z"/></symbol>
                <symbol id="rateb-ng-domains" viewBox="0 0 24 24"><path fill="currentColor" d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zm6.93 6h-2.95c-.32-1.25-.78-2.45-1.38-3.56 1.84.63 3.37 1.91 4.33 3.56zM12 4.04c.83 1.2 1.48 2.53 1.91 3.96h-3.82c.43-1.43 1.08-2.76 1.91-3.96zM4.26 14C4.1 13.36 4 12.69 4 12s.1-1.36.26-2h3.38c-.08.66-.14 1.32-.14 2 0 .68.06 1.34.14 2H4.26zm.82 2h2.95c.32 1.25.78 2.45 1.38 3.56-1.84-.63-3.37-1.9-4.33-3.56zm2.95-8H5.08c.96-1.66 2.49-2.93 4.33-3.56C8.81 5.55 8.35 6.75 8.03 8zM12 19.96c-.83-1.2-1.48-2.53-1.91-3.96h3.82c-.43 1.43-1.08 2.76-1.91 3.96zM16.97 11.68c.08-.66.14-1.32.14-2 0-.68-.06-1.34-.14-2h3.38c.16.64.26 1.31.26 2s-.1 1.36-.26 2h-3.38zm-1.82 6.56c.6-1.11 1.06-2.31 1.38-3.56h2.95c-.96 1.65-2.49 2.93-4.33 3.56z"/></symbol>
                <symbol id="rateb-ng-video" viewBox="0 0 24 24"><path fill="currentColor" d="M5 8h9v8H5a2 2 0 01-2-2v-4a2 2 0 012-2zm11 2l6 4v4l-6 4V10z"/></symbol>
                <symbol id="rateb-ng-agency" viewBox="0 0 24 24"><path fill="currentColor" d="M6 21h12V11l-6-3.6L6 11v10zm3-10h2v5H9v-5zm5 0h2v5h-2v-5z"/></symbol>
                <symbol id="rateb-ng-features" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2l2.2 5.5H20l-4.4 3.2 1.7 5.3L12 15.2 7.7 16l1.7-5.3L5 7.5h5.8L12 2z"/></symbol>
                <symbol id="rateb-ng-solutions" viewBox="0 0 24 24"><path fill="currentColor" d="M11 3h2v5h5v2h-5v5h-2v-5H6V8h5V3z"/></symbol>
                <symbol id="rateb-ng-programs" viewBox="0 0 24 24"><path fill="currentColor" d="M5 11l7-5 8 8-7 5H5v-8zm2 2v7h8v-7l-4-3-4 3z"/></symbol>
                <symbol id="rateb-ng-agencies" viewBox="0 0 24 24"><path fill="currentColor" d="M4 22h4v-9H4v9zm7 0h4V11h-4v11zm7 0h4v-6h-4v6zM7 11h2v2H7v-2zm9 0h2v5h-2v-5z"/></symbol>
                <symbol id="rateb-ng-tracking" viewBox="0 0 24 24"><path fill="currentColor" d="M12 4a8 8 0 100 16 8 8 0 000-16zm0 3v3H9v2h3v3h2v-3h3v-2h-3V7h-2z"/></symbol>
                <symbol id="rateb-ng-operational" viewBox="0 0 24 24"><path fill="currentColor" d="M12 5c-5.45 0-9.33 3.56-10 7 .67 3.44 4.55 7 10 7s9.33-3.56 10-7c-.67-3.44-4.55-7-10-7zm0 11a4 4 0 110-8 4 4 0 010 8z"/></symbol>
                <symbol id="rateb-ng-api" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M7 8l-4 4 4 4 1.4-1.4L5.8 12l2.6-2.6L7 8zm10 0l-1.4 1.4L18.2 12l-2.6 2.6L17 16l4-4-4-4z"/>
                    <path fill="currentColor" d="M11 4h2l-2 16h-2l2-16z"/>
                </symbol>
                <symbol id="rateb-ng-contact" viewBox="0 0 24 24"><path fill="currentColor" d="M4 7h16v11H4V7zm1 2 7 4.5 7-4.5V17H5V9z"/></symbol>
                <symbol id="rateb-ng-partner" viewBox="0 0 24 24"><path fill="currentColor" d="M8 12a3 3 0 116 0 3 3 0 01-6 0zm9-1a2 2 0 11-4 0 2 2 0 014 0zM4 18c0-2.5 3-4 7-4s7 1.5 7 4v1H4v-1zm12 0v-.5c0-1.5 2-2.5 4-2.5s4 1 4 2.5V19h-8z"/></symbol>
            </svg>
            <div class="rateb-nav__cta">
                <?php if ($ratebPartnerNavIsCurrent): ?>
                <span class="rateb-btn rateb-btn--ghost rateb-nav__partner-login is-current" aria-current="page"><span class="rateb-nav__partner-icon" aria-hidden="true"><svg class="rateb-nav__glyph" viewBox="0 0 24 24" focusable="false"><use href="#rateb-ng-partner"/></svg></span><span class="rateb-nav__partner-label"><?php echo htmlspecialchars($ratebHome['home.nav.cta_partner'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></span>
                <?php else: ?>
                <a href="<?php echo htmlspecialchars(function_exists('rateb_public_page_url') ? rateb_public_page_url($baseUrl, 'partner-portal-login.php') : rtrim($baseUrl, '/') . '/pages/partner-portal-login'); ?>" class="rateb-btn rateb-btn--ghost rateb-nav__partner-login"><span class="rateb-nav__partner-icon" aria-hidden="true"><svg class="rateb-nav__glyph" viewBox="0 0 24 24" focusable="false"><use href="#rateb-ng-partner"/></svg></span><span class="rateb-nav__partner-label"><?php echo htmlspecialchars($ratebHome['home.nav.cta_partner'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    </div>

