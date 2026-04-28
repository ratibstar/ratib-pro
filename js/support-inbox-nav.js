/**
 * EN: Implements frontend interaction behavior in `js/support-inbox-nav.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/support-inbox-nav.js`.
 */
/**
 * Navbar badge: unread live-support chats for this agency (session country).
 */
(function() {
    'use strict';

    var link = document.getElementById('navSupportInboxLink');
    var badge = document.getElementById('supportInboxNavBadge');
    if (!link || !badge) return;

    function apiBase() {
        var el = document.getElementById('app-config');
        if (el && el.getAttribute('data-base-url')) {
            var b = (el.getAttribute('data-base-url') || '').replace(/\/+$/, '');
            return window.location.origin + (b ? (b.charAt(0) === '/' ? b : '/' + b) : '');
        }
        return window.location.origin;
    }

    function poll() {
        fetch(apiBase() + '/api/support-chat-agency-unread.php', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d || !d.success) return;
                var n = parseInt(d.unread, 10) || 0;
                if (n > 0) {
                    badge.textContent = n > 99 ? '99+' : String(n);
                    badge.classList.remove('d-none');
                } else {
                    badge.classList.add('d-none');
                }
            })
            .catch(function() { /* ignore */ });
    }

    poll();
    /** Frequent poll so “Talk to support” shows in the navbar within a few seconds without refresh. */
    setInterval(poll, 3000);
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') poll();
    });
    window.addEventListener('focus', poll);
})();
