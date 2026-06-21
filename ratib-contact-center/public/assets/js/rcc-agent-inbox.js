/**
 * RATIB Contact Center — Unified Agent Inbox (EventBus-driven, no polling).
 */
(function (global) {
    'use strict';

    function RccAgentInbox(options) {
        this.tenantId = options.tenantId || 0;
        this.agentId = options.agentId || 0;
        this.apiBase = options.apiBase || '';
        this.wsUrl = options.wsUrl || 'ws://127.0.0.1:9702';
        this.root = options.root || document.getElementById('rcc-agent-desktop');
        this.onConversationSelect = options.onConversationSelect || function () {};
        this.onRealtimeEvent = options.onRealtimeEvent || function () {};
        this._conversations = {};
        this._activeId = null;
        this._client = null;
    }

    RccAgentInbox.prototype.init = function () {
        var self = this;
        if (!self.root) {
            return;
        }
        self._bindUi();
        self._loadInbox();
        if (global.RccRealtimeClient) {
            self._client = new global.RccRealtimeClient({
                url: self.wsUrl,
                tenantId: self.tenantId,
                rooms: ['agent:' + self.agentId, 'tenant:' + self.tenantId],
                onEvent: function (ev) { self._onRealtimeEvent(ev); }
            });
            self._client.connect();
        }
    };

    RccAgentInbox.prototype._bindUi = function () {
        var self = this;
        var list = self.root.querySelector('#rcc-inbox-list');
        if (list) {
            list.addEventListener('click', function (e) {
                var item = e.target.closest('[data-conversation-id]');
                if (!item) {
                    return;
                }
                self.selectConversation(parseInt(item.getAttribute('data-conversation-id'), 10));
            });
        }
        var sendBtn = self.root.querySelector('#rcc-inbox-send');
        if (sendBtn) {
            sendBtn.addEventListener('click', function () { self.sendReply(); });
        }
    };

    RccAgentInbox.prototype._loadInbox = function () {
        var self = this;
        self._api('inbox', { tenant_id: self.tenantId, agent_id: self.agentId }).then(function (res) {
            if (!res || !res.ok) {
                var list = self.root.querySelector('#rcc-inbox-list');
                if (list) {
                    list.innerHTML = '<div class="rcc-inbox__empty"><span class="rcc-inbox__empty-icon">⚠</span>' +
                        self._esc((res && res.error) || 'Could not load inbox') + '</div>';
                }
                return;
            }
            (res.conversations || []).forEach(function (c) {
                self._conversations[c.conversation_id] = c;
            });
            self._renderList();
        });
    };

    RccAgentInbox.prototype.selectConversation = function (conversationId) {
        var self = this;
        self._activeId = conversationId;
        self._api('thread', {
            tenant_id: self.tenantId,
            conversation_id: conversationId
        }).then(function (res) {
            if (!res.ok) {
                return;
            }
            self._renderThread(res.messages || [], res.conversation || {});
            self.onConversationSelect(res.conversation || {});
        });
        self._renderList();
    };

    RccAgentInbox.prototype.sendReply = function () {
        var self = this;
        if (!self._activeId) {
            return;
        }
        var input = self.root.querySelector('#rcc-inbox-reply');
        var text = input ? input.value.trim() : '';
        if (!text) {
            return;
        }
        var conv = self._conversations[self._activeId] || {};
        var channel = conv.last_channel || 'chat';
        self._api('send', {
            tenant_id: self.tenantId,
            agent_id: self.agentId,
            conversation_id: self._activeId,
            channel: channel,
            message: text
        }).then(function () {
            if (input) {
                input.value = '';
            }
            self.selectConversation(self._activeId);
        });
    };

    RccAgentInbox.prototype._onRealtimeEvent = function (ev) {
        var self = this;
        self.onRealtimeEvent(ev);
        var types = [
            'CONVERSATION_CREATED', 'CONVERSATION_UPDATED', 'MESSAGE_RECEIVED',
            'MESSAGE_SENT', 'CONVERSATION_ASSIGNED', 'CONVERSATION_PRIORITY_CHANGED'
        ];
        if (types.indexOf(ev.type) === -1) {
            return;
        }
        var conv = (ev.payload && ev.payload.conversation) ? ev.payload.conversation : null;
        if (conv && conv.conversation_id) {
            self._conversations[conv.conversation_id] = conv;
            self._renderList();
            if (self._activeId === conv.conversation_id) {
                self.selectConversation(conv.conversation_id);
            }
        } else {
            self._loadInbox();
        }
    };

    RccAgentInbox.prototype._renderList = function () {
        var self = this;
        var list = self.root.querySelector('#rcc-inbox-list');
        if (!list) {
            return;
        }
        var html = '';
        Object.keys(self._conversations).forEach(function (id) {
            var c = self._conversations[id];
            var active = self._activeId === c.conversation_id ? ' is-active' : '';
            var channels = (c.channels || []).join(', ');
            html += '<div class="rcc-inbox__item' + active + '" data-conversation-id="' + c.conversation_id + '">' +
                '<div class="rcc-inbox__item-head">' +
                '<span class="rcc-inbox__identity">' + self._esc(c.customer_identity) + '</span>' +
                '<span class="rcc-inbox__priority rcc-inbox__priority--' + self._esc(c.priority) + '">' + self._esc(c.priority) + '</span>' +
                '</div>' +
                '<div class="rcc-inbox__preview">' + self._esc(c.last_message || '') + '</div>' +
                '<div class="rcc-inbox__meta">' + self._esc(channels) + ' · SLA ' + self._esc(c.sla_status) + '</div>' +
                '</div>';
        });
        list.innerHTML = html || '<div class="rcc-inbox__empty"><span class="rcc-inbox__empty-icon">📭</span>No conversations yet</div>';
    };

    RccAgentInbox.prototype._renderThread = function (messages, conversation) {
        var self = this;
        var thread = self.root.querySelector('#rcc-inbox-thread');
        var erp = self.root.querySelector('#rcc-inbox-erp');
        if (thread) {
            var html = '';
            messages.forEach(function (m) {
                html += '<div class="rcc-inbox__msg rcc-inbox__msg--' + m.direction + '">' +
                    '<span class="rcc-inbox__msg-channel">' + self._esc(m.channel) + '</span>' +
                    '<div>' + self._esc(m.message) + '</div>' +
                    '<time>' + self._esc(m.created_at || '') + '</time></div>';
            });
            thread.innerHTML = html || '<div class="rcc-inbox__empty"><span class="rcc-inbox__empty-icon">💬</span>No messages yet</div>';
            thread.scrollTop = thread.scrollHeight;
        }
        if (erp) {
            var meta = conversation.metadata || {};
            var erpCustomer = meta.erp_customer || {};
            var contact = erpCustomer.contact || {};
            erp.innerHTML = '<h4>' + self._esc(contact.full_name || conversation.customer_identity || 'Customer') + '</h4>' +
                '<p>Priority: ' + self._esc(conversation.priority) + '</p>' +
                '<p>SLA: ' + self._esc(conversation.sla_status) + '</p>';
        }
    };

    RccAgentInbox.prototype._api = function (action, body) {
        var url = this.apiBase + (this.apiBase.indexOf('?') >= 0 ? '&' : '?') + 'action=' + encodeURIComponent(action);
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (r) {
            return r.text().then(function (text) {
                if (!text) {
                    return { ok: false, error: 'Empty response (HTTP ' + r.status + ')' };
                }
                try {
                    var data = JSON.parse(text);
                    if (!r.ok && data && !data.error) {
                        data.error = 'HTTP ' + r.status;
                        data.ok = false;
                    }
                    return data;
                } catch (err) {
                    return {
                        ok: false,
                        error: 'Invalid JSON (HTTP ' + r.status + ')',
                        detail: text.substring(0, 200)
                    };
                }
            });
        }).catch(function (err) {
            return { ok: false, error: err && err.message ? err.message : 'Network error' };
        });
    };

    RccAgentInbox.prototype._esc = function (s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    };

    global.RccAgentInbox = RccAgentInbox;
})(typeof window !== 'undefined' ? window : globalThis);
