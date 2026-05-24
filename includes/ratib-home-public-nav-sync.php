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

function ratib_home_nav_emit_sync_guard_style(): void
{
    if (!function_exists('ratib_public_site_base_url')) {
        require_once __DIR__ . '/ratib-public-base-url.php';
    }
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
    ?>
<script>window.ratibProfileNavUrl=<?php echo $profileJson; ?>;</script>
<script src="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/js/pages/ratib-public-nav-ia-fix.js?v=' . $iaFixQ, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/js/pages/ratib-mega-nav.js?v=' . (string) ($GLOBALS['ratibMegaNavJsQuery'] ?? $iaFixQ), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php
}
