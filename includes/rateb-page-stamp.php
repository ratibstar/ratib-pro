<?php
/**
 * Fixed corner badge — inline styles only (survives stale CSS cache).
 *
 * @param 'profile'|'home' $kind
 */
declare(strict_types=1);

if (!function_exists('rateb_emit_page_stamp')) {
    function rateb_emit_page_stamp(string $kind): void
    {
        $buildPath = dirname(__DIR__) . '/public/rateb-build.txt';
        $build = is_file($buildPath) ? trim((string) file_get_contents($buildPath)) : 'build-unknown';
        $isProfile = $kind === 'profile';
        $label = $isProfile ? 'COMPANY PROFILE' : 'MARKETING HOME';
        $bg = $isProfile ? '#7c3aed' : '#0369a1';
        $url = $isProfile ? '/profile/' : '/home';
        echo '<div id="rateb-page-stamp" data-rateb-page-stamp="' . ($isProfile ? 'profile' : 'home') . '" ';
        echo 'style="position:fixed;bottom:12px;right:12px;z-index:2147483647;max-width:min(92vw,320px);padding:10px 14px;border-radius:10px;';
        echo 'font:bold 14px/1.25 system-ui,-apple-system,sans-serif;color:#fff;background:' . $bg . ';';
        echo 'box-shadow:0 6px 28px rgba(0,0,0,.55);border:2px solid #fff;pointer-events:none">';
        echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        echo '<div style="font-size:11px;font-weight:600;margin-top:5px;opacity:.92;word-break:break-all">';
        echo htmlspecialchars($build, ENT_QUOTES, 'UTF-8');
        echo '</div><div style="font-size:10px;margin-top:4px;opacity:.85">';
        echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        echo '</div></div>';
    }
}
