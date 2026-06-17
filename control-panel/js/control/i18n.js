/**
 * Control Panel client-side i18n — full dictionary + DOM phrase translation.
 */
(function (global) {
    'use strict';

    function bag() {
        return (global.__CP_I18N && global.__CP_I18N.strings) || {};
    }

    function phraseMap() {
        return (global.__CP_I18N && global.__CP_I18N.phraseMap) || {};
    }

    function locale() {
        return (global.__CP_I18N && global.__CP_I18N.locale) || 'en';
    }

    function cpT(key, replacements) {
        var text = bag()[key] || key;
        if (replacements && typeof replacements === 'object') {
            Object.keys(replacements).forEach(function (name) {
                text = text.split('{' + name + '}').join(String(replacements[name]));
            });
        }
        return text;
    }

    /** Translate a literal English UI phrase (for JS-built HTML). */
    function cpPhrase(en) {
        if (locale() !== 'ar' || !en) return en;
        var map = phraseMap();
        return map[en] || en;
    }

    function shouldSkipEl(el) {
        if (!el || !el.matches) return false;
        return el.matches('input[type=date],input[type=datetime-local],input[type=number],input[type=month],input[type=time],input[type=week],.cp-ltr-field,[lang="en"],[data-cp-no-i18n]');
    }

    function translateAttributes(el) {
        if (!el || shouldSkipEl(el)) return;
        if (el.closest && el.closest('[translate="no"]')) return;
        if (el.getAttribute && el.getAttribute('lang') === 'en') return;
        ['placeholder', 'title', 'aria-label'].forEach(function (attr) {
            var val = el.getAttribute && el.getAttribute(attr);
            if (val && phraseMap()[val]) {
                el.setAttribute(attr, phraseMap()[val]);
            }
        });
    }

    function translateTextNodes(root) {
        if (locale() !== 'ar') return;
        var map = phraseMap();
        var keys = Object.keys(map).sort(function (a, b) { return b.length - a.length; });
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: function (node) {
                if (!node.parentElement) return NodeFilter.FILTER_REJECT;
                var p = node.parentElement;
                if (p.closest('script,style,pre,code,textarea,svg,input,select,[translate="no"],[lang="en"],.cp-ltr-field,[data-cp-no-i18n]')) {
                    return NodeFilter.FILTER_REJECT;
                }
                var t = (node.nodeValue || '').trim();
                if (!t) return NodeFilter.FILTER_REJECT;
                return NodeFilter.FILTER_ACCEPT;
            }
        });
        var node;
        while ((node = walker.nextNode())) {
            var original = node.nodeValue;
            var updated = original;
            for (var i = 0; i < keys.length; i++) {
                var en = keys[i];
                if (en && updated.indexOf(en) !== -1) {
                    updated = updated.split(en).join(map[en]);
                }
            }
            if (updated !== original) {
                node.nodeValue = updated;
            }
        }
    }

    function cpApplyDomI18n(root) {
        if (locale() !== 'ar') return;
        root = root || document.body;
        if (!root || !root.querySelectorAll) return;
        if (root.getAttribute && root.getAttribute('data-cp-no-i18n')) return;
        root.querySelectorAll('[placeholder],[title],[aria-label]').forEach(function (el) {
            if (!el.closest('[data-cp-no-i18n]')) translateAttributes(el);
        });
        if (!root.closest || !root.closest('[data-cp-no-i18n]')) {
            translateTextNodes(root);
        }
        root.querySelectorAll('option').forEach(function (opt) {
            if (opt.closest('[data-cp-no-i18n]')) return;
            var t = (opt.textContent || '').trim();
            if (phraseMap()[t]) opt.textContent = phraseMap()[t];
        });
    }

    global.cpT = cpT;
    global.cpPhrase = cpPhrase;
    global.cpApplyDomI18n = cpApplyDomI18n;

    function boot() {
        cpApplyDomI18n(document.body);
        if (typeof MutationObserver !== 'undefined' && document.body) {
            var timer = null;
            new MutationObserver(function () {
                if (timer) clearTimeout(timer);
                timer = setTimeout(function () { cpApplyDomI18n(document.body); }, 120);
            }).observe(document.body, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window);
