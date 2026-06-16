<?php
/**
 * Inline CSS/JS: keep dismiss controls inside their panels and suppress stray "Close" tooltips.
 */
declare(strict_types=1);

if (!function_exists('rateb_emit_overlay_dismiss_guard')) {
    function rateb_emit_overlay_dismiss_guard(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        echo '<style id="rateb-overlay-dismiss-guard">';
        echo '.chat-widget-container:not(.active) .chat-widget-header-actions{visibility:hidden!important;pointer-events:none!important}';
        echo '.global-ai-modal:not(.show){pointer-events:none!important}';
        echo '.global-ai-modal:not(.show) .global-ai-modal-close{visibility:hidden!important}';
        echo '</style>';
        echo '<script id="rateb-overlay-dismiss-guard-js">';
        echo '(function(){function strip(){document.querySelectorAll(';
        echo '".chat-widget-close,.chat-widget-clear,.global-ai-modal-close,[data-rateb-program-lightbox-close]"';
        echo ').forEach(function(el){el.removeAttribute("title");});}';
        echo 'strip();document.addEventListener("DOMContentLoaded",strip);setTimeout(strip,0);setTimeout(strip,400);})();';
        echo '</script>';
    }
}

rateb_emit_overlay_dismiss_guard();
