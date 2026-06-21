/**
 * Boots unified agent desktop inbox + AI Copilot on DOM ready.
 */
(function (global) {
    'use strict';

    function syncRccTheme(root) {
        if (!root) {
            return;
        }
        if (document.body.classList.contains('control-system-body')) {
            root.setAttribute('data-theme', 'dark');
            return;
        }
        var stored = null;
        try {
            stored = localStorage.getItem('rcc-agent-theme');
        } catch (e) { /* ignore */ }
        if (stored === 'light' || stored === 'dark') {
            root.setAttribute('data-theme', stored);
            return;
        }
        root.setAttribute(
            'data-theme',
            global.matchMedia && global.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
        );
    }

    function normalizeRccWsUrl(raw, mode) {
        var u = (raw || '').trim().toLowerCase();
        if (mode === 'polling' || !u || u === 'polling' || u === 'off' || u === 'disabled' || u === 'none') {
            return 'polling';
        }
        if (u.indexOf('ws://') !== 0 && u.indexOf('wss://') !== 0) {
            return 'polling';
        }
        return (raw || '').trim();
    }

    function bootAgentDesktop() {
        var root = document.getElementById('rcc-agent-desktop');
        if (!root || !global.RccAgentInbox) {
            return;
        }
        syncRccTheme(root);
        if (global.matchMedia) {
            global.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
                if (!document.body.classList.contains('control-system-body')) {
                    syncRccTheme(root);
                }
            });
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

        var rtMode = root.getAttribute('data-realtime-mode') || 'polling';
        var wsUrl = normalizeRccWsUrl(root.getAttribute('data-ws'), rtMode);

        var inbox = new global.RccAgentInbox({
            tenantId: parseInt(root.getAttribute('data-tenant'), 10) || 0,
            agentId: parseInt(root.getAttribute('data-agent'), 10) || 0,
            apiBase: root.getAttribute('data-inbox-api') || '',
            wsUrl: wsUrl,
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
