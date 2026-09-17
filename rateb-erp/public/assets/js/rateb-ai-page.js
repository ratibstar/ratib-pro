/**
 * RATEB AI page — soft-nav safe (script[src] re-injected by erp-nav-instant).
 * Mirrors the inline window.ratebAi API from the AI view.
 */
(function (root, doc) {
    'use strict';

    function buildApi() {
        return {
            loading: false,
            lastMessage: '',
            pendingConfirmKeys: [],
            __p0WriteConfirm: true,
            __p0SendFix: true,
            __p0LangFix: true,
            __p0I18nUi: true,
            __p0ToolLabels: true,
            __p0ChatHistory: true,
            __unifiedConfirmFix: true,
            isConfirmPhrase: function (message) {
                var m = String(message || '').trim();
                if (!m || m.length > 40) return false;
                return /^(نعم|موافق|تأكيد|أكد|اكد|أكمل|اكمل|نفذ|نفّذ|انشئ|أنشئ|انشاء|إنشاء|تنفيذ|confirm|yes|ok|okay|go|proceed|do\s*it|create|execute)!*$/i.test(m);
            },
            isRejectPhrase: function (message) {
                var m = String(message || '').trim();
                if (!m || m.length > 40) return false;
                return /^(لا|الغاء|إلغاء|توقف|ألغ|الغ|cancel|no|stop|abort|nevermind)!*$/i.test(m);
            },
            send: function (message, confirmedWrites) {
                var box = doc.getElementById('ratebAiRoot');
                if (!box) return false;
                var messages = doc.getElementById('aiMessages');
                var input = doc.getElementById('aiInput');
                var welcome = doc.getElementById('aiWelcome');
                var statusDot = doc.getElementById('aiStatusDot');
                var statusText = doc.getElementById('aiStatusText');
                var endpoint = box.getAttribute('data-endpoint') || '';
                var csrf = box.getAttribute('data-csrf') || '';
                var t = function (key, fallback) {
                    return box.getAttribute('data-i18n-' + key) || fallback;
                };
                var toolLabels = {};
                try {
                    toolLabels = JSON.parse(box.getAttribute('data-tool-labels') || '{}') || {};
                } catch (eLabels) {
                    toolLabels = {};
                }
                var toolLabel = function (name) {
                    return (toolLabels && toolLabels[name]) ? toolLabels[name] : name;
                };
                message = String(message || '').trim();
                if (!message || !messages || !endpoint || this.loading) return false;
                confirmedWrites = Array.isArray(confirmedWrites) ? confirmedWrites : [];
                if (!confirmedWrites.length && this.isConfirmPhrase(message) && this.pendingConfirmKeys && this.pendingConfirmKeys.length) {
                    confirmedWrites = this.pendingConfirmKeys.slice();
                }
                if (this.isRejectPhrase(message)) {
                    this.pendingConfirmKeys = [];
                }

                function setStatus(state) {
                    if (!statusText) return;
                    if (state === 'thinking') {
                        if (statusDot) statusDot.style.animation = 'none';
                        statusText.textContent = statusText.getAttribute('data-thinking') || 'Thinking...';
                    } else {
                        if (statusDot) statusDot.style.animation = 'pulse 2s infinite';
                        statusText.textContent = statusText.getAttribute('data-ready') || 'Ready';
                    }
                }
                function esc(text) {
                    return String(text)
                        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;').replace(/'/g, '&#039;')
                        .replace(/\n/g, '<br>');
                }
                function addMsg(role, content) {
                    var div = doc.createElement('div');
                    div.className = 'rateb-ai-message ' + role;
                    div.innerHTML = '<div class="rateb-ai-message-avatar"><i class="fa-solid fa-' +
                        (role === 'user' ? 'user' : 'robot') + '"></i></div>' +
                        '<div class="rateb-ai-message-content">' + esc(content) + '</div>';
                    messages.appendChild(div);
                    messages.scrollTop = messages.scrollHeight;
                }
                function typing() {
                    var div = doc.createElement('div');
                    div.className = 'rateb-ai-message assistant rateb-ai-typing-container';
                    div.innerHTML = '<div class="rateb-ai-message-avatar"><i class="fa-solid fa-robot"></i></div>' +
                        '<div class="rateb-ai-typing"><div class="rateb-ai-typing-dot"></div><div class="rateb-ai-typing-dot"></div><div class="rateb-ai-typing-dot"></div></div>';
                    messages.appendChild(div);
                    messages.scrollTop = messages.scrollHeight;
                    return div;
                }
                function showConfirm(pending, originalMessage) {
                    var keys = [];
                    var labels = [];
                    var actionId = '';
                    (pending || []).forEach(function (p) {
                        if (p && p.confirm_key) keys.push(p.confirm_key);
                        if (p && p.tool) labels.push(toolLabel(p.tool));
                        if (p && p.action_id && !actionId) actionId = String(p.action_id);
                    });
                    if (!keys.length) return;
                    var wrap = doc.createElement('div');
                    wrap.className = 'rateb-ai-message assistant rateb-ai-confirm-chrome';
                    wrap.setAttribute('data-action-id', actionId || '');
                    wrap.innerHTML = '<div class="rateb-ai-message-avatar"><i class="fa-solid fa-robot"></i></div>' +
                        '<div class="rateb-ai-message-content">' +
                        '<div>' + esc(labels.join(', ')) + '</div>' +
                        '<div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">' +
                        '<button type="button" class="rateb-ai-suggestion-btn" data-rateb-ai-confirm="1">' + esc(t('confirm', 'Confirm')) + '</button>' +
                        '<button type="button" class="rateb-ai-suggestion-btn" data-rateb-ai-cancel="1">' + esc(t('cancel', 'Cancel')) + '</button>' +
                        '</div></div>';
                    messages.appendChild(wrap);
                    messages.scrollTop = messages.scrollHeight;
                    var confirmBtn = wrap.querySelector('[data-rateb-ai-confirm]');
                    var cancelBtn = wrap.querySelector('[data-rateb-ai-cancel]');
                    if (confirmBtn) {
                        confirmBtn.addEventListener('click', function () {
                            if (confirmBtn.disabled || root.ratebAi.loading) return;
                            confirmBtn.disabled = true;
                            if (cancelBtn) cancelBtn.disabled = true;
                            if (wrap.parentNode) wrap.parentNode.removeChild(wrap);
                            // Deterministic confirmation — never re-send the original create utterance
                            root.ratebAi.send(t('confirm', 'تأكيد'), keys.slice());
                        });
                    }
                    if (cancelBtn) {
                        cancelBtn.addEventListener('click', function () {
                            if (cancelBtn.disabled || root.ratebAi.loading) return;
                            cancelBtn.disabled = true;
                            if (confirmBtn) confirmBtn.disabled = true;
                            if (wrap.parentNode) wrap.parentNode.removeChild(wrap);
                            root.ratebAi.pendingConfirmKeys = [];
                            root.ratebAi.send(t('cancel', 'إلغاء'));
                        });
                    }
                }

                if (welcome) welcome.style.display = 'none';
                var history = (root.RatebAiHistory && root.RatebAiHistory.getApiHistory)
                    ? root.RatebAiHistory.getApiHistory()
                    : (this.getHistory ? this.getHistory() : []);
                if (!confirmedWrites.length || this.isConfirmPhrase(message) || this.isRejectPhrase(message)) {
                    addMsg('user', message);
                    this.lastMessage = message;
                    try { root.RatebAiHistory && root.RatebAiHistory.appendMessage('user', message); } catch (eHistU) {}
                }
                if (input) {
                    input.value = '';
                    input.style.height = 'auto';
                }
                this.loading = true;
                this.syncSendBtn();
                setStatus('thinking');
                var tip = typing();
                var self = this;

                fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrf,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        message: message,
                        history: history,
                        confirmed_writes: confirmedWrites
                    })
                }).then(function (res) {
                    return res.json().catch(function () {
                        return { success: false, message: t('request-failed', 'Request failed') + ' (' + res.status + ')' };
                    }).then(function (data) { return { ok: res.ok, data: data }; });
                }).then(function (result) {
                    if (tip && tip.parentNode) tip.parentNode.removeChild(tip);
                    var data = result.data || {};
                    if (!result.ok || !data.success) {
                        addMsg('assistant', t('error-prefix', 'Error: ') + (data.message || t('request-failed', 'Request failed')));
                        return;
                    }
                    addMsg('assistant', (data.data && data.data.response) ? data.data.response : t('no-response', 'No response'));
                    try {
                        var replyText = (data.data && data.data.response) ? data.data.response : '';
                        if (replyText && root.RatebAiHistory) root.RatebAiHistory.appendMessage('assistant', replyText);
                        if (root.RatebAiHistory && root.RatebAiHistory.decorateDomActions) root.RatebAiHistory.decorateDomActions();
                    } catch (eHistA) {}
                    var pending = (data.data && data.data.pending_confirmations) ? data.data.pending_confirmations : [];
                    if (pending.length) {
                        var keys = [];
                        pending.forEach(function (p) { if (p && p.confirm_key) keys.push(p.confirm_key); });
                        self.pendingConfirmKeys = keys;
                        showConfirm(pending, message);
                    } else if (confirmedWrites.length) {
                        self.pendingConfirmKeys = [];
                    }
                }).catch(function (err) {
                    if (tip && tip.parentNode) tip.parentNode.removeChild(tip);
                    addMsg('assistant', t('error-prefix', 'Error: ') + (err && err.message ? err.message : t('network-error', 'Network error')));
                }).then(function () {
                    self.loading = false;
                    setStatus('ready');
                    self.syncSendBtn();
                });
                return false;
            },
            clickSuggest: function (btn) {
                var prompt = (btn && (btn.getAttribute('data-prompt') || btn.textContent)) || '';
                return this.send(prompt);
            },
            getHistory: function () {
                var messages = doc.getElementById('aiMessages');
                if (!messages) return [];
                var out = [];
                var nodes = messages.querySelectorAll('.rateb-ai-message');
                for (var i = 0; i < nodes.length; i++) {
                    var el = nodes[i];
                    if (el.classList.contains('rateb-ai-typing-container')) continue;
                    if (el.classList.contains('rateb-ai-confirm-chrome')) continue;
                    var role = el.classList.contains('user') ? 'user' : (el.classList.contains('assistant') ? 'assistant' : '');
                    if (!role) continue;
                    var contentEl = el.querySelector('.rateb-ai-message-content');
                    var text = contentEl ? String(contentEl.innerText || contentEl.textContent || '').trim() : '';
                    if (!text) continue;
                    out.push({ role: role, content: text });
                }
                if (out.length > 16) out = out.slice(out.length - 16);
                return out;
            },
            sendFromInput: function () {
                var input = doc.getElementById('aiInput');
                return this.send(input ? input.value : '');
            },
            syncSendBtn: function () {
                var input = doc.getElementById('aiInput');
                var sendBtn = doc.getElementById('aiSendBtn');
                if (!sendBtn) return;
                var empty = !(input && String(input.value || '').trim());
                sendBtn.disabled = !!this.loading;
                sendBtn.classList.toggle('is-empty', empty && !this.loading);
                sendBtn.setAttribute('aria-disabled', (empty || this.loading) ? 'true' : 'false');
            },
            bind: function () {
                var box = doc.getElementById('ratebAiRoot');
                if (!box) return;
                var form = doc.getElementById('aiInputForm');
                var input = doc.getElementById('aiInput');
                var sendBtn = doc.getElementById('aiSendBtn');
                var self = this;
                if (form && form.getAttribute('data-rateb-ai-submit') !== '1') {
                    form.setAttribute('data-rateb-ai-submit', '1');
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        self.sendFromInput();
                    });
                }
                if (input && input.getAttribute('data-rateb-ai-input') !== '1') {
                    input.setAttribute('data-rateb-ai-input', '1');
                    var onType = function () {
                        this.style.height = 'auto';
                        this.style.height = Math.min(this.scrollHeight, 180) + 'px';
                        self.syncSendBtn();
                    };
                    input.addEventListener('input', onType);
                    input.addEventListener('keyup', onType);
                    input.addEventListener('change', onType);
                    input.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            self.sendFromInput();
                        }
                    });
                }
                if (sendBtn && sendBtn.getAttribute('data-rateb-ai-click') !== '1') {
                    sendBtn.setAttribute('data-rateb-ai-click', '1');
                    sendBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        self.sendFromInput();
                    });
                }
                box.setAttribute('data-rateb-ai-bound', '1');
                self.syncSendBtn();
                try { if (input) input.focus(); } catch (eFocus) {}
            }
        };
    }

    function ensureApi() {
        if (root.ratebAi && root.ratebAi.__p0ChatHistory && typeof root.ratebAi.getHistory === 'function') {
            return root.ratebAi;
        }
        var prev = root.ratebAi || {};
        root.ratebAi = buildApi();
        root.ratebAi.loading = !!prev.loading;
        root.ratebAi.lastMessage = prev.lastMessage || '';

        if (!root.__ratebAiClickBound) {
            root.__ratebAiClickBound = true;
            doc.addEventListener('click', function (e) {
                var btn = e.target && e.target.closest ? e.target.closest('.rateb-ai-suggestion-btn') : null;
                if (!btn || !doc.getElementById('ratebAiRoot')) return;
                if (btn.getAttribute('data-rateb-ai-confirm') || btn.getAttribute('data-rateb-ai-cancel')) return;
                e.preventDefault();
                e.stopPropagation();
                root.ratebAi.clickSuggest(btn);
            }, true);
        }

        return root.ratebAi;
    }

    function boot() {
        var api = ensureApi();
        var box = doc.getElementById('ratebAiRoot');
        if (box) {
            box.removeAttribute('data-rateb-ai-bound');
            var form = doc.getElementById('aiInputForm');
            var input = doc.getElementById('aiInput');
            var sendBtn = doc.getElementById('aiSendBtn');
            if (form) form.removeAttribute('data-rateb-ai-submit');
            if (input) input.removeAttribute('data-rateb-ai-input');
            if (sendBtn) sendBtn.removeAttribute('data-rateb-ai-click');
            api.bind();
        }
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    doc.addEventListener('rateb:nav:afterEnter', boot);
    doc.addEventListener('rateb:soft-nav:afterEnter', boot);
})(window, document);
