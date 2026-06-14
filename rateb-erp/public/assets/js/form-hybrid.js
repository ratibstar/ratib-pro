(function () {
    'use strict';

    var MANUAL = '__manual__';

    function syncHybrid(wrap) {
        var sel = wrap.querySelector('.rateb-hybrid-select');
        var manual = wrap.querySelector('.rateb-hybrid-manual');
        var hidden = wrap.querySelector('.rateb-hybrid-value');
        if (!sel || !manual || !hidden) {
            return;
        }
        if (sel.value === MANUAL) {
            manual.style.display = '';
            manual.required = true;
            hidden.value = manual.value;
        } else {
            manual.style.display = 'none';
            manual.required = false;
            hidden.value = sel.value;
        }
    }

    function initHybrid(root) {
        (root || document).querySelectorAll('.rateb-hybrid-field').forEach(function (wrap) {
            var sel = wrap.querySelector('.rateb-hybrid-select');
            var manual = wrap.querySelector('.rateb-hybrid-manual');
            if (!sel || sel.dataset.hybridBound === '1') {
                return;
            }
            sel.dataset.hybridBound = '1';
            sel.addEventListener('change', function () {
                syncHybrid(wrap);
            });
            manual.addEventListener('input', function () {
                if (sel.value === MANUAL) {
                    wrap.querySelector('.rateb-hybrid-value').value = manual.value;
                }
            });
            syncHybrid(wrap);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initHybrid(document);
    });

    window.ratebInitHybridFields = initHybrid;
})();
