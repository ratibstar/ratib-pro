<?php
declare(strict_types=1);

if (!function_exists('str_contains')) {
    require_once __DIR__ . '/ratib-php74-compat.php';
}

/**
 * Inject Global AI submit patch into HTML responses (works when global-ai-action.js on server is stale).
 */
function ratib_register_global_ai_html_patch(): void
{
    static $done = false;
    if ($done || PHP_SAPI === 'cli') {
        return;
    }
    $done = true;

    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if (str_contains($uri, '/api/') || str_contains($uri, 'worker-onboarding') || str_contains($uri, '.php') && str_contains($uri, '/public/')) {
        return;
    }

    ob_start(static function (string $html): string {
        if ($html === '' || strlen($html) < 200) {
            return $html;
        }
        if (stripos($html, 'globalAiRunBtn') === false || stripos($html, 'ratib-global-ai-fetch-v7') !== false || stripos($html, 'ratibGlobalAiV7') !== false) {
            return $html;
        }
        $patchFile = __DIR__ . '/global_ai_run_patch.php';
        if (!is_file($patchFile)) {
            return $html;
        }
        ob_start();
        include $patchFile;
        $patch = ob_get_clean();
        if ($patch === '') {
            return $html;
        }
        if (stripos($html, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $patch . '</body>', $html, 1) ?? $html;
        }
        return $html . $patch;
    });
}

ratib_register_global_ai_html_patch();

/**
 * Inject Profile nav lock on public marketing pages (fixes stale cached home HTML).
 */
function ratib_register_public_profile_nav_patch(): void
{
    static $done = false;
    if ($done || PHP_SAPI === 'cli') {
        return;
    }
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if (
        str_contains($uri, '/api/')
        || str_contains($uri, '/control-panel/')
        || str_contains($uri, 'worker-onboarding')
    ) {
        return;
    }
    $done = true;

    ob_start(static function (string $html): string {
        if ($html === '' || strlen($html) < 400) {
            return $html;
        }
        if (
            stripos($html, 'ratib-profile-head-lock') !== false
            || stripos($html, 'ratib-profile-nav=v13-onclick') !== false
            || stripos($html, 'ratib-nav__brand-profile') === false
        ) {
            return $html;
        }
        $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'out.ratib.sa';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $profile = json_encode($scheme . '://' . $host . '/profile/', JSON_UNESCAPED_SLASHES);
        $patch = '<script id="ratib-profile-head-lock">(function(){var P=' . $profile . ';function go(ev){var a=ev.target&&ev.target.closest&&ev.target.closest(".ratib-nav__brand-profile,.ratib-nav__link--about,[data-ratib-profile-nav],[data-ratib-go-profile],a.ratib-footer-link--about");if(!a)return;ev.preventDefault();ev.stopImmediatePropagation();window.location.assign(P);}document.addEventListener("mousedown",go,true);document.addEventListener("click",go,true);document.querySelectorAll(".ratib-nav__brand-profile,.ratib-nav__link--about,.ratib-footer-link--about").forEach(function(a){a.setAttribute("href",P);});})();</script>';
        if (stripos($html, '</head>') !== false) {
            return preg_replace('/<\/head>/i', $patch . '</head>', $html, 1) ?? $html;
        }
        if (stripos($html, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $patch . '</body>', $html, 1) ?? $html;
        }
        return $html . $patch;
    });
}

ratib_register_public_profile_nav_patch();
