/**
 * RATEB AI page — soft-nav safe (script[src] is reloaded by erp-nav-instant).
 */
(function (root, doc) {
    'use strict';

    var BOOT_ATTR = 'data-rateb-ai-booted';

    function qs(sel, el) {
        return (el || doc).querySelector(sel);
    }

    function qsa(sel, el) {
        return Array.prototype.slice.call((el || doc).querySelectorAll(sel));
    }

    function boot() {
        var rootEl = qs('#ratebAiRoot');
        if (!rootEl || rootEl.getAttribute(BOOT_ATTR) === '1') {
            return;
        }
        rootEl.setAttribute(BOOT_ATTR, '1');

        var messagesContainer = qs('#aiMessages', rootEl);
        var inputForm = qs('#aiInputForm', rootEl);
        var input = qs('#aiInput', rootEl);
        var sendBtn = qs('#aiSendBtn', rootEl);
        var welcome = qs('#aiWelcome', rootEl);
        var statusDot = qs('#aiStatusDot', rootEl);
        var statusText = qs('#aiStatusText', rootEl);
        var endpoint = rootEl.getAttribute('data-endpoint') || '';
        var csrfToken = rootEl.getAttribute('data-csrf') || '';

        if (!messagesContainer || !inputForm || !input || !sendBtn || !endpoint) {
            return;
        }

        var isLoading = false;

        function updateStatus(state) {
            if (!statusText) {
                return;
            }
            if (state === 'thinking') {
                if (statusDot) {
                    statusDot.style.animation = 'none';
                }
                statusText.textContent = statusText.getAttribute('data-thinking') || 'Thinking...';
            } else {
                if (statusDot) {
                    statusDot.style.animation = 'pulse 2s infinite';
                }
                statusText.textContent = statusText.getAttribute('data-ready') || 'Ready';
            }
        }

        function scrollToBottom() {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function formatContent(text) {
            var html = String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
            html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
            html = html.replace(/```(\w+)?\n([\s\S]*?)```/g, '<pre><code class="language-$1">$2</code></pre>');
            html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
            html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
            html = html.replace(/\n/g, '<br>');
            return html;
        }

        function appendMessage(role, content) {
            var div = doc.createElement('div');
            div.className = 'rateb-ai-message ' + role;

            var avatar = doc.createElement('div');
            avatar.className = 'rateb-ai-message-avatar';
            avatar.innerHTML = role === 'user'
                ? '<i class="fa-solid fa-user"></i>'
                : '<i class="fa-solid fa-robot"></i>';

            var contentDiv = doc.createElement('div');
            contentDiv.className = 'rateb-ai-message-content';
            contentDiv.innerHTML = formatContent(content);

            div.appendChild(avatar);
            div.appendChild(contentDiv);
            messagesContainer.appendChild(div);
            scrollToBottom();
        }

        function showTyping() {
            var div = doc.createElement('div');
            div.className = 'rateb-ai-message assistant rateb-ai-typing-container';
            div.innerHTML =
                '<div class="rateb-ai-message-avatar"><i class="fa-solid fa-robot"></i></div>' +
                '<div class="rateb-ai-typing">' +
                '<div class="rateb-ai-typing-dot"></div>' +
                '<div class="rateb-ai-typing-dot"></div>' +
                '<div class="rateb-ai-typing-dot"></div>' +
                '</div>';
            messagesContainer.appendChild(div);
            scrollToBottom();
            return div;
        }

        function removeTyping(el) {
            if (el && el.parentNode) {
                el.parentNode.removeChild(el);
            }
        }

        function getHistory() {
            var history = [];
            qsa('.rateb-ai-message', messagesContainer).forEach(function (msg) {
                if (msg.classList.contains('rateb-ai-typing-container')) {
                    return;
                }
                var role = msg.classList.contains('user') ? 'user' : 'assistant';
                var contentEl = qs('.rateb-ai-message-content', msg);
                var content = contentEl ? (contentEl.textContent || '') : '';
                if (content.trim()) {
                    history.push({ role: role, content: content });
                }
            });
            return history.slice(-20);
        }

        function showToolCalls(toolCalls) {
            if (!Array.isArray(toolCalls)) {
                return;
            }
            toolCalls.forEach(function (tc) {
                if (!tc || !tc.result) {
                    return;
                }
                var div = doc.createElement('div');
                div.className = 'rateb-ai-message assistant';
                var toolName = tc.tool || tc.name || 'tool';
                var resultText = '';
                try {
                    resultText = JSON.stringify(tc.result).substring(0, 500);
                } catch (eJson) {
                    resultText = String(tc.result);
                }
                div.innerHTML =
                    '<div class="rateb-ai-message-avatar"><i class="fa-solid fa-robot"></i></div>' +
                    '<div class="rateb-ai-message-content">' +
                    '<div class="tool-call">Tool: <strong>' + formatContent(String(toolName)) + '</strong></div>' +
                    '<div class="tool-result">Result: ' + formatContent(resultText) + '</div>' +
                    '</div>';
                messagesContainer.appendChild(div);
                scrollToBottom();
            });
        }

        function sendMessage(message) {
            message = String(message || '').trim();
            if (!message || isLoading) {
                return;
            }

            if (welcome) {
                welcome.style.display = 'none';
            }

            appendMessage('user', message);
            input.value = '';
            input.style.height = 'auto';
            sendBtn.disabled = true;
            isLoading = true;
            updateStatus('thinking');

            var typingEl = showTyping();

            fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    message: message,
                    history: getHistory()
                })
            }).then(function (response) {
                return response.json().catch(function () {
                    return { success: false, message: 'Request failed (' + response.status + ')' };
                }).then(function (data) {
                    return { ok: response.ok, data: data };
                });
            }).then(function (result) {
                removeTyping(typingEl);
                var data = result.data || {};
                if (!result.ok || !data.success) {
                    appendMessage('assistant', 'Error: ' + (data.message || 'Request failed'));
                    return;
                }
                var responseText = (data.data && data.data.response) ? data.data.response : 'No response';
                appendMessage('assistant', responseText);
                if (data.data && data.data.tool_calls) {
                    showToolCalls(data.data.tool_calls);
                }
            }).catch(function (err) {
                removeTyping(typingEl);
                appendMessage('assistant', 'Error: ' + (err && err.message ? err.message : 'Network error'));
            }).then(function () {
                isLoading = false;
                updateStatus('ready');
                sendBtn.disabled = !input.value.trim();
            });
        }

        input.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 180) + 'px';
            sendBtn.disabled = !this.value.trim() || isLoading;
        });

        inputForm.addEventListener('submit', function (e) {
            e.preventDefault();
            sendMessage(input.value);
        });

        qsa('.rateb-ai-suggestion-btn', rootEl).forEach(function (btn) {
            btn.addEventListener('click', function () {
                var prompt = this.getAttribute('data-prompt') || '';
                if (!prompt) {
                    return;
                }
                input.value = prompt;
                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight, 180) + 'px';
                sendBtn.disabled = false;
                sendMessage(prompt);
            });
        });

        try {
            input.focus();
        } catch (eFocus) { /* ignore */ }
    }

    function onReady() {
        boot();
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', onReady);
    } else {
        onReady();
    }

    doc.addEventListener('rateb:nav:afterEnter', onReady);
    doc.addEventListener('rateb:soft-nav:afterEnter', onReady);
})(window, document);
