<?php
declare(strict_types=1);

require_once __DIR__ . '/php/bootstrap.php';
$coreaiUser = coreai_current_user();

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CoreAI</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <?php if ($coreaiUser === null): ?>
    <div class="ide-shell">
        <header class="topbar">
            <h1 class="brand">CoreAI</h1>
            <div class="status">SaaS Login Required</div>
        </header>
        <main class="workspace coreai-auth-workspace">
            <section class="editor-panel coreai-auth-panel">
                <div class="editor-header">
                    <span>Account Access</span>
                </div>
                <div class="coreai-auth-content">
                    <form id="coreai-login-form" class="chat-form">
                        <input id="coreai-login-username" class="chat-input" type="text" placeholder="Username" autocomplete="username" required>
                        <input id="coreai-login-password" class="chat-input" type="password" placeholder="Password" autocomplete="current-password" required>
                        <button class="btn" type="submit">Login</button>
                    </form>
                    <form id="coreai-register-form" class="chat-form">
                        <input id="coreai-register-username" class="chat-input" type="text" placeholder="New Username" autocomplete="username" required>
                        <input id="coreai-register-password" class="chat-input" type="password" placeholder="New Password (min 8)" autocomplete="new-password" required>
                        <button class="btn" type="submit">Register</button>
                    </form>
                    <div id="coreai-auth-status" class="status">Sign in to access your isolated CoreAI workspace.</div>
                </div>
            </section>
        </main>
    </div>
    <script src="js/auth.js"></script>
    <?php else: ?>
    <!-- Top app shell / الحاوية الرئيسية للتطبيق -->
    <div class="ide-shell">
        <!-- Top bar / الشريط العلوي -->
        <header class="topbar">
            <h1 class="brand">CoreAI</h1>
            <div class="status"><?php echo htmlspecialchars((string)$coreaiUser['username']); ?></div>
            <div class="status" id="save-status">Ready</div>
            <div id="api-key-inline" class="api-key-inline">
                <input id="api-key-input" class="chat-input" type="password" placeholder="Set AI API Key (Gemini / Groq / OpenRouter)" autocomplete="off">
                <button id="save-api-key-btn" class="btn btn-secondary" type="button">Save Key</button>
            </div>
        </header>

        <!-- Main workspace / مساحة العمل الرئيسية -->
        <main class="workspace">
            <!-- Left sidebar navigation / شريط التنقل الأيسر -->
            <aside class="sidebar" aria-label="Navigation panel">
                <h2 class="panel-title">Explorer</h2>
                <nav id="file-nav" class="file-nav" aria-label="Project files"></nav>
            </aside>

            <!-- Center editor area / منطقة المحرر الوسطى -->
            <section class="editor-panel" aria-label="Code editor panel">
                <div class="editor-header">
                    <span id="active-file-label">No file selected</span>
                    <button id="save-btn" class="btn" type="button">Save</button>
                </div>
                <textarea
                    id="code-editor"
                    class="code-editor"
                    spellcheck="false"
                    aria-label="Code editor"
                    placeholder="Select a file from the left panel..."
                ></textarea>
            </section>

            <!-- Right AI chat panel / لوحة دردشة الذكاء الاصطناعي اليمنى -->
            <aside class="chat-panel" aria-label="AI chat panel">
                <h2 class="panel-title">Feature Builder</h2>
                <section id="onboarding-panel" class="onboarding-panel hidden" aria-live="polite">
                    <h3 class="onboarding-title">Ask AI to build or modify your project</h3>
                    <p class="onboarding-subtitle">Try one of these:</p>
                    <div class="onboarding-prompts">
                        <button type="button" class="btn btn-secondary onboarding-prompt" data-prompt="Create a login system">Create a login system</button>
                        <button type="button" class="btn btn-secondary onboarding-prompt" data-prompt="Refactor this file">Refactor this file</button>
                        <button type="button" class="btn btn-secondary onboarding-prompt" data-prompt="Fix this bug">Fix this bug</button>
                    </div>
                    <div class="onboarding-actions">
                        <button type="button" id="onboarding-start-demo" class="btn btn-secondary">Try Demo Mode</button>
                        <button type="button" id="onboarding-dismiss" class="btn">Got it</button>
                    </div>
                    <p class="onboarding-steps">Ask AI here -> Review plan here -> Apply changes</p>
                </section>
                <section id="session-reminder-panel" class="session-reminder-panel hidden" aria-live="polite"></section>
                <section id="ai-analytics-panel" class="ai-analytics-panel" aria-live="polite">
                    <div class="ai-analytics-head">
                        <strong>AI Analytics</strong>
                        <button id="refresh-analytics-btn" class="btn btn-secondary" type="button">Refresh</button>
                    </div>
                    <div class="ai-analytics-grid">
                        <article class="ai-analytics-card">
                            <span class="ai-analytics-label">Decision Success Rate</span>
                            <strong id="metric-decision-success">--%</strong>
                        </article>
                        <article class="ai-analytics-card">
                            <span class="ai-analytics-label">Risk Trend</span>
                            <strong id="metric-risk-trend">--</strong>
                        </article>
                        <article class="ai-analytics-card">
                            <span class="ai-analytics-label">System Stability</span>
                            <strong id="metric-stability">--%</strong>
                        </article>
                        <article class="ai-analytics-card">
                            <span class="ai-analytics-label">Learning Improvement</span>
                            <strong id="metric-learning">--%</strong>
                        </article>
                    </div>
                </section>
                <div id="chat-empty-state" class="chat-empty-state hidden">No actions yet. Start by asking AI.</div>
                <div id="chat-messages" class="chat-messages" aria-live="polite"></div>
                <!-- Action review panel / لوحة مراجعة الإجراءات -->
                <section id="action-review-panel" class="action-review-panel hidden" aria-live="polite"></section>
                <form id="chat-form" class="chat-form">
                    <input
                        id="chat-input"
                        class="chat-input"
                        type="text"
                        placeholder="Ask AI to build feature..."
                        autocomplete="off"
                    >
                    <button class="btn" type="submit">Send</button>
                </form>
            </aside>
        </main>
    </div>

    <script src="js/app.js?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/js/app.js')); ?>"></script>
    <script>
    (function () {
        var wrap = document.getElementById("api-key-inline");
        var btn = document.getElementById("save-api-key-btn");
        var input = document.getElementById("api-key-input");
        if (wrap) {
            wrap.classList.remove("hidden");
            wrap.style.display = "flex";
        }
        if (!btn || !input) return;

        btn.addEventListener("click", function () {
            var apiKey = String(input.value || "").trim();
            if (!apiKey) {
                alert("Please enter API key first.");
                return;
            }
            fetch("php/account.php?action=api-key", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ api_key: apiKey })
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || data.ok !== true) {
                    throw new Error((data && data.error) ? data.error : "Save failed");
                }
                input.value = "";
                alert("API key saved successfully.");
            })
            .catch(function (err) {
                alert("Save key failed: " + (err && err.message ? err.message : "Unknown error"));
            });
        });
    })();
    </script>
    <?php endif; ?>
</body>
</html>
