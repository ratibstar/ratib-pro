<?php
declare(strict_types=1);

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
