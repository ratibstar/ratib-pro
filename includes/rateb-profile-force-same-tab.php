<?php
/**
 * Force /profile/ links to same tab — runs first in <head>, fixes LiteSpeed-cached HTML with window.open.
 */
declare(strict_types=1);

if (!function_exists('rateb_emit_profile_force_same_tab')) {
    function rateb_emit_profile_force_same_tab(string $baseUrl): void
    {
        $profileUrl = rtrim($baseUrl, '/') . '/profile/#company-profile';
        $profileJson = json_encode($profileUrl, JSON_UNESCAPED_SLASHES);
        echo '<script id="rateb-profile-force-same-tab">(function(){var P=' . $profileJson . ';var o=window.open;window.open=function(u,t,f){if(u!=null&&/\\/profile\\//i.test(String(u))){window.location.assign(String(u));return null;}return o.apply(window,arguments);};function clean(){document.querySelectorAll(".rateb-nav__brand-profile,.rateb-nav__link--about,.rateb-nav__go-profile,[data-rateb-profile-nav],[data-rateb-go-profile],.rateb-footer-link--about,a.rateb-mega-nav__card").forEach(function(a){var ti=a.querySelector&&a.querySelector(".rateb-mega-nav__card-title");if(a.matches("a.rateb-mega-nav__card")&&(!ti||!/company profile/i.test(ti.textContent||"")))return;a.setAttribute("href",P);a.removeAttribute("target");a.removeAttribute("rel");a.removeAttribute("onclick");});}clean();document.addEventListener("DOMContentLoaded",clean);setTimeout(clean,0);setTimeout(clean,300);setTimeout(clean,1200);})();</script>';
    }
}
