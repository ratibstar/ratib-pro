<?php
declare(strict_types=1);

/**
 * Kill FOUC: legacy/cached nav HTML flashes before deferred home-page.js runs.
 * Emit guard style once per page, then sync cleanup script immediately after #ratibNavMenu closes.
 */
function ratib_emit_profile_same_tab_fix(string $baseUrl): void
{
    $profileUrl = rtrim($baseUrl, '/') . '/profile/#company-profile';
    $profileJson = json_encode($profileUrl, JSON_UNESCAPED_SLASHES);
    echo '<script id="ratib-profile-same-tab-fix">(function(){var P=' . $profileJson . ';function kill(){document.querySelectorAll(".ratib-nav__brand-profile,.ratib-nav__link--about,.ratib-nav__go-profile,[data-ratib-profile-nav],[data-ratib-go-profile],.ratib-footer-link--about,a.ratib-mega-nav__card").forEach(function(a){var t=a.querySelector&&a.querySelector(".ratib-mega-nav__card-title");if(a.matches("a.ratib-mega-nav__card")&&(!t||!/company profile/i.test(t.textContent||"")))return;a.setAttribute("href",P);a.removeAttribute("target");a.removeAttribute("rel");var oc=a.getAttribute("onclick");if(oc&&/window\\.open/i.test(oc))a.removeAttribute("onclick");});}function go(ev){var a=ev.target&&ev.target.closest&&ev.target.closest("a");if(!a)return;if(!a.matches(".ratib-nav__brand-profile,.ratib-nav__link--about,.ratib-nav__go-profile,[data-ratib-profile-nav],[data-ratib-go-profile],.ratib-footer-link--about")){if(!a.matches("a.ratib-mega-nav__card"))return;var t=a.querySelector(".ratib-mega-nav__card-title");if(!t||!/company profile/i.test(t.textContent||""))return;}ev.preventDefault();ev.stopImmediatePropagation();window.location.assign(P);}kill();document.addEventListener("click",go,true);document.addEventListener("mousedown",go,true);document.addEventListener("DOMContentLoaded",kill);setTimeout(kill,0);setTimeout(kill,400);})();</script>';
}

function ratib_emit_nav_brand_critical_css(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    echo '<style id="ratib-nav-brand-critical-v5-brand-colors">';
    echo '.ratib-nav__brand--animated-title img,.ratib-nav__brand--animated-title .ratib-nav__brand-logo,';
    echo '.ratib-nav__brand--animated-title .ratib-nav__brand-text,';
    echo '.ratib-nav__brand:has(.ratib-brand-full) img,.ratib-nav__brand:has(.ratib-brand-full) .ratib-nav__brand-text{display:none!important;width:0!important;height:0!important}';
    echo '.ratib-nav__brand-block--animated{display:flex!important;flex-direction:column!important;align-items:flex-start!important;';
    echo 'gap:.32rem!important;max-width:min(20.5rem,40vw)!important;min-width:0!important;padding:.4rem .58rem .36rem .52rem!important;';
    echo 'border-radius:14px!important;border:1px solid rgba(167,139,250,.38)!important;';
    echo 'border-left:4px solid rgba(236,72,153,.75)!important;';
    echo 'background:linear-gradient(145deg,rgba(15,23,42,.92),rgba(76,29,149,.3))!important;';
    echo 'box-shadow:0 8px 24px rgba(0,0,0,.3),inset 0 1px 0 rgba(255,255,255,.06)!important}';
    echo '.ratib-nav__brand--animated-title{display:block!important;width:100%!important}';
    echo '.ratib-brand-full--nav-stack{display:flex!important;flex-direction:column!important;align-items:flex-start!important;gap:.3rem!important;width:100%!important}';
    echo '.ratib-brand-full--nav-stack .ratib-brand-full__head{display:inline-flex!important;flex-wrap:nowrap!important;align-items:baseline!important;gap:.45em!important}';
    echo '.ratib-brand-full--nav-stack .ratib-brand-letter{font-size:clamp(1.02rem,2.35vw,1.28rem)!important;font-weight:800!important}';
    echo '.ratib-brand-full--nav-stack .ratib-brand-full__word--company{font-size:clamp(.76rem,1.5vw,.92rem)!important;font-weight:700!important}';
    echo '.ratib-brand-full--nav-stack .ratib-brand-full__tagline{display:flex!important;flex-direction:column!important;gap:.14rem!important;width:100%!important}';
    echo '.ratib-brand-full--nav-stack .ratib-brand-full__tagline-row{display:inline-flex!important;flex-wrap:wrap!important;gap:.2em .3em!important;';
    echo 'font-size:clamp(.58rem,1.05vw,.72rem)!important;font-weight:600!important;line-height:1.28!important}';
    echo '.ratib-nav__brand-block--animated .ratib-nav__brand-profile{margin-left:0!important;font-size:.64rem!important;padding:.2rem .56rem!important}';
    echo '.ratib-nav__brand:has(.ratib-brand-full--nav):not(.ratib-nav__brand--animated-title) img{display:none!important}';
    echo '.ratib-nav__brand-block:has(.ratib-brand-full){display:flex!important;flex-direction:column!important;align-items:flex-start!important}';
    echo '.ratib-public-header-pin{position:sticky!important;top:0!important;left:0!important;right:0!important;z-index:200!important;width:100%!important;max-width:100vw!important}';
    echo '.ratib-public-header-pin .ratib-nav-shell{position:relative!important;top:auto!important}';
    echo '</style>';
}

