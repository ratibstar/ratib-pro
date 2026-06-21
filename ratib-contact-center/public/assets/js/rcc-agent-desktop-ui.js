/**
 * Boots unified agent desktop inbox on DOM ready.
 */
(function (global) {
    'use strict';

    function bootAgentDesktop() {
        var root = document.getElementById('rcc-agent-desktop');
        if (!root || !global.RccAgentInbox) {
            return;
        }
        var inbox = new global.RccAgentInbox({
            tenantId: parseInt(root.getAttribute('data-tenant'), 10) || 0,
            agentId: parseInt(root.getAttribute('data-agent'), 10) || 0,
            apiBase: root.getAttribute('data-inbox-api') || '',
            wsUrl: root.getAttribute('data-ws') || 'ws://127.0.0.1:9702',
            root: root
        });
        inbox.init();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootAgentDesktop);
    } else {
        bootAgentDesktop();
    }
})(typeof window !== 'undefined' ? window : globalThis);
