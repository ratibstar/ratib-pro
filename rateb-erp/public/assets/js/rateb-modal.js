(function () {
    'use strict';

    /**
     * Prepare a Bootstrap modal: move to body, bind a11y attrs, return a singleton instance.
     * @param {HTMLElement|null} el
     * @returns {import('bootstrap').Modal|null}
     */
    window.ratebModalPrepare = function (el) {
        if (!el || typeof bootstrap === 'undefined') {
            return null;
        }
        if (el.parentElement && el.parentElement !== document.body) {
            document.body.appendChild(el);
        }
        if (!el.classList.contains('show')) {
            el.setAttribute('aria-hidden', 'true');
        }
        if (!el.dataset.ratebModalA11y) {
            el.dataset.ratebModalA11y = '1';
            el.addEventListener('show.bs.modal', function () {
                el.removeAttribute('aria-hidden');
                el.setAttribute('aria-modal', 'true');
            });
            el.addEventListener('hidden.bs.modal', function () {
                el.setAttribute('aria-hidden', 'true');
                el.removeAttribute('aria-modal');
            });
        }
        return bootstrap.Modal.getOrCreateInstance(el);
    };
})();
