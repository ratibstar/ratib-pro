/**
 * Focused marketing density — expand/collapse deep sections on home & profile.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'ratib-marketing-expanded';

    function applyExpanded(expanded) {
        document.body.classList.toggle('ratib-marketing--expanded', expanded);
        document.querySelectorAll('[data-ratib-marketing-expand]').forEach(function (btn) {
            btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            var more = btn.querySelector('[data-ratib-marketing-expand-label="more"]');
            var less = btn.querySelector('[data-ratib-marketing-expand-label="less"]');
            if (more) {
                more.hidden = expanded;
            }
            if (less) {
                less.hidden = !expanded;
            }
        });
    }

    function readUrlExpanded() {
        try {
            var params = new URLSearchParams(window.location.search);
            var density = (params.get('density') || '').toLowerCase();
            if (density === 'full' || density === 'expanded' || density === 'all') {
                return true;
            }
            if (density === 'focused' || density === 'compact' || density === 'summary' || density === 'short') {
                return false;
            }
        } catch (e) {
            return false;
        }
        return null;
    }

    function readStoredExpanded() {
        try {
            return localStorage.getItem(STORAGE_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function storeExpanded(expanded) {
        try {
            localStorage.setItem(STORAGE_KEY, expanded ? '1' : '0');
        } catch (e) {
            /* ignore */
        }
    }

    function initMarketingFocused() {
        if (!document.body.classList.contains('ratib-marketing--focused')) {
            return;
        }

        var fromUrl = readUrlExpanded();
        var expanded = fromUrl !== null ? fromUrl : readStoredExpanded();
        applyExpanded(expanded);

        document.querySelectorAll('[data-ratib-marketing-expand]').forEach(function (btn) {
            if (btn.getAttribute('data-ratib-marketing-expand-init') === '1') {
                return;
            }
            btn.setAttribute('data-ratib-marketing-expand-init', '1');
            btn.addEventListener('click', function () {
                var next = !document.body.classList.contains('ratib-marketing--expanded');
                applyExpanded(next);
                storeExpanded(next);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMarketingFocused);
    } else {
        initMarketingFocused();
    }
})();
