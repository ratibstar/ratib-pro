<?php
/**
 * Force /profile/ links to same tab — runs first in <head>, fixes LiteSpeed-cached HTML with window.open.
 */
declare(strict_types=1);

if (!function_exists('ratib_emit_profile_force_same_tab')) {
    function ratib_emit_profile_force_same_tab(string $baseUrl): void
    {
        $profileUrl = rtrim($baseUrl, '/') . '/profile/#company-profile';
        $profileJson = json_encode($profileUrl, JSON_UNESCAPED_SLASHES);
        echo '<script id="ratib-profile-force-same-tab">(function(){var P=' . $profileJson . ';var o=window.open;window.open=function(u,t,f){if(u!=null&&/\\/profile\\//i.test(String(u))){window.location.assign(String(u));return null;}return o.apply(window,arguments);};function clean(){document.querySelectorAll(".ratib-nav__brand-profile,.ratib-nav__link--about,.ratib-nav__go-profile,[data-ratib-profile-nav],[data-ratib-go-profile],.ratib-footer-link--about,a.ratib-mega-nav__card").forEach(function(a){var ti=a.querySelector&&a.querySelector(".ratib-mega-nav__card-title");if(a.matches("a.ratib-mega-nav__card")&&(!ti||!/company profile/i.test(ti.textContent||"")))return;a.setAttribute("href",P);a.removeAttribute("target");a.removeAttribute("rel");a.removeAttribute("onclick");});}clean();document.addEventListener("DOMContentLoaded",clean);setTimeout(clean,0);setTimeout(clean,300);setTimeout(clean,1200);})();</script>';
    }
}
