/**
 * RATEB Contact Center — AI Copilot panel (EventBus-driven, advisory only).
 */
(function (global) {
    'use strict';

    var MOOD = { angry: '😡', negative: '😟', neutral: '😐', positive: '🙂' };

    function RccAiCopilot(options) {
        this.tenantId = options.tenantId || 0;
        this.agentId = options.agentId || 0;
        this.apiBase = options.apiBase || '';
        this.root = options.root || document.getElementById('rcc-ai-copilot');
        this.inboxRoot = options.inboxRoot || document.getElementById('rcc-agent-desktop');
        this._conversationId = null;
        this._context = null;
        this._replyEditable = false;
    }

    RccAiCopilot.prototype.init = function () {
        var self = this;
        if (!self.root) {
            return;
        }
        self._bindUi();
    };

    RccAiCopilot.prototype.onConversationSelected = function (conversation) {
        var id = conversation && conversation.conversation_id;
        if (!id) {
            return;
        }
        this._conversationId = id;
        this.loadContext(id);
    };

    RccAiCopilot.prototype.onRealtimeEvent = function (ev) {
        var types = [
            'AI_ASSISTANT_UPDATE', 'AI_SUMMARY_UPDATED', 'AI_SENTIMENT_UPDATED',
            'AI_INTENT_DETECTED', 'AI_RECOMMENDATION_READY', 'AI_REPLY_SUGGESTED', 'AI_TICKET_CREATED'
        ];
        if (types.indexOf(ev.type) === -1) {
            return;
        }
        var cid = ev.payload && (ev.payload.conversation_id || (ev.payload.ai_context && ev.payload.ai_context.conversation_id));
        if (cid && this._conversationId && parseInt(cid, 10) !== this._conversationId) {
            return;
        }
        if (ev.type === 'AI_ASSISTANT_UPDATE' && ev.payload && ev.payload.ai_context) {
            this.render(ev.payload.ai_context);
            return;
        }
        if (this._conversationId) {
            this.loadContext(this._conversationId);
        }
    };

    RccAiCopilot.prototype.loadContext = function (conversationId) {
        var self = this;
        self._api('context', {
            tenant_id: self.tenantId,
            conversation_id: conversationId
        }).then(function (res) {
            if (res.ok && res.ai_context) {
                self.render(res.ai_context);
            }
        });
    };

    RccAiCopilot.prototype.render = function (ctx) {
        this._context = ctx || {};
        var mood = document.getElementById('rcc-ai-mood');
        var intent = document.getElementById('rcc-ai-intent');
        var risk = document.getElementById('rcc-ai-risk');
        var summary = document.getElementById('rcc-ai-summary');
        var reply = document.getElementById('rcc-ai-reply');
        var actions = document.getElementById('rcc-ai-actions');
        var panel = this.root;

        var sentiment = ctx.sentiment || 'neutral';
        var emoji = MOOD[sentiment] || MOOD.neutral;
        if (mood) {
            mood.textContent = emoji + ' ' + sentiment;
        }
        if (intent) {
            intent.textContent = (ctx.intent || '—').replace(/_/g, ' ');
        }
        if (risk) {
            var rs = ctx.risk_score != null ? Math.round(ctx.risk_score * 100) + '%' : '—';
            risk.textContent = rs;
        }
        if (summary) {
            summary.textContent = ctx.summary_live || ctx.summary_final || 'No summary yet.';
        }
        if (reply) {
            reply.value = ctx.suggested_reply || '';
            reply.readOnly = !this._replyEditable;
        }

        panel.classList.remove('rcc-ai-copilot--risk-high', 'rcc-ai-copilot--risk-mid');
        if ((ctx.risk_score || 0) >= 0.75) {
            panel.classList.add('rcc-ai-copilot--risk-high');
        } else if ((ctx.risk_score || 0) >= 0.45) {
            panel.classList.add('rcc-ai-copilot--risk-mid');
        }

        if (actions) {
            var list = (ctx.suggestions && ctx.suggestions.actions) || [];
            if (!list.length && ctx.recommended_action) {
                list = [{ action: ctx.recommended_action, label: ctx.recommended_action.replace(/_/g, ' ') }];
            }
            actions.innerHTML = list.map(function (a) {
                var cls = a.action.indexOf('ESCALATE') >= 0 || a.action.indexOf('TICKET') >= 0
                    ? 'rcc-ai-copilot__action' : 'rcc-ai-copilot__action rcc-ai-copilot__action--neutral';
                return '<button type="button" class="' + cls + '" data-ai-action="' + a.action + '">' + (a.label || a.action) + '</button>';
            }).join('');
        }
    };

    RccAiCopilot.prototype._bindUi = function () {
        var self = this;
        var sendAsIs = document.getElementById('rcc-ai-send-as-is');
        var editSend = document.getElementById('rcc-ai-edit-send');
        var reply = document.getElementById('rcc-ai-reply');
        var actions = document.getElementById('rcc-ai-actions');

        if (sendAsIs) {
            sendAsIs.addEventListener('click', function () {
                if (!self._conversationId) {
                    alert('Select a conversation first (use "New demo chat").');
                    return;
                }
                self._applyReply(false);
            });
        }
        if (editSend) {
            editSend.addEventListener('click', function () {
                if (!self._conversationId) {
                    alert('Select a conversation first (use "New demo chat").');
                    return;
                }
                self._replyEditable = true;
                if (reply) {
                    reply.readOnly = false;
                    reply.focus();
                }
            });
        }
        if (actions) {
            actions.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-ai-action]');
                if (!btn) {
                    return;
                }
                self._runAction(btn.getAttribute('data-ai-action'));
            });
        }
    };

    RccAiCopilot.prototype._applyReply = function (editMode) {
        var reply = document.getElementById('rcc-ai-reply');
        var composer = this.inboxRoot && this.inboxRoot.querySelector('#rcc-inbox-reply');
        if (!reply || !composer) {
            return;
        }
        composer.value = reply.value;
        if (!editMode) {
            var sendBtn = this.inboxRoot.querySelector('#rcc-inbox-send');
            if (sendBtn) {
                sendBtn.click();
            }
        } else {
            composer.focus();
        }
    };

    RccAiCopilot.prototype._runAction = function (action) {
        var self = this;
        if (!self._conversationId) {
            return;
        }
        if (action === 'CREATE_TICKET' || action.indexOf('TICKET') >= 0) {
            self._api('create_ticket', {
                tenant_id: self.tenantId,
                agent_id: self.agentId,
                conversation_id: self._conversationId,
                subject: 'Copilot: ' + (self._context.intent || 'support'),
                description: self._context.summary_live || ''
            }).then(function (res) {
                if (res.ok) {
                    alert('Ticket #' + res.ticket_id + ' created (advisory action confirmed by agent).');
                } else {
                    alert((res && res.error) || 'Could not create ticket — run DB migration 010 on the server.');
                }
            }).catch(function () {
                alert('Could not create ticket — check server logs or run migration 010.');
            });
            return;
        }
        alert('Advisory: ' + action.replace(/_/g, ' ') + ' — agent must execute manually in softphone/inbox.');
    };

    RccAiCopilot.prototype._api = function (action, body) {
        var url = this.apiBase + (this.apiBase.indexOf('?') >= 0 ? '&' : '?') + 'action=' + encodeURIComponent(action);
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); });
    };

    global.RccAiCopilot = RccAiCopilot;
})(typeof window !== 'undefined' ? window : globalThis);