function ratib_home_nav_emit_sync_guard_style(): void
{
    if (!function_exists('ratib_public_site_base_url')) {
        require_once __DIR__ . '/ratib-public-base-url.php';
    }
    ratib_emit_nav_brand_critical_css();
    echo '<style id="ratib-nav-sync-guard">';
    echo '#ratibNavMenu:not([data-ratib-nav-sync="1"]){visibility:hidden;}';
    echo '#ratibNavMenu[data-ratib-nav-sync="1"]{visibility:visible!important;opacity:1!important;pointer-events:auto!important;}';
    echo '</style>';
}

function ratib_home_nav_emit_sync_script(string $profileUrl = ''): void
{
    if ($profileUrl === '') {
        if (!function_exists('ratib_public_site_base_url')) {
            require_once __DIR__ . '/ratib-public-base-url.php';
        }
        $profileUrl = rtrim(ratib_public_site_base_url(), '/') . '/profile/#company-profile';
    }
    $profileJson = json_encode($profileUrl, JSON_UNESCAPED_SLASHES);
    if (!function_exists('ratib_public_site_base_url')) {
        require_once __DIR__ . '/ratib-public-base-url.php';
    }
    $baseUrl = ratib_public_site_base_url();
    $iaFixPath = __DIR__ . '/../js/pages/ratib-public-nav-ia-fix.js';
    clearstatcache(true, $iaFixPath);
    $iaFixQ = (string) (int) (@filemtime($iaFixPath) ?: time());
    if (isset($GLOBALS['ratibHomeUiRev']) && (string) $GLOBALS['ratibHomeUiRev'] !== '') {
        $iaFixQ .= '-' . preg_replace('/[^a-zA-Z0-9._-]/', '', (string) $GLOBALS['ratibHomeUiRev']);
    }
    $iaFixInline = is_readable($iaFixPath) ? (string) file_get_contents($iaFixPath) : '';
    ?>
<script>window.ratibProfileNavUrl=<?php echo $profileJson; ?>;</script>
<?php if ($iaFixInline !== '') { ?>
<script id="ratib-nav-ia-fix-inline"><?php echo $iaFixInline; ?></script>
<?php } ?>
<script src="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/js/pages/ratib-public-nav-ia-fix.js?v=' . $iaFixQ, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php
}
