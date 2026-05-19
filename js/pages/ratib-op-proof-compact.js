/**
 * Operational proof compact blocks — expand/collapse via More / Less.
 */
(function () {
    'use strict';

    function setBlockExpanded(block, open) {
        block.classList.toggle('is-expanded', open);
        block.querySelectorAll('.ratib-op-item--more').forEach(function (el) {
            el.hidden = !open;
        });
    }

    function initOpProofCompact() {
        document.querySelectorAll('[data-ratib-op-collapsible]').forEach(function (block) {
            if (block.getAttribute('data-ratib-op-compact-init') === '1') {
                return;
            }
            block.setAttribute('data-ratib-op-compact-init', '1');

            var btn = block.querySelector('[data-ratib-op-more]');
            if (!btn) {
                return;
            }

            setBlockExpanded(block, false);

            btn.addEventListener('click', function () {
                var open = !block.classList.contains('is-expanded');
                setBlockExpanded(block, open);
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                var moreLbl = btn.querySelector('.ratib-op-more-btn__more');
                var lessLbl = btn.querySelector('.ratib-op-more-btn__less');
                if (moreLbl) {
                    moreLbl.hidden = open;
                }
                if (lessLbl) {
                    lessLbl.hidden = !open;
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOpProofCompact);
    } else {
        initOpProofCompact();
    }
})();
