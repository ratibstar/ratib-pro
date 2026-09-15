/**
 * RATEB AI page — soft-nav safe (script[src] re-injected by erp-nav-instant).
 * Mirrors the inline window.ratebAi API from the AI view.
 */
(function (root, doc) {
    'use strict';

    function ensureApi() {
        if (root.ratebAi && root.ratebAi.send) {
            return root.ratebAi;
        }

        root.ratebAi = {
            loading: false,
            send: function (message) {
                var box = doc.getElementById('ratebAiRoot');
                if (!box) return false;
                var messages = doc.getElementById('aiMessages');
                var input = doc.getElementById('aiInput');
                var sendBtn = doc.getElementById('aiSendBtn');
                var welcome = doc.getElementById('aiWelcome');
                var statusDot = doc.getElementById('aiStatusDot');
                var statusText = doc.getElementById('aiStatusText');
                var endpoint = box.getAttribute('data-endpoint') || '';
                var csrf = box.getAttribute('data-csrf') || '';
                message = String(message || '').trim();
                if (!message || !messages || !endpoint || this.loading) return false;

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

                if (welcome) welcome.style.display = 'none';
                addMsg('user', message);
                if (input) {
                    input.value = '';
                    input.style.height = 'auto';
                }
                if (sendBtn) sendBtn.disabled = true;
                this.loading = true;
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
                    body: JSON.stringify({ message: message, history: [] })
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
                }).catch(function (err) {
                    if (tip && tip.parentNode) tip.parentNode.removeChild(tip);
                    addMsg('assistant', 'Error: ' + (err && err.message ? err.message : 'Network error'));
                }).then(function () {
                    self.loading = false;
                    setStatus('ready');
                    if (sendBtn && input) sendBtn.disabled = !input.value.trim();
                });
                return false;
            },
            clickSuggest: function (btn) {
                var prompt = (btn && (btn.getAttribute('data-prompt') || btn.textContent)) || '';
                return this.send(prompt);
            },
            bind: function () {
                var box = doc.getElementById('ratebAiRoot');
                if (!box || box.getAttribute('data-rateb-ai-bound') === '1') return;
                var form = doc.getElementById('aiInputForm');
                var input = doc.getElementById('aiInput');
                var sendBtn = doc.getElementById('aiSendBtn');
                var self = this;
                if (form) {
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        self.send(input ? input.value : '');
                    });
                }
                if (input && sendBtn) {
                    input.addEventListener('input', function () {
                        this.style.height = 'auto';
                        this.style.height = Math.min(this.scrollHeight, 180) + 'px';
                        sendBtn.disabled = !this.value.trim() || self.loading;
                    });
                }
                box.setAttribute('data-rateb-ai-bound', '1');
                try { if (input) input.focus(); } catch (eFocus) {}
            }
        };

        if (!root.__ratebAiClickBound) {
            root.__ratebAiClickBound = true;
            doc.addEventListener('click', function (e) {
                var btn = e.target && e.target.closest ? e.target.closest('.rateb-ai-suggestion-btn') : null;
                if (!btn || !doc.getElementById('ratebAiRoot')) return;
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
