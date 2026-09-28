/**
 * Unified HR: after QR / green link (?setup=1), open sa.rateb.hr.mobile via HTTPS intent (not ratebapp).
 */
(function () {
    'use strict';

    if (!/\bsetup=1\b/.test(location.search || '')) {
        return;
    }
    var head = document.querySelector('[data-rateb-act-code]');
    if (!head || head.getAttribute('data-rateb-unified-hr') !== '1') {
        return;
    }
    if (!/Android/i.test(navigator.userAgent || '')) {
        return;
    }
    var intent = head.getAttribute('data-rateb-hr-android-intent');
    if (!intent || intent.indexOf('scheme=https') === -1) {
        return;
    }
    var banner = document.getElementById('rateb-unified-download');
    if (banner) {
        var msg = document.createElement('p');
        msg.className = 'small text-center mb-0 mt-2';
        msg.setAttribute('data-rateb-handoff-msg', '1');
        msg.textContent = banner.getAttribute('data-opening-msg') || 'Opening RATEB HR…';
        banner.appendChild(msg);
    }
    window.setTimeout(function () {
        window.location.href = intent;
    }, 500);
})();
