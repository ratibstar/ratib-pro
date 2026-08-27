(function () {
    'use strict';

    var MANUAL = '__manual__';

    function selectedOptionLabel(sel) {
        var opt = sel.options[sel.selectedIndex];
        if (!opt) {
            return '';
        }
        var fromData = opt.getAttribute('data-label');
        if (fromData != null && String(fromData) !== '') {
            return String(fromData);
        }
        return String(opt.textContent || '').trim();
    }

    function syncHybrid(wrap, forceFill) {
        var sel = wrap.querySelector('.rateb-hybrid-select');
        var manual = wrap.querySelector('.rateb-hybrid-manual');
        var hidden = wrap.querySelector('.rateb-hybrid-value');
        if (!sel || !manual || !hidden) {
            return;
        }
        var detailsOnPick = wrap.getAttribute('data-details-on-pick') === '1';

        if (sel.value === MANUAL) {
            manual.style.display = '';
            manual.disabled = false;
            manual.required = true;
            if (forceFill && !manual.dataset.touched) {
                manual.value = '';
            }
            hidden.value = manual.value;
            return;
        }

        if (detailsOnPick) {
            if (!sel.value) {
                manual.style.display = 'none';
                manual.disabled = true;
                manual.required = false;
                if (forceFill) {
                    manual.value = '';
                    delete manual.dataset.touched;
                }
                hidden.value = '';
                return;
            }
            manual.style.display = '';
            manual.disabled = false;
            manual.required = true;
            if (forceFill || !manual.dataset.touched) {
                manual.value = selectedOptionLabel(sel);
                delete manual.dataset.touched;
            }
            hidden.value = manual.value;
            return;
        }

        manual.style.display = 'none';
        manual.disabled = true;
        manual.required = false;
        hidden.value = sel.value;
    }

    function initHybrid(root) {
        (root || document).querySelectorAll('.rateb-hybrid-field').forEach(function (wrap) {
            var sel = wrap.querySelector('.rateb-hybrid-select');
            var manual = wrap.querySelector('.rateb-hybrid-manual');
            if (!sel || !manual || sel.dataset.hybridBound === '1') {
                return;
            }
            sel.dataset.hybridBound = '1';
            sel.addEventListener('change', function () {
                delete manual.dataset.touched;
                syncHybrid(wrap, true);
            });
            manual.addEventListener('input', function () {
                manual.dataset.touched = '1';
                var hidden = wrap.querySelector('.rateb-hybrid-value');
                if (hidden) {
                    hidden.value = manual.value;
                }
            });
            syncHybrid(wrap, false);
        });
    }

    function syncAllHybrids(form) {
        if (!form || !form.querySelectorAll) {
            return;
        }
        form.querySelectorAll('.rateb-hybrid-field').forEach(function (wrap) {
            syncHybrid(wrap, false);
        });
    }

    function bootAfterNav() {
        document.querySelectorAll('.rateb-hybrid-select').forEach(function (sel) {
            delete sel.dataset.hybridBound;
        });
        initHybrid(document);
    }

    document.addEventListener('submit', function (e) {
        syncAllHybrids(e.target);
    }, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initHybrid(document);
        });
    } else {
        initHybrid(document);
    }
    document.addEventListener('rateb:nav:afterEnter', bootAfterNav);
    document.addEventListener('rateb:soft-nav:afterEnter', bootAfterNav);

    window.ratebInitHybridFields = initHybrid;
})();
