/** Ensures cpApplyDomI18n runs after dynamic module scripts on late-loaded pages. */
(function () {
    'use strict';
    if (typeof window.cpApplyDomI18n !== 'function') return;
    window.addEventListener('load', function () {
        window.cpApplyDomI18n(document.body);
    });
})();
