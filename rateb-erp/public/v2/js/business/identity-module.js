/*!
 * Compatibility stub — canonical owner:
 * /public/assets/offline/shared/identity/identity-module.js
 */
(function (root) {
    'use strict';

    if (root.RatebOfflineV2Identity && root.RatebOfflineV2Identity.__locked) {
        return;
    }

    var current = root.document && root.document.currentScript;
    var base = current && current.src ? current.src : root.location.href;
    var script = root.document.createElement('script');
    script.src = new URL('../../../assets/offline/shared/identity/identity-module.js', base).href;
    script.async = false;
    script.setAttribute('data-rateb-v2-compat', 'identity');
    root.document.head.appendChild(script);
})(typeof window !== 'undefined' ? window : this);
