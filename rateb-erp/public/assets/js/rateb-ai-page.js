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
            __p0WriteConfirm: true,
            __p0SendFix: true,
            __p0LangFix: true,
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
                message = String(message || '').trim();
                if (!message || !messages || !endpoint || this.loading) return false;
                confirmedWrites = Array.isArray(confirmedWrites) ? confirmedWrites : [];

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
                    (pending || []).forEach(function (p) {
                        if (p && p.confirm_key) keys.push(p.confirm_key);
                        if (p && p.tool) labels.push(p.tool);
                    });
                    if (!keys.length) return;
                    var wrap = doc.createElement('div');
                    wrap.className = 'rateb-ai-message assistant';
                    wrap.innerHTML = '<div class="rateb-ai-message-avatar"><i class="fa-solid fa-robot"></i></div>' +
                        '<div class="rateb-ai-message-content">' +
                        '<div>' + esc(labels.join(', ')) + '</div>' +
                        '<div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">' +
                        '<button type="button" class="rateb-ai-suggestion-btn" data-rateb-ai-confirm="1">Confirm</button>' +
                        '<button type="button" class="rateb-ai-suggestion-btn" data-rateb-ai-cancel="1">Cancel</button>' +
                        '</div></div>';
                    messages.appendChild(wrap);
                    messages.scrollTop = messages.scrollHeight;
                    var confirmBtn = wrap.querySelector('[data-rateb-ai-confirm]');
                    var cancelBtn = wrap.querySelector('[data-rateb-ai-cancel]');
                    if (confirmBtn) {
                        confirmBtn.addEventListener('click', function () {
                            if (wrap.parentNode) wrap.parentNode.removeChild(wrap);
                            root.ratebAi.send(originalMessage, keys);
                        });
                    }
                    if (cancelBtn) {
                        cancelBtn.addEventListener('click', function () {
                            if (wrap.parentNode) wrap.parentNode.removeChild(wrap);
                        });
                    }
                }

                if (welcome) welcome.style.display = 'none';
                var history = this.getHistory ? this.getHistory() : [];
                if (!confirmedWrites.length) {
                    addMsg('user', message);
                    this.lastMessage = message;
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
                        return { success: false, message: 'Request failed (' + res.status + ')' };
                    }).then(function (data) { return { ok: res.ok, data: data }; });
                }).then(function (result) {
                    if (tip && tip.parentNode) tip.parentNode.removeChild(tip);
                    var data = result.data || {};
                    if (!result.ok || !data.success) {
                        addMsg('assistant', 'Error: ' + (data.message || 'Request failed'));
                        return;
                    }
                    addMsg('assistant', (data.data && data.data.response) ? data.data.response : 'No response');
                    var pending = (data.data && data.data.pending_confirmations) ? data.data.pending_confirmations : [];
                    if (pending.length) {
                        showConfirm(pending, message);
                    }
                }).catch(function (err) {
                    if (tip && tip.parentNode) tip.parentNode.removeChild(tip);
                    addMsg('assistant', 'Error: ' + (err && err.message ? err.message : 'Network error'));
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
        if (root.ratebAi && root.ratebAi.__p0LangFix && typeof root.ratebAi.getHistory === 'function') {
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
