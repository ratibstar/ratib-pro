<?php
/**
 * RATEB AI — General AI Assistant for RATEB ERP
 * Chat interface connected to backend agents
 */
?>
<div class="rateb-ai-container">
    <div class="rateb-ai-header">
        <div class="rateb-ai-brand">
            <i class="fa-solid fa-robot"></i>
            <span class="rateb-ai-title"><?php echo htmlspecialchars(__('rateb_ai') ?? 'RATEB AI', ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="rateb-ai-status">
            <span class="rateb-ai-status-dot" id="aiStatusDot"></span>
            <span class="rateb-ai-status-text" id="aiStatusText"><?php echo htmlspecialchars(__('ai_ready') ?? 'Ready', ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    </div>

    <div class="rateb-ai-messages" id="aiMessages" role="log" aria-live="polite">
        <div class="rateb-ai-welcome" id="aiWelcome">
            <div class="rateb-ai-avatar">
                <i class="fa-solid fa-robot"></i>
            </div>
            <div class="rateb-ai-welcome-content">
                <h3><?php echo htmlspecialchars(__('rateb_ai') ?? 'RATEB AI', ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?php echo htmlspecialchars(__('ai_welcome_message') ?? 'Welcome to RATEB AI. I can help you with procurement tasks and more.', ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="rateb-ai-suggestions">
                    <button type="button" class="rateb-ai-suggestion-btn" data-prompt="List my pending purchase requests"><?php echo htmlspecialchars(__('ai_suggest_list_pr') ?? 'List my pending purchase requests', ENT_QUOTES, 'UTF-8'); ?></button>
                    <button type="button" class="rateb-ai-suggestion-btn" data-prompt="Search for suppliers in Riyadh"><?php echo htmlspecialchars(__('ai_suggest_search_suppliers') ?? 'Search for suppliers in Riyadh', ENT_QUOTES, 'UTF-8'); ?></button>
                    <button type="button" class="rateb-ai-suggestion-btn" data-prompt="Show pending approvals"><?php echo htmlspecialchars(__('ai_suggest_pending_approvals') ?? 'Show pending approvals', ENT_QUOTES, 'UTF-8'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <form class="rateb-ai-input-form" id="aiInputForm" novalidate>
        <div class="rateb-ai-input-wrapper">
            <textarea
                class="rateb-ai-input"
                id="aiInput"
                name="message"
                placeholder="<?php echo htmlspecialchars(__('ai_input_placeholder') ?? 'Type your message...', ENT_QUOTES, 'UTF-8'); ?>"
                rows="1"
                aria-label="<?php echo htmlspecialchars(__('ai_input_label') ?? 'Message', ENT_QUOTES, 'UTF-8'); ?>"
                required></textarea>
            <div class="rateb-ai-input-actions">
                <button type="submit" class="rateb-ai-send-btn" id="aiSendBtn" disabled aria-label="<?php echo htmlspecialchars(__('ai_send') ?? 'Send', ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </div>
        </div>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    </form>
</div>

<style>
:root {
    --ai-bg: #ffffff;
    --ai-text: #212529;
    --ai-text-muted: #6c757d;
    --ai-border: #dee2e6;
    --ai-primary: #1a5fb4;
    --ai-primary-hover: #155099;
    --ai-user-bg: #1a5fb4;
    --ai-user-text: #ffffff;
    --ai-assistant-bg: #f8f9fa;
    --ai-assistant-border: #e9ecef;
    --ai-input-bg: #ffffff;
    --ai-input-border: #ced4da;
    --ai-input-focus: #1a5fb4;
    --ai-status-online: #198754;
    --ai-suggestion-bg: #f8f9fa;
    --ai-suggestion-hover: #e9ecef;
    --ai-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

[data-bs-theme="dark"] {
    --ai-bg: #1e1e2e;
    --ai-text: #e4e4e7;
    --ai-text-muted: #a1a1aa;
    --ai-border: #3f3f46;
    --ai-primary: #3b82f6;
    --ai-primary-hover: #2563eb;
    --ai-user-bg: #3b82f6;
    --ai-user-text: #ffffff;
    --ai-assistant-bg: #27272a;
    --ai-assistant-border: #3f3f46;
    --ai-input-bg: #18181b;
    --ai-input-border: #3f3f46;
    --ai-input-focus: #3b82f6;
    --ai-status-online: #22c55e;
    --ai-suggestion-bg: #27272a;
    --ai-suggestion-hover: #3f3f46;
    --ai-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

.rateb-ai-container {
    display: flex;
    flex-direction: column;
    height: 100vh;
    max-height: 100vh;
    background: var(--ai-bg);
    border-radius: 12px;
    box-shadow: var(--ai-shadow);
    overflow: hidden;
    font-family: 'Tajawal', system-ui, -apple-system, sans-serif;
}

.rateb-ai-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid var(--ai-border);
    background: var(--ai-bg);
    flex-shrink: 0;
}

.rateb-ai-brand {
    display: flex;
    align-items: center;
    gap: 10px;
}

.rateb-ai-brand i {
    font-size: 24px;
    color: var(--ai-primary);
}

.rateb-ai-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--ai-text);
}

.rateb-ai-status {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--ai-text-muted);
}

.rateb-ai-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--ai-status-online);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.rateb-ai-messages {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    background: var(--ai-bg);
    overflow-y: auto;
}

.rateb-ai-welcome {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    padding: 24px;
    background: var(--ai-assistant-bg);
    border: 1px solid var(--ai-assistant-border);
    border-radius: 16px;
    max-width: 600px;
    margin: 0 auto;
}

.rateb-ai-avatar {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: var(--ai-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    flex-shrink: 0;
}

.rateb-ai-welcome-content h3 {
    margin: 0 0 8px;
    font-size: 18px;
    font-weight: 700;
    color: var(--ai-text);
}

.rateb-ai-welcome-content p {
    margin: 0 0 16px;
    color: var(--ai-text-muted);
    font-size: 14px;
    line-height: 1.5;
}

.rateb-ai-suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.rateb-ai-suggestion-btn {
    padding: 8px 14px;
    background: var(--ai-suggestion-bg);
    border: 1px solid var(--ai-border);
    border-radius: 20px;
    font-size: 13px;
    color: var(--ai-text);
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.rateb-ai-suggestion-btn:hover {
    background: var(--ai-suggestion-hover);
    border-color: var(--ai-primary);
    color: var(--ai-primary);
}

.rateb-ai-message {
    display: flex;
    gap: 12px;
    animation: fadeIn 0.2s ease;
    max-width: 85%;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

.rateb-ai-message.user {
    align-self: flex-end;
    flex-direction: row-reverse;
}

.rateb-ai-message.assistant {
    align-self: flex-start;
}

.rateb-ai-message-avatar {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.rateb-ai-message.user .rateb-ai-message-avatar {
    background: var(--ai-user-bg);
    color: var(--ai-user-text);
}

.rateb-ai-message.assistant .rateb-ai-message-avatar {
    background: var(--ai-assistant-bg);
    border: 1px solid var(--ai-assistant-border);
    color: var(--ai-primary);
}

.rateb-ai-message-content {
    padding: 12px 16px;
    border-radius: 16px;
    max-width: 500px;
    font-size: 14px;
    line-height: 1.6;
}

.rateb-ai-message.user .rateb-ai-message-content {
    background: var(--ai-user-bg);
    color: var(--ai-user-text);
    border-bottom-right-radius: 4px;
}

.rateb-ai-message.assistant .rateb-ai-message-content {
    background: var(--ai-assistant-bg);
    border: 1px solid var(--ai-assistant-border);
    color: var(--ai-text);
    border-bottom-left-radius: 4px;
}

.rateb-ai-message-content pre {
    margin: 8px 0;
    padding: 12px;
    background: #1e1e1e;
    border-radius: 8px;
    overflow-x: auto;
    font-size: 12px;
    color: #e4e4e7;
}

.rateb-ai-message-content code {
    font-family: 'Fira Code', monospace;
    background: rgba(0,0,0,0.05);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 13px;
}

[data-bs-theme="dark"] .rateb-ai-message-content code {
    background: rgba(255,255,255,0.1);
}

.rateb-ai-message-content .tool-call {
    padding: 10px 12px;
    background: var(--ai-suggestion-bg);
    border: 1px solid var(--ai-border);
    border-radius: 8px;
    margin: 8px 0;
    font-size: 12px;
    color: var(--ai-text-muted);
    font-family: monospace;
}

.rateb-ai-message-content .tool-result {
    padding: 10px 12px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    margin: 8px 0;
    font-size: 12px;
    color: #166534;
}

[data-bs-theme="dark"] .rateb-ai-message-content .tool-result {
    background: #14532d;
    border-color: #22c55e;
    color: #86efac;
}

.rateb-ai-typing {
    display: flex;
    gap: 4px;
    padding: 12px 16px;
    align-items: center;
}

.rateb-ai-typing-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--ai-text-muted);
    animation: typing 1.4s infinite ease-in-out;
}

.rateb-ai-typing-dot:nth-child(2) { animation-delay: 0.2s; }
.rateb-ai-typing-dot:nth-child(3) { animation-delay: 0.4s; }

@keyframes typing {
    0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
    30% { transform: translateY(-6px); opacity: 1; }
}

.rateb-ai-input-form {
    flex-shrink: 0;
    padding: 16px 20px;
    border-top: 1px solid var(--ai-border);
    background: var(--ai-bg);
}

.rateb-ai-input-wrapper {
    display: flex;
    gap: 12px;
    max-width: 900px;
    margin: 0 auto;
}

.rateb-ai-input {
    flex: 1;
    min-height: 48px;
    max-height: 180px;
    padding: 12px 16px;
    border: 2px solid var(--ai-input-border);
    border-radius: 12px;
    background: var(--ai-input-bg);
    color: var(--ai-text);
    font-size: 14px;
    line-height: 1.5;
    resize: none;
    font-family: inherit;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.rateb-ai-input:focus {
    outline: none;
    border-color: var(--ai-input-focus);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--ai-input-focus) 20%, transparent);
}

.rateb-ai-input::placeholder {
    color: var(--ai-text-muted);
}

.rateb-ai-input-actions {
    flex-shrink: 0;
}

.rateb-ai-send-btn {
    width: 48px;
    height: 48px;
    border: none;
    border-radius: 12px;
    background: var(--ai-primary);
    color: white;
    font-size: 18px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s ease, transform 0.1s ease;
}

.rateb-ai-send-btn:hover:not(:disabled) {
    background: var(--ai-primary-hover);
    transform: translateY(-1px);
}

.rateb-ai-send-btn:active:not(:disabled) {
    transform: translateY(0);
}

.rateb-ai-send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .rateb-ai-message {
        max-width: 90%;
    }
    .rateb-ai-header {
        padding: 12px 16px;
    }
    .rateb-ai-messages {
        padding: 16px;
    }
    .rateb-ai-welcome {
        padding: 16px;
    }
}
</style>

<script>
(function() {
    'use strict';

    const messagesContainer = document.getElementById('aiMessages');
    const inputForm = document.getElementById('aiInputForm');
    const input = document.getElementById('aiInput');
    const sendBtn = document.getElementById('aiSendBtn');
    const welcome = document.getElementById('aiWelcome');
    const statusDot = document.getElementById('aiStatusDot');
    const statusText = document.getElementById('aiStatusText');
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    let isLoading = false;
    let conversationId = null;

    // Auto-resize textarea
    const textarea = document.getElementById('aiInput');
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 180) + 'px';
        sendBtn.disabled = !this.value.trim();
    });

    // Submit handler
    inputForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const message = input.value.trim();
        if (!message || isLoading) return;

        // Hide welcome
        if (welcome) welcome.style.display = 'none';

        // Add user message
        appendMessage('user', message);
        input.value = '';
        input.style.height = 'auto';
        sendBtn.disabled = true;
        isLoading = true;
        updateStatus('thinking');

        // Show typing indicator
        const typingEl = showTyping();

        try {
            const response = await fetch('/api/v1/agent/procurement', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('input[name="csrf_token"]')?.value || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    message: message,
                    history: getHistory()
                })
            });

            removeTyping(typingEl);

            if (!response.ok) {
                const err = await response.json().catch(() => ({ message: 'Request failed' }));
                appendMessage('assistant', 'Error: ' + (err.message || 'Request failed'));
                return;
            }

            const data = await response.json();
            
            if (data.success && data.data) {
                const responseText = data.data.response || 'No response';
                appendMessage('assistant', responseText);
                
                // Store conversation ID if returned
                if (data.request_id) {
                    conversationId = data.request_id;
                }

                // Show tool calls if any
                if (data.data.tool_calls && data.data.tool_calls.length > 0) {
                    showToolCalls(data.data.tool_calls);
                }
            } else {
                appendMessage('assistant', 'Unexpected response format');
            }
        } catch (err) {
            removeTyping(typingEl);
            appendMessage('assistant', 'Error: ' + err.message);
        } finally {
            isLoading = false;
            updateStatus('ready');
            sendBtn.disabled = !input.value.trim();
        }
    });

    // Suggestion buttons
    document.querySelectorAll('.rateb-ai-suggestion-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const prompt = this.dataset.prompt;
            if (prompt) {
                input.value = prompt;
                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight, 180) + 'px';
                sendBtn.disabled = false;
                input.focus();
            }
        });
    });

    function appendMessage(role, content) {
        const div = document.createElement('div');
        div.className = 'rateb-ai-message ' + role;
        
        const avatar = document.createElement('div');
        avatar.className = 'rateb-ai-message-avatar';
        avatar.innerHTML = role === 'user' 
            ? '<i class="fa-solid fa-user"></i>' 
            : '<i class="fa-solid fa-robot"></i>';
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'rateb-ai-message-content';
        
        // Render markdown-like content
        contentDiv.innerHTML = formatContent(content);
        
        div.appendChild(avatar);
        div.appendChild(contentDiv);
        
        messagesContainer.appendChild(div);
        scrollToBottom();
    }

    function formatContent(text) {
        // Escape HTML
        let html = text.replace(/&/g, '&')
            .replace(/</g, '<')
            .replace(/>/g, '>')
            .replace(/"/g, '"')
            .replace(/'/g, '&#039;');
        
        // Bold
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        // Italic
        html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
        // Code blocks
        html = html.replace(/```(\w+)?\n([\s\S]*?)```/g, '<pre><code class="language-$1">$2</code></pre>');
        // Inline code
        html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
        // Links
        html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
        // Newlines
        html = html.replace(/\n/g, '<br>');
        
        return html;
    }

    function showToolCalls(toolCalls) {
        toolCalls.forEach(tc => {
            if (tc.result) {
                const div = document.createElement('div');
                div.className = 'rateb-ai-message assistant';
                div.innerHTML = `
                    <div class="rateb-ai-message-avatar"><i class="fa-solid fa-robot"></i></div>
                    <div class="rateb-ai-message-content">
                        <div class="tool-call">🔧 Tool: <strong>${tc.tool}</strong></div>
                        <div class="tool-result">✅ Result: ${JSON.stringify(tc.result).substring(0, 500)}</div>
                    </div>
                `;
                messagesContainer.appendChild(div);
                scrollToBottom();
            }
        });
    }

    function showTyping() {
        const div = document.createElement('div');
        div.className = 'rateb-ai-message assistant rateb-ai-typing-container';
        div.innerHTML = `
            <div class="rateb-ai-message-avatar"><i class="fa-solid fa-robot"></i></div>
            <div class="rateb-ai-typing">
                <div class="rateb-ai-typing-dot"></div>
                <div class="rateb-ai-typing-dot"></div>
                <div class="rateb-ai-typing-dot"></div>
            </div>
        `;
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
        const messages = document.querySelectorAll('.rateb-ai-message');
        const history = [];
        messages.forEach(msg => {
            const role = msg.classList.contains('user') ? 'user' : 'assistant';
            const content = msg.querySelector('.rateb-ai-message-content')?.textContent || '';
            if (content.trim()) {
                history.push({ role, content });
            }
        }
        return history.slice(-20); // Keep last 20 messages
    }

    function updateStatus(state) {
        if (state === 'thinking') {
            statusDot.style.animation = 'none';
            statusText.textContent = 'Thinking...';
        } else {
            statusDot.style.animation = 'pulse 2s infinite';
            statusText.textContent = 'Ready';
        }
    }

    function scrollToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Focus input on load
    input.focus();
})();
</script>