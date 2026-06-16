/**
 * Focused marketing density — URL ?density=full is authoritative (server renders deep sections).
 * Optional body class for CSS-only collapse on home when deep HTML is still present.
 */
(function () {
    'use strict';

    function initMarketingFocused() {
        if (!document.body.classList.contains('rateb-marketing--focused')) {
            return;
        }
        try {
            var density = (new URLSearchParams(window.location.search).get('density') || '').toLowerCase();
            if (density === 'full' || density === 'expanded' || density === 'all') {
                document.body.classList.add('rateb-marketing--expanded');
            }
        } catch (e) {
            /* ignore */
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMarketingFocused);
    } else {
        initMarketingFocused();
    }
})();
