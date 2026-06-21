/**
 * Boots unified agent desktop inbox + AI Copilot on DOM ready.
 */
(function (global) {
    'use strict';

    function bootAgentDesktop() {
        var root = document.getElementById('rcc-agent-desktop');
        if (!root || !global.RccAgentInbox) {
            return;
        }

        var copilot = null;
        if (global.RccAiCopilot) {
            copilot = new global.RccAiCopilot({
                tenantId: parseInt(root.getAttribute('data-tenant'), 10) || 0,
                agentId: parseInt(root.getAttribute('data-agent'), 10) || 0,
                apiBase: root.getAttribute('data-assistant-api') || '',
                root: document.getElementById('rcc-ai-copilot'),
                inboxRoot: root
            });
            copilot.init();
        }

        var inbox = new global.RccAgentInbox({
            tenantId: parseInt(root.getAttribute('data-tenant'), 10) || 0,
            agentId: parseInt(root.getAttribute('data-agent'), 10) || 0,
            apiBase: root.getAttribute('data-inbox-api') || '',
            wsUrl: root.getAttribute('data-ws') || 'ws://127.0.0.1:9702',
            root: root,
            onConversationSelect: function (conv) {
                if (copilot) {
                    copilot.onConversationSelected(conv);
                }
            },
            onRealtimeEvent: function (ev) {
                if (copilot) {
                    copilot.onRealtimeEvent(ev);
                }
            }
        });
        inbox.init();
        global.__rccAgentInbox = inbox;
        global.__rccAiCopilot = copilot;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootAgentDesktop);
    } else {
        bootAgentDesktop();
    }
})(typeof window !== 'undefined' ? window : globalThis);
