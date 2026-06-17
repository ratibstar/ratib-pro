/**
 * Control Panel client-side i18n (fed by window.__CP_I18N from PHP).
 */
(function (global) {
    'use strict';

    function cpT(key, replacements) {
        var bag = (global.__CP_I18N && global.__CP_I18N.strings) || {};
        var text = bag[key] || key;
        if (replacements && typeof replacements === 'object') {
            Object.keys(replacements).forEach(function (name) {
                text = text.split('{' + name + '}').join(String(replacements[name]));
            });
        }
        return text;
    }

    global.cpT = cpT;
})(window);
