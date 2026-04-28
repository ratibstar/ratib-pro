(() => {
    "use strict";

    // Core UI references / مراجع عناصر الواجهة الأساسية
    const fileNav = document.getElementById("file-nav");
    const codeEditor = document.getElementById("code-editor");
    const activeFileLabel = document.getElementById("active-file-label");
    const saveBtn = document.getElementById("save-btn");
    const saveStatus = document.getElementById("save-status");
    const chatMessages = document.getElementById("chat-messages");
    const actionReviewPanel = document.getElementById("action-review-panel");
    const chatForm = document.getElementById("chat-form");
    const chatInput = document.getElementById("chat-input");
    const onboardingPanel = document.getElementById("onboarding-panel");
    const onboardingDismiss = document.getElementById("onboarding-dismiss");
    const onboardingStartDemo = document.getElementById("onboarding-start-demo");
    const onboardingPromptButtons = document.querySelectorAll(".onboarding-prompt");
    const chatEmptyState = document.getElementById("chat-empty-state");
    const sessionReminderPanel = document.getElementById("session-reminder-panel");
    const refreshAnalyticsBtn = document.getElementById("refresh-analytics-btn");
    const metricDecisionSuccess = document.getElementById("metric-decision-success");
    const metricRiskTrend = document.getElementById("metric-risk-trend");
    const metricStability = document.getElementById("metric-stability");
    const metricLearning = document.getElementById("metric-learning");
    const apiKeyInline = document.getElementById("api-key-inline");
    const apiKeyInput = document.getElementById("api-key-input");
    const saveApiKeyBtn = document.getElementById("save-api-key-btn");

    // Keep key box visible even if stale cached scripts previously hid it.
    // إبقاء صندوق المفتاح ظاهرًا حتى مع وجود كاش قديم.
    if (apiKeyInline) {
        apiKeyInline.classList.remove("hidden");
    }
    setTimeout(() => {
        if (apiKeyInline) {
            apiKeyInline.classList.remove("hidden");
        }
    }, 1200);
    let lastExecutionForShare = null;

    // In-memory workspace files / ملفات المشروع داخل الذاكرة
    const files = [
        {
            id: "main",
            name: "main.php",
            content: "<?php\n\nfunction runCoreAI(): void\n{\n    echo 'CoreAI is running';\n}\n"
        },
        {
            id: "service",
            name: "ai-service.php",
            content: "<?php\n\nclass AIService\n{\n    public function reply(string $prompt): string\n    {\n        return 'Response for: ' . $prompt;\n    }\n}\n"
        },
        {
            id: "styles",
            name: "theme.css",
            content: ".editor {\n    font-family: Consolas, monospace;\n}\n"
        }
    ];

    const demoFiles = [
        {
            id: "demo-main",
            name: "demo-auth.php",
            content: "<?php\n\nfunction loginUser(string $email, string $password): bool\n{\n    return $email !== '' && $password !== '';\n}\n"
        },
        {
            id: "demo-ui",
            name: "demo-dashboard.js",
            content: "function renderWelcome(userName) {\n    return `Welcome ${userName}`;\n}\n"
        },
        {
            id: "demo-style",
            name: "demo-theme.css",
            content: ".demo-card {\n    border-radius: 10px;\n    padding: 12px;\n}\n"
        }
    ];

    const state = {
        activeFileId: null,
        dirty: false,
        isStreaming: false,
        demoMode: false,
        chatHistory: [],
        persistentHistory: [],
        sessionHistory: [],
        actionApprovals: {},
        pendingActionPlan: null,
        executionPlanReviewed: false
    };
    const firstWinStorageKey = "coreai_first_success_win_shown";
    const lastActivityStorageKey = "coreai_last_activity";

    function getActiveFile() {
        return files.find((file) => file.id === state.activeFileId) || null;
    }

    // Render left navigation / عرض قائمة الملفات في الشريط الأيسر
    function renderFileNav() {
        fileNav.innerHTML = "";
        files.forEach((file) => {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "file-item";
            button.textContent = file.name;
            if (file.id === state.activeFileId) {
                button.classList.add("active");
            }
            button.addEventListener("click", () => setActiveFile(file.id));
            fileNav.appendChild(button);
        });
    }

    // Switch active file / تبديل الملف النشط
    function setActiveFile(fileId) {
        const nextFile = files.find((file) => file.id === fileId);
        if (!nextFile) {
            return;
        }
        state.activeFileId = fileId;
        state.dirty = false;
        activeFileLabel.textContent = nextFile.name;
        codeEditor.value = nextFile.content;
        updateSaveStatus();
        renderFileNav();
    }

    // Save current editor content / حفظ محتوى المحرر الحالي
    function saveCurrentFile() {
        const file = getActiveFile();
        if (!file) {
            return;
        }
        file.content = codeEditor.value;
        state.dirty = false;
        updateSaveStatus("Saved");
    }

    function updateSaveStatus(label) {
        if (label) {
            saveStatus.textContent = label;
            return;
        }
        saveStatus.textContent = state.dirty ? "Unsaved changes" : "Ready";
    }

    // Append a message to chat / إضافة رسالة إلى الدردشة
    function appendMessage(role, text) {
        const message = document.createElement("div");
        message.className = `msg ${role}`;
        message.textContent = text;
        chatMessages.appendChild(message);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        setEmptyStateVisible(false);
        return message;
    }

    function normalizeRole(role) {
        return role === "assistant" ? "ai" : role;
    }

    /**
     * This onboarding flow helps users quickly understand how to use CoreAI without exposing internal system complexity.
     * هذا النظام التمهيدي يساعد المستخدم على فهم استخدام CoreAI بسرعة دون إظهار التعقيدات الداخلية.
     */
    function setOnboardingVisible(visible) {
        if (!onboardingPanel) {
            return;
        }
        onboardingPanel.classList.toggle("hidden", !visible);
    }

    function setEmptyStateVisible(visible) {
        if (!chatEmptyState) {
            return;
        }
        chatEmptyState.classList.toggle("hidden", !visible);
    }

    function applyGuidedHighlights() {
        chatInput?.classList.add("onboarding-highlight");
        actionReviewPanel?.classList.add("onboarding-highlight");
        const approveBtn = document.getElementById("execute-approved-actions");
        if (approveBtn) {
            approveBtn.classList.add("onboarding-highlight");
        }
    }

    function clearGuidedHighlights() {
        chatInput?.classList.remove("onboarding-highlight");
        actionReviewPanel?.classList.remove("onboarding-highlight");
        const approveBtn = document.getElementById("execute-approved-actions");
        if (approveBtn) {
            approveBtn.classList.remove("onboarding-highlight");
        }
    }

    function isFirstTimeUser() {
        return localStorage.getItem("coreai_onboarding_done") !== "1";
    }

    function markOnboardingDone() {
        localStorage.setItem("coreai_onboarding_done", "1");
        setOnboardingVisible(false);
        clearGuidedHighlights();
    }

    function enableDemoMode() {
        state.demoMode = true;
        files.splice(0, files.length, ...demoFiles.map((file) => ({ ...file })));
        renderFileNav();
        setActiveFile(files[0].id);
        saveStatus.textContent = "Demo Mode";
        appendMessage("ai", "Demo mode is active. You can try safely.");
    }

    /**
     * Hide action panel when normal text flow is used.
     * إخفاء لوحة الإجراءات عند استخدام مسار النص العادي.
     */
    function clearActionPanel() {
        actionReviewPanel.innerHTML = "";
        actionReviewPanel.classList.add("hidden");
        state.actionApprovals = {};
        state.pendingActionPlan = null;
        state.executionPlanReviewed = false;
    }

    /**
     * This feature enables users to share AI-generated improvements, helping CoreAI grow through user-driven distribution.
     * هذه الميزة تسمح للمستخدمين بمشاركة نتائج تحسيناتهم، مما يساعد على انتشار CoreAI عبر المستخدمين.
     */
    function renderShareResultAction() {
        if (!actionReviewPanel || !lastExecutionForShare) {
            return;
        }
        const stats = lastExecutionForShare.stats || {};
        const diffSummary = `${Number(stats.totalChangedLines || 0)} lines changed across ${Number(stats.changedFilesCount || 0)} file(s).`;
        let holder = document.getElementById("share-result-holder");
        if (!holder) {
            holder = document.createElement("div");
            holder.id = "share-result-holder";
            holder.className = "share-result-card";
        }
        const parentWin = actionReviewPanel.querySelector(".win-moment-panel");
        if (parentWin && parentWin.parentNode) {
            parentWin.insertAdjacentElement("afterend", holder);
        } else {
            actionReviewPanel.appendChild(holder);
        }
        holder.innerHTML = `
            <div class="share-brand-row">
                <div class="share-logo-mark">C</div>
                <div>
                    <h4 class="share-result-title">CoreAI</h4>
                    <p class="action-meta">AI that improves your code</p>
                </div>
            </div>
            <h4 class="share-result-title">Share this improvement</h4>
            <p class="share-key-summary"><strong>Before vs after:</strong> ${escapeHtml(diffSummary)}</p>
            <p class="action-meta"><strong>Lines changed:</strong> ${Number(stats.totalChangedLines || 0)}</p>
            <p class="share-gains"><strong>Performance/Stability:</strong> +${Number(stats.performanceGain || 0)}% / ${Number(stats.stabilityGain || 0) >= 0 ? "+" : ""}${Number(stats.stabilityGain || 0)}%</p>
            <p class="action-meta"><strong>AI explanation:</strong> ${escapeHtml(String(lastExecutionForShare.aiExplanation || ""))}</p>
            <div class="action-controls">
                <button type="button" class="btn" id="share-result-btn">Share this improvement</button>
                <button type="button" class="btn btn-secondary" id="copy-share-link-btn" disabled>Copy link</button>
                <button type="button" class="btn btn-secondary" id="download-share-image-btn" disabled>Download image</button>
            </div>
            <div class="share-footer-row">
                <span class="share-watermark">Generated by CoreAI</span>
                <a class="share-cta-link" href="index.php" target="_blank" rel="noopener noreferrer">Try CoreAI</a>
            </div>
            <span id="share-result-link" class="action-meta"></span>
        `;
        const shareBtn = document.getElementById("share-result-btn");
        const copyBtn = document.getElementById("copy-share-link-btn");
        const imageBtn = document.getElementById("download-share-image-btn");
        const linkNode = document.getElementById("share-result-link");
        let sharedUrl = "";

        const buildShareImage = () => {
            const canvas = document.createElement("canvas");
            canvas.width = 1200;
            canvas.height = 630;
            const ctx = canvas.getContext("2d");
            if (!ctx) {
                throw new Error("Canvas unavailable");
            }
            ctx.fillStyle = "#0f172a";
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = "#1e40af";
            ctx.beginPath();
            ctx.arc(90, 80, 34, 0, Math.PI * 2);
            ctx.fill();
            ctx.fillStyle = "#ffffff";
            ctx.font = "bold 42px Segoe UI";
            ctx.fillText("C", 77, 96);
            ctx.fillStyle = "#bfdbfe";
            ctx.font = "bold 40px Segoe UI";
            ctx.fillText("CoreAI", 145, 92);
            ctx.fillStyle = "#93c5fd";
            ctx.font = "24px Segoe UI";
            ctx.fillText("AI that improves your code", 145, 126);
            ctx.fillStyle = "#bbf7d0";
            ctx.font = "bold 34px Segoe UI";
            ctx.fillText(`Before vs After: ${Number(stats.totalChangedLines || 0)} lines improved`, 60, 210);
            ctx.fillStyle = "#dbeafe";
            ctx.font = "30px Segoe UI";
            ctx.fillText(`Performance gain: +${Number(stats.performanceGain || 0)}%`, 60, 280);
            ctx.fillText(`Stability gain: ${Number(stats.stabilityGain || 0) >= 0 ? "+" : ""}${Number(stats.stabilityGain || 0)}%`, 60, 330);
            ctx.fillStyle = "#93c5fd";
            ctx.font = "24px Segoe UI";
            ctx.fillText(String(lastExecutionForShare.aiExplanation || "").slice(0, 95), 60, 400);
            ctx.fillStyle = "rgba(148, 163, 184, 0.25)";
            ctx.font = "bold 46px Segoe UI";
            ctx.fillText("Generated by CoreAI", 650, 590);
            ctx.fillStyle = "#a7f3d0";
            ctx.font = "bold 24px Segoe UI";
            ctx.fillText("Try CoreAI", 60, 560);
            ctx.fillStyle = "#93c5fd";
            ctx.font = "20px Segoe UI";
            ctx.fillText(sharedUrl.slice(0, 100), 60, 595);
            const anchor = document.createElement("a");
            anchor.href = canvas.toDataURL("image/png");
            anchor.download = "coreai-share-card.png";
            anchor.click();
        };

        shareBtn?.addEventListener("click", async () => {
            try {
                const response = await fetch("php/share_result.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        execution: lastExecutionForShare.execution,
                        ai_explanation: lastExecutionForShare.aiExplanation,
                        share_card: {
                            lines_changed: Number(stats.totalChangedLines || 0),
                            performance_gain: Number(stats.performanceGain || 0),
                            stability_gain: Number(stats.stabilityGain || 0),
                            diff_summary: diffSummary
                        }
                    })
                });
                const data = await response.json();
                if (!response.ok || data.ok !== true) {
                    throw new Error(data.error || `HTTP ${response.status}`);
                }
                const url = String(data.share_url || "");
                sharedUrl = url;
                if (linkNode) {
                    linkNode.innerHTML = url
                        ? `<a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer">Open public preview</a>`
                        : "Shared.";
                }
                if (copyBtn) {
                    copyBtn.disabled = url === "";
                }
                if (imageBtn) {
                    imageBtn.disabled = url === "";
                }
            } catch (error) {
                if (linkNode) {
                    linkNode.textContent = `Share failed: ${error.message || "Unknown error"}`;
                }
            }
        });

        copyBtn?.addEventListener("click", async () => {
            if (!sharedUrl) {
                return;
            }
            try {
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(sharedUrl);
                    if (linkNode) {
                        linkNode.textContent = "Link copied.";
                    }
                }
            } catch (error) {
                if (linkNode) {
                    linkNode.textContent = "Copy failed.";
                }
            }
        });

        imageBtn?.addEventListener("click", () => {
            if (!sharedUrl) {
                return;
            }
            try {
                buildShareImage();
                if (linkNode) {
                    linkNode.textContent = "Image downloaded.";
                }
            } catch (error) {
                if (linkNode) {
                    linkNode.textContent = "Image export failed.";
                }
            }
        });
    }

    function countChangedLines(beforeContent, afterContent) {
        const beforeLines = String(beforeContent || "").split(/\r?\n/);
        const afterLines = String(afterContent || "").split(/\r?\n/);
        const max = Math.max(beforeLines.length, afterLines.length);
        let changed = 0;
        for (let i = 0; i < max; i += 1) {
            if ((beforeLines[i] ?? null) !== (afterLines[i] ?? null)) {
                changed += 1;
            }
        }
        return changed;
    }

    function buildExecutionWinStats(executionData) {
        const groups = Array.isArray(executionData?.groups) ? executionData.groups : [];
        const fileChanges = [];
        let totalChangedLines = 0;
        let totalOps = 0;
        let successfulOps = 0;
        let perfScoreSum = 0;
        let perfScoreCount = 0;
        let stabilityGainSum = 0;
        let stabilityGainCount = 0;

        groups.forEach((group) => {
            const results = Array.isArray(group?.results) ? group.results : [];
            results.forEach((row) => {
                const changedLines = countChangedLines(row?.before_content || "", row?.after_content || "");
                totalChangedLines += changedLines;
                totalOps += 1;
                if (row?.ok === true) {
                    successfulOps += 1;
                }
                fileChanges.push({
                    target: String(row?.target || "unknown"),
                    changedLines
                });
            });

            const score = Number(group?.prediction_accuracy_score || 0);
            if (Number.isFinite(score) && score > 0) {
                perfScoreSum += score;
                perfScoreCount += 1;
            }

            const stability = group?.deviation_report?.stability;
            const predicted = Number(stability?.predicted_score);
            const actual = Number(stability?.actual_score);
            if (Number.isFinite(predicted) && Number.isFinite(actual)) {
                stabilityGainSum += (actual - predicted);
                stabilityGainCount += 1;
            }
        });

        fileChanges.sort((a, b) => b.changedLines - a.changedLines);
        const topFileChanges = fileChanges.slice(0, 4);
        const impactSummary = String(executionData?.summary || "Execution complete.");
        const performanceGain = perfScoreCount > 0
            ? Math.max(0, Math.round((perfScoreSum / perfScoreCount) - 50))
            : Math.max(0, Math.round((successfulOps / Math.max(totalOps, 1)) * 30));
        const stabilityGain = stabilityGainCount > 0 ? Math.round(stabilityGainSum / stabilityGainCount) : 0;

        return {
            impactSummary,
            totalChangedLines,
            topFileChanges,
            performanceGain,
            stabilityGain
        };
    }

    function renderFirstExecutionWinMoment(executionData) {
        if (localStorage.getItem(firstWinStorageKey) === "1") {
            return;
        }
        if (!executionData || executionData.ok !== true || !actionReviewPanel) {
            return;
        }

        const stats = buildExecutionWinStats(executionData);
        const filesHtml = stats.topFileChanges.map(
            (item) => `<li><code>${escapeHtml(item.target)}</code> — ${item.changedLines} line(s)</li>`
        ).join("");

        const panel = document.createElement("section");
        panel.className = "win-moment-panel";
        panel.innerHTML = `
            <h3 class="win-moment-title">CoreAI improved your project</h3>
            <p class="action-meta"><strong>Impact summary:</strong> ${escapeHtml(stats.impactSummary)}</p>
            <p class="action-meta"><strong>Lines changed:</strong> ${stats.totalChangedLines}</p>
            <p class="action-meta"><strong>Performance gain:</strong> +${stats.performanceGain}%</p>
            <p class="action-meta"><strong>Stability gain:</strong> ${stats.stabilityGain >= 0 ? "+" : ""}${stats.stabilityGain}%</p>
            <details class="plan-details" open>
                <summary>Top changed files</summary>
                <ul>${filesHtml || "<li>No file details available.</li>"}</ul>
            </details>
        `;
        actionReviewPanel.prepend(panel);
        actionReviewPanel.classList.remove("hidden");
        appendMessage("ai", "CoreAI improved your project");
        localStorage.setItem(firstWinStorageKey, "1");
    }

    function saveLastActivity(activity) {
        try {
            localStorage.setItem(lastActivityStorageKey, JSON.stringify(activity));
        } catch (error) {
            // Ignore storage failures silently.
        }
    }

    function loadLastActivity() {
        try {
            const raw = localStorage.getItem(lastActivityStorageKey);
            if (!raw) {
                return null;
            }
            const parsed = JSON.parse(raw);
            return parsed && typeof parsed === "object" ? parsed : null;
        } catch (error) {
            return null;
        }
    }

    function renderSessionReminder() {
        if (!sessionReminderPanel) {
            return;
        }
        const last = loadLastActivity();
        if (!last) {
            sessionReminderPanel.classList.add("hidden");
            return;
        }

        const lastFile = String(last.activeFileName || "project file");
        const summary = String(last.impactSummary || "You made progress in your previous session.");
        const changed = Number(last.totalChangedLines || 0);
        const perf = Number(last.performanceGain || 0);
        const stability = Number(last.stabilityGain || 0);
        const at = String(last.timestamp || "");

        sessionReminderPanel.innerHTML = `
            <h3 class="session-reminder-title">Continue your last project</h3>
            <p class="action-meta">${escapeHtml(summary)}</p>
            <p class="action-meta">Last file: <code>${escapeHtml(lastFile)}</code></p>
            <p class="action-meta">Last improvement stats: ${changed} lines changed | +${perf}% performance | ${stability >= 0 ? "+" : ""}${stability}% stability</p>
            <p class="action-meta">${escapeHtml(at)}</p>
            <div class="action-controls">
                <button type="button" class="btn btn-secondary" id="continue-last-project-btn">Continue</button>
            </div>
        `;
        sessionReminderPanel.classList.remove("hidden");

        const continueBtn = document.getElementById("continue-last-project-btn");
        continueBtn?.addEventListener("click", () => {
            const fileName = String(last.activeFileName || "");
            const target = files.find((f) => f.name === fileName);
            if (target) {
                setActiveFile(target.id);
            }
            chatInput.value = "Continue from my last project improvements.";
            chatInput.focus();
            sessionReminderPanel.classList.add("hidden");
        });
    }

    /**
     * Strip markdown fences before JSON parsing.
     * إزالة حدود markdown قبل محاولة تحليل JSON.
     */
    function stripJsonFences(text) {
        const trimmed = text.trim();
        if (!trimmed.startsWith("```")) {
            return trimmed;
        }
        return trimmed
            .replace(/^```(?:json)?\s*/i, "")
            .replace(/\s*```$/, "")
            .trim();
    }

    /**
     * Safely parse action JSON with schema checks.
     * تحليل JSON بشكل آمن مع التحقق من البنية المطلوبة.
     */
    function parseActionPlan(rawText) {
        try {
            const candidate = stripJsonFences(rawText);
            const parsed = JSON.parse(candidate);
            if (!parsed || typeof parsed !== "object") {
                return null;
            }
            if (parsed.requires_action !== true || !Array.isArray(parsed.actions)) {
                return null;
            }
            if (!["build", "refactor", "debug"].includes(parsed.intent)) {
                return null;
            }

            const safeActions = parsed.actions
                .filter((item) => item && typeof item === "object")
                .map((item, index) => ({
                    step: Number.isFinite(item.step) ? item.step : index + 1,
                    target: String(item.target || "unknown"),
                    operation: String(item.operation || "modify"),
                    details: String(item.details || "")
                }));

            if (safeActions.length === 0) {
                return null;
            }

            return {
                intent: parsed.intent,
                summary: String(parsed.summary || "Action plan generated"),
                action_type: String(parsed.action_type || "unknown"),
                actions: safeActions,
                risks: Array.isArray(parsed.risks) ? parsed.risks.map((r) => String(r)) : []
            };
        } catch (error) {
            return null;
        }
    }

    /**
     * Parse simplified direct file actions JSON.
     * تحليل JSON المبسط للإجراءات المباشرة على الملفات.
     */
    function parseDirectFileActions(rawText) {
        try {
            const candidate = stripJsonFences(rawText);
            const parsed = JSON.parse(candidate);
            let rawActions = [];

            // Accept both formats:
            // 1) { "actions": [...] }
            // 2) [ ... ]
            // قبول الصيغتين: كائن actions أو مصفوفة مباشرة.
            if (Array.isArray(parsed)) {
                rawActions = parsed;
            } else if (parsed && typeof parsed === "object" && Array.isArray(parsed.actions)) {
                rawActions = parsed.actions;
            } else {
                return null;
            }

            const normalized = rawActions
                .filter((item) => item && typeof item === "object")
                .map((item) => ({
                    operation: String(item.operation || item.type || "modify").toLowerCase() === "replace"
                        ? "modify"
                        : String(item.operation || item.type || "modify").toLowerCase(),
                    target: String(item.target || item.file || "").trim(),
                    details: String(item.details || item.content || ""),
                    approved: true
                }))
                .filter((item) => item.target !== "" && ["create", "modify", "update", "delete"].includes(item.operation));

            if (normalized.length === 0) {
                return null;
            }
            return normalized;
        } catch (error) {
            return null;
        }
    }

    /**
     * Detect explicit user intent for auto file apply.
     * اكتشاف طلب المستخدم للتنفيذ التلقائي المباشر.
     */
    function wantsAutoApply(userMessage) {
        const text = String(userMessage || "").toLowerCase();
        return /create and apply|apply now|execute now|auto apply|apply directly|create|build|generate|make|نفذ الآن|طبق الآن|نفّذ مباشرة|تطبيق مباشر|اعمل|انشئ|أنشئ|ابني|سو|سوي/.test(text);
    }

    function classifyOperation(operationText) {
        const op = operationText.toLowerCase();
        if (op.includes("delete") || op.includes("remove")) {
            return "delete";
        }
        if (op.includes("create") || op.includes("add") || op.includes("new")) {
            return "create";
        }
        return "modify";
    }

    function toBackendOperation(operationText) {
        return classifyOperation(operationText);
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    /**
     * Render a simplified plan preview for user review.
     * عرض معاينة مبسطة للخطة لمراجعة المستخدم.
     */
    function renderExecutionPlanPreview(plan) {
        const planBox = document.getElementById("execution-plan-output");
        if (!planBox) {
            return;
        }

        const groups = Array.isArray(plan.groups) ? plan.groups : [];
        const groupCards = groups.map((group) => {
            const affected = Array.isArray(group.affected_files) ? group.affected_files : [];
            const operations = Array.isArray(group.operations) ? group.operations : [];

            const operationsList = operations.map(
                (op) => `<li><code>${escapeHtml(op.operation || "modify")}</code> -> <code>${escapeHtml(op.target || "")}</code></li>`
            ).join("");

            return `
                <article class="plan-group-card">
                    <div class="plan-group-head">
                        <strong>${escapeHtml(group.group_id || "group")}</strong>
                        <span class="action-meta">${operations.length} steps</span>
                    </div>
                    <p class="action-meta"><strong>Changes:</strong> ${affected.map((f) => `<code>${escapeHtml(f)}</code>`).join(", ") || "[none]"}</p>
                    <details class="plan-details" open>
                        <summary>Planned Steps</summary>
                        <ul>${operationsList || "<li>No operations.</li>"}</ul>
                    </details>
                </article>
            `;
        }).join("");

        const impactedFiles = Array.isArray(plan.affected_files) ? plan.affected_files : [];
        planBox.innerHTML = `
            <section class="plan-preview">
                <div class="plan-preview-head">
                    <strong>Review Plan</strong>
                    <span class="action-meta">${Number(plan.group_count || groups.length)} group(s)</span>
                </div>
                <p class="action-meta">This execution will update ${impactedFiles.length} file(s).</p>
                <div class="plan-groups">${groupCards || "<p class='action-meta'>No groups found.</p>"}</div>
            </section>
        `;
    }

    /**
     * Render review-only action UI (no execution).
     * عرض واجهة مراجعة الإجراءات فقط (بدون تنفيذ).
     */
    function renderActionPanel(plan) {
        clearActionPanel();
        state.pendingActionPlan = plan;

        const header = document.createElement("div");
        header.className = "action-header";
        header.innerHTML = `
            <strong>Build Flow</strong>
            <span class="action-meta">Ask -> Review -> Approve</span>
        `;
        actionReviewPanel.appendChild(header);

        const summary = document.createElement("p");
        summary.className = "action-meta";
        summary.textContent = plan.summary || "CoreAI prepared a feature implementation plan.";
        actionReviewPanel.appendChild(summary);

        const list = document.createElement("ul");
        list.className = "action-list";

        plan.actions.forEach((action, idx) => {
            const actionId = `action-${idx + 1}`;
            state.actionApprovals[actionId] = false;
            const li = document.createElement("li");
            li.className = "action-item";
            li.innerHTML = `
                <p><strong>#${action.step} ${classifyOperation(action.operation).toUpperCase()}</strong> - ${action.target}</p>
                <p>${action.details}</p>
                <div class="action-meta">Simple decision:</div>
                <div class="action-controls">
                    <button type="button" class="btn btn-secondary action-approve-btn" data-action-id="${actionId}">Include</button>
                    <button type="button" class="btn btn-secondary action-skip-btn" data-action-id="${actionId}">Skip</button>
                </div>
            `;
            list.appendChild(li);
        });
        actionReviewPanel.appendChild(list);

        const controls = document.createElement("div");
        controls.className = "action-controls";
        controls.innerHTML = `
            <button type="button" class="btn btn-secondary" id="approve-all-actions">Include All</button>
            <button type="button" class="btn btn-secondary" id="reject-all-actions">Skip All</button>
            <button type="button" class="btn btn-secondary" id="build-execution-plan">Review Plan</button>
            <button type="button" class="btn" id="execute-approved-actions" disabled>Approve Execution</button>
        `;
        actionReviewPanel.appendChild(controls);

        const planOutput = document.createElement("div");
        planOutput.id = "execution-plan-output";
        planOutput.className = "execution-plan-output";
        planOutput.textContent = "Click 'Review Plan' before approving execution.";
        actionReviewPanel.appendChild(planOutput);

        actionReviewPanel.querySelectorAll(".action-approve-btn").forEach((button) => {
            button.addEventListener("click", () => {
                const actionId = button.getAttribute("data-action-id");
                if (!actionId) {
                    return;
                }
                state.actionApprovals[actionId] = !state.actionApprovals[actionId];
                button.textContent = state.actionApprovals[actionId]
                    ? "Included"
                    : "Include";
            });
        });
        actionReviewPanel.querySelectorAll(".action-skip-btn").forEach((button) => {
            button.addEventListener("click", () => {
                const actionId = button.getAttribute("data-action-id");
                if (!actionId) {
                    return;
                }
                state.actionApprovals[actionId] = false;
                const includeButton = actionReviewPanel.querySelector(`.action-approve-btn[data-action-id="${actionId}"]`);
                if (includeButton) {
                    includeButton.textContent = "Include";
                }
            });
        });

        const approveAll = document.getElementById("approve-all-actions");
        const rejectAll = document.getElementById("reject-all-actions");
        if (approveAll) {
            approveAll.addEventListener("click", () => {
                Object.keys(state.actionApprovals).forEach((id) => {
                    state.actionApprovals[id] = true;
                });
                actionReviewPanel.querySelectorAll(".action-approve-btn").forEach((button) => {
                    button.textContent = "Included";
                });
            });
        }
        if (rejectAll) {
            rejectAll.addEventListener("click", () => {
                Object.keys(state.actionApprovals).forEach((id) => {
                    state.actionApprovals[id] = false;
                });
                actionReviewPanel.querySelectorAll(".action-approve-btn").forEach((button) => {
                    button.textContent = "Include";
                });
            });
        }

        const executeApproved = document.getElementById("execute-approved-actions");
        const buildPlan = document.getElementById("build-execution-plan");
        if (buildPlan) {
            buildPlan.addEventListener("click", () => {
                buildExecutionPlan().catch((error) => {
                    appendMessage("ai", `Plan error: ${error.message || "Unknown error"}`);
                });
            });
        }
        if (executeApproved) {
            executeApproved.addEventListener("click", () => {
                executeApprovedActions().catch((error) => {
                    appendMessage("ai", `Execution error: ${error.message || "Unknown error"}`);
                });
            });
        }

        actionReviewPanel.classList.remove("hidden");
        applyGuidedHighlights();
    }

    /**
     * Send only approved actions to backend for safe execution.
     * إرسال الإجراءات المعتمدة فقط للخلفية من أجل التنفيذ الآمن.
     */
    async function executeApprovedActions() {
        if (!state.pendingActionPlan) {
            appendMessage("ai", "No action plan available for execution.");
            return;
        }

        const approvedActions = state.pendingActionPlan.actions
            .map((action, index) => ({ action, id: `action-${index + 1}` }))
            .filter((row) => state.actionApprovals[row.id] === true)
            .map((row) => ({
                operation: toBackendOperation(row.action.operation),
                target: row.action.target,
                details: row.action.details,
                approved: true
            }));

        if (approvedActions.length === 0) {
            appendMessage("ai", "No steps selected. Include at least one step.");
            return;
        }
        if (!state.executionPlanReviewed) {
            appendMessage("ai", "Please review the plan before approving execution.");
            return;
        }

        if (state.demoMode) {
            const summary = `Demo apply complete. ${approvedActions.length} step(s) were simulated safely.`;
            appendMessage("ai", summary);
            addToSessionMemory("assistant", summary);
            markOnboardingDone();
            return;
        }

        const response = await fetch("php/execute.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                approvalConfirmed: true,
                actionGroups: [
                    {
                        id: `plan-group-${Date.now()}`,
                        actions: approvedActions
                    }
                ],
                actions: approvedActions
            })
        });

        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || `HTTP ${response.status}`);
        }

        const lines = Array.isArray(data.groups)
            ? data.groups.flatMap((group) => {
                const groupHeader = `Group ${group.group_id}: ${group.summary}`;
                const detailLines = Array.isArray(group.results)
                    ? group.results.map((item) => {
                        const mark = item.ok ? "OK" : "BLOCKED";
                        return `- [${mark}] ${item.operation} ${item.target}: ${item.message}`;
                    })
                    : [];
                if (group.rolled_back) {
                    detailLines.push("- [ROLLBACK] Group rollback applied.");
                }
                return [groupHeader, ...detailLines];
            })
            : [];
        const report = [data.summary || "Execution complete.", ...lines].join("\n");
        appendMessage("ai", report);
        addToSessionMemory("assistant", report);
        const winStats = buildExecutionWinStats(data);
        const shortExplanation = String(state.pendingActionPlan?.summary || "CoreAI applied approved changes.").slice(0, 180);
        lastExecutionForShare = {
            execution: data,
            aiExplanation: shortExplanation,
            stats: {
                totalChangedLines: winStats.totalChangedLines,
                changedFilesCount: winStats.topFileChanges.length,
                performanceGain: winStats.performanceGain,
                stabilityGain: winStats.stabilityGain
            }
        };
        saveLastActivity({
            timestamp: new Date().toLocaleString(),
            activeFileName: getActiveFile()?.name || "",
            impactSummary: winStats.impactSummary,
            totalChangedLines: winStats.totalChangedLines,
            performanceGain: winStats.performanceGain,
            stabilityGain: winStats.stabilityGain
        });
        renderFirstExecutionWinMoment(data);
        renderShareResultAction();
        markOnboardingDone();
    }

    /**
     * Execute already-approved backend actions directly (auto apply mode).
     * تنفيذ مباشر للإجراءات المعتمدة مسبقًا (وضع التنفيذ التلقائي).
     */
    async function executeDirectActions(approvedActions, summaryText) {
        if (!Array.isArray(approvedActions) || approvedActions.length === 0) {
            appendMessage("ai", "No executable actions found.");
            return;
        }

        const response = await fetch("php/execute.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                approvalConfirmed: true,
                actionGroups: [
                    {
                        id: `auto-group-${Date.now()}`,
                        actions: approvedActions
                    }
                ],
                actions: approvedActions
            })
        });

        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || `HTTP ${response.status}`);
        }

        const lines = Array.isArray(data.groups)
            ? data.groups.flatMap((group) => {
                const groupHeader = `Group ${group.group_id}: ${group.summary}`;
                const detailLines = Array.isArray(group.results)
                    ? group.results.map((item) => {
                        const mark = item.ok ? "OK" : "BLOCKED";
                        return `- [${mark}] ${item.operation} ${item.target}: ${item.message}`;
                    })
                    : [];
                if (group.rolled_back) {
                    detailLines.push("- [ROLLBACK] Group rollback applied.");
                }
                return [groupHeader, ...detailLines];
            })
            : [];
        const report = [data.summary || "Execution complete.", ...lines].join("\n");
        appendMessage("ai", report);
        addToSessionMemory("assistant", report);

        const winStats = buildExecutionWinStats(data);
        lastExecutionForShare = {
            execution: data,
            aiExplanation: String(summaryText || "CoreAI auto-applied changes.").slice(0, 180),
            stats: {
                totalChangedLines: winStats.totalChangedLines,
                changedFilesCount: winStats.topFileChanges.length,
                performanceGain: winStats.performanceGain,
                stabilityGain: winStats.stabilityGain
            }
        };
        saveLastActivity({
            timestamp: new Date().toLocaleString(),
            activeFileName: getActiveFile()?.name || "",
            impactSummary: winStats.impactSummary,
            totalChangedLines: winStats.totalChangedLines,
            performanceGain: winStats.performanceGain,
            stabilityGain: winStats.stabilityGain
        });
        renderFirstExecutionWinMoment(data);
        renderShareResultAction();
        markOnboardingDone();
    }

    /**
     * Build execution plan and show impact before approval.
     * بناء خطة التنفيذ وعرض التأثير قبل الموافقة النهائية.
     */
    async function buildExecutionPlan() {
        if (!state.pendingActionPlan) {
            appendMessage("ai", "No action plan available.");
            return;
        }

        const approvedActions = state.pendingActionPlan.actions
            .map((action, index) => ({ action, id: `action-${index + 1}` }))
            .filter((row) => state.actionApprovals[row.id] === true)
            .map((row) => ({
                operation: toBackendOperation(row.action.operation),
                target: row.action.target,
                details: row.action.details,
                approved: true
            }));

        if (approvedActions.length === 0) {
            appendMessage("ai", "Select at least one step to review the plan.");
            return;
        }

        const actionGroups = [
            {
                id: `plan-group-${Date.now()}`,
                actions: approvedActions
            }
        ];

        const response = await fetch("php/plan.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ actionGroups })
        });
        const data = await response.json();
        if (!response.ok || !data.ok) {
            throw new Error(data.error || `HTTP ${response.status}`);
        }

        renderExecutionPlanPreview(data.plan);

        state.executionPlanReviewed = true;
        const executeApproved = document.getElementById("execute-approved-actions");
        if (executeApproved) {
            executeApproved.disabled = false;
        }
    }

    /**
     * Build combined history from both memory layers.
     * إنشاء سجل موحد من الذاكرة المؤقتة والدائمة.
     */
    function rebuildCombinedHistory() {
        state.chatHistory = [...state.persistentHistory, ...state.sessionHistory];
    }

    /**
     * Render merged history to chat UI.
     * عرض السجل المدمج داخل واجهة الدردشة.
     */
    function renderCombinedHistory() {
        chatMessages.innerHTML = "";
        state.chatHistory.forEach((item) => {
            appendMessage(normalizeRole(item.role), item.content);
        });
    }

    /**
     * Persist only temporary session memory in browser.
     * حفظ الذاكرة المؤقتة الخاصة بالجلسة داخل المتصفح فقط.
     */
    function persistSessionLayer() {
        const payload = {
            basePersistentCount: state.persistentHistory.length,
            sessionHistory: state.sessionHistory
        };
        sessionStorage.setItem("coreai_session_memory", JSON.stringify(payload));
    }

    /**
     * Add message to temporary session layer.
     * إضافة الرسالة إلى الذاكرة المؤقتة.
     */
    function addToSessionMemory(role, content) {
        state.sessionHistory.push({ role, content });
        rebuildCombinedHistory();
        persistSessionLayer();
    }

    /**
     * Load persistent memory from backend storage.
     * تحميل الذاكرة الدائمة من التخزين الخلفي.
     */
    async function loadPersistentMemory() {
        const response = await fetch("php/memory.php", { method: "GET" });
        if (!response.ok) {
            throw new Error(`Failed to load memory: HTTP ${response.status}`);
        }

        const data = await response.json();
        const history = Array.isArray(data.history) ? data.history : [];
        state.persistentHistory = history
            .filter((item) => item && typeof item.role === "string" && typeof item.content === "string")
            .map((item) => ({
                role: item.role === "ai" ? "assistant" : item.role,
                content: item.content
            }));
    }

    /**
     * Restore temporary layer if it belongs to same persistent base.
     * استعادة الذاكرة المؤقتة إذا كانت متوافقة مع الأساس الدائم الحالي.
     */
    function restoreSessionLayer() {
        const raw = sessionStorage.getItem("coreai_session_memory");
        if (!raw) {
            state.sessionHistory = [];
            return;
        }

        try {
            const parsed = JSON.parse(raw);
            const baseCount = Number(parsed.basePersistentCount || 0);
            const sessionHistory = Array.isArray(parsed.sessionHistory) ? parsed.sessionHistory : [];

            // If backend memory changed, reset temporary layer to avoid duplicates.
            // إذا تغيّرت الذاكرة الدائمة نعيد ضبط الذاكرة المؤقتة لتجنب التكرار.
            if (baseCount !== state.persistentHistory.length) {
                state.sessionHistory = [];
                persistSessionLayer();
                return;
            }

            state.sessionHistory = sessionHistory
                .filter((item) => item && typeof item.role === "string" && typeof item.content === "string")
                .map((item) => ({ role: item.role, content: item.content }));
        } catch (error) {
            state.sessionHistory = [];
            persistSessionLayer();
        }
    }

    /**
     * Read current selected text in editor.
     * قراءة النص المحدد حاليا من المحرر.
     */
    function getSelectedCode() {
        const start = codeEditor.selectionStart || 0;
        const end = codeEditor.selectionEnd || 0;
        if (end <= start) {
            return "";
        }
        return codeEditor.value.slice(start, end);
    }

    function setChatBusy(isBusy) {
        state.isStreaming = isBusy;
        chatInput.disabled = isBusy;
        saveBtn.disabled = isBusy;
    }

    /**
     * Send chat payload to backend and stream response.
     * إرسال الطلب إلى الخلفية واستقبال الرد بشكل متدفق.
     */
    async function streamAIReply(userMessage, historyWindow) {
        const file = getActiveFile();
        const aiMessageEl = appendMessage("ai", "");
        clearActionPanel();

        if (!file) {
            aiMessageEl.textContent = "No active file selected.";
            return;
        }

        const payload = {
            userMessage,
            activeFileName: file.name,
            activeFileContent: codeEditor.value,
            selectedCode: getSelectedCode(),
            history: historyWindow
        };

        setChatBusy(true);
        saveStatus.textContent = "AI streaming...";

        try {
            const response = await fetch("php/ai.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(payload)
            });

            if (!response.ok || !response.body) {
                const errorText = await response.text();
                throw new Error(errorText || `HTTP ${response.status}`);
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let fullText = "";

            // Append streamed chunks in real time / إضافة أجزاء الرد لحظيا
            while (true) {
                const { value, done } = await reader.read();
                if (done) {
                    break;
                }
                fullText += decoder.decode(value, { stream: true });
                aiMessageEl.textContent = fullText;
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            if (!fullText.trim()) {
                aiMessageEl.textContent = "No content received from AI backend.";
            }
            const actionPlan = parseActionPlan(fullText);
            if (actionPlan) {
                aiMessageEl.remove();
                if (wantsAutoApply(userMessage)) {
                    const approvedActions = actionPlan.actions.map((action) => ({
                        operation: toBackendOperation(action.operation),
                        target: action.target,
                        details: action.details,
                        approved: true
                    }));
                    await executeDirectActions(approvedActions, actionPlan.summary);
                } else {
                    renderActionPanel(actionPlan);
                    const actionSummary = `Action plan (${actionPlan.intent}) with ${actionPlan.actions.length} steps.`;
                    addToSessionMemory("assistant", actionSummary);
                }
            } else {
                const directActions = parseDirectFileActions(fullText);
                if (directActions && wantsAutoApply(userMessage)) {
                    aiMessageEl.remove();
                    await executeDirectActions(directActions, "CoreAI auto-applied generated file actions.");
                } else {
                    addToSessionMemory("assistant", aiMessageEl.textContent);
                }
            }
        } catch (error) {
            aiMessageEl.textContent = `Error: ${error.message || "Unknown error"}`;
            addToSessionMemory("assistant", aiMessageEl.textContent);
        } finally {
            setChatBusy(false);
            updateSaveStatus();
            chatInput.focus();
        }
    }

    codeEditor.addEventListener("input", () => {
        state.dirty = true;
        updateSaveStatus();
    });

    saveBtn.addEventListener("click", () => {
        saveCurrentFile();
    });

    chatForm.addEventListener("submit", (event) => {
        event.preventDefault();
        if (state.isStreaming) {
            return;
        }

        const text = chatInput.value.trim();
        if (!text) {
            return;
        }
        const historyWindow = state.chatHistory.slice(-20);
        appendMessage("user", text);
        addToSessionMemory("user", text);
        chatInput.value = "";
        streamAIReply(text, historyWindow);
    });

    onboardingPromptButtons.forEach((btn) => {
        btn.addEventListener("click", () => {
            const prompt = String(btn.getAttribute("data-prompt") || "").trim();
            if (!prompt) {
                return;
            }
            chatInput.value = prompt;
            chatInput.focus();
            appendMessage("ai", "Prompt ready. Press Send.");
        });
    });

    onboardingDismiss?.addEventListener("click", () => {
        markOnboardingDone();
    });

    onboardingStartDemo?.addEventListener("click", () => {
        enableDemoMode();
    });

    /**
     * Initialize memory layers then render chat.
     * تهيئة طبقات الذاكرة ثم عرض المحادثة.
     */
    async function initializeMemory() {
        await loadPersistentMemory();
        restoreSessionLayer();
        rebuildCombinedHistory();
        renderCombinedHistory();
        setEmptyStateVisible(state.chatHistory.length === 0);

        if (state.chatHistory.length === 0) {
            setEmptyStateVisible(true);
        }
        setOnboardingVisible(isFirstTimeUser());
        if (isFirstTimeUser()) {
            applyGuidedHighlights();
        }
    }

    function updateAnalyticsView(analytics) {
        if (!analytics || typeof analytics !== "object") {
            return;
        }
        if (metricDecisionSuccess) {
            metricDecisionSuccess.textContent = `${Number(analytics.decision_success_rate || 0)}%`;
        }
        if (metricRiskTrend) {
            metricRiskTrend.textContent = String(analytics.risk_trend_label || "stable");
        }
        if (metricStability) {
            metricStability.textContent = `${Number(analytics.system_stability_score || 0)}%`;
        }
        if (metricLearning) {
            metricLearning.textContent = `${Number(analytics.learning_improvement_score || 0)}%`;
        }
    }

    async function loadAnalytics() {
        const response = await fetch("php/analytics.php", { method: "GET" });
        if (!response.ok) {
            throw new Error(`Analytics request failed: HTTP ${response.status}`);
        }
        const data = await response.json();
        updateAnalyticsView(data.analytics || {});
    }

    async function loadAccountState() {
        const response = await fetch("php/account.php?action=me", { method: "GET" });
        if (!response.ok) {
            throw new Error(`Account request failed: HTTP ${response.status}`);
        }
        await response.json();
        // Keep API key input visible so user can replace/update keys anytime.
        // إبقاء حقل المفتاح ظاهرًا دائمًا حتى يتمكن المستخدم من تحديث المفتاح في أي وقت.
        if (apiKeyInline) {
            apiKeyInline.classList.remove("hidden");
        }
    }

    async function saveApiKey() {
        const key = String(apiKeyInput?.value || "").trim();
        if (!key) {
            appendMessage("ai", "Please enter your AI API key first (Gemini/Groq/OpenRouter).");
            return;
        }
        const response = await fetch("php/account.php?action=api-key", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ api_key: key })
        });
        const data = await response.json();
        if (!response.ok || data.ok !== true) {
            throw new Error(data.error || `HTTP ${response.status}`);
        }
        if (apiKeyInput) {
            apiKeyInput.value = "";
        }
        appendMessage("ai", "API key saved. You can now use CoreAI.");
    }

    // Initial bootstrapping / تهيئة أولية للتطبيق
    renderFileNav();
    setActiveFile(files[0].id);
    renderSessionReminder();
    initializeMemory().catch((error) => {
        const fallback = `Startup failed: ${error.message || "Unknown error"}`;
        appendMessage("ai", fallback);
        addToSessionMemory("assistant", fallback);
    });

    loadAnalytics().catch((error) => {
        if (metricRiskTrend) {
            metricRiskTrend.textContent = "unavailable";
        }
        appendMessage("ai", `Analytics unavailable: ${error.message || "Unknown error"}`);
    });

    loadAccountState().catch((error) => {
        appendMessage("ai", `Account state unavailable: ${error.message || "Unknown error"}`);
    });

    if (refreshAnalyticsBtn) {
        refreshAnalyticsBtn.addEventListener("click", () => {
            loadAnalytics().catch((error) => {
                appendMessage("ai", `Analytics refresh failed: ${error.message || "Unknown error"}`);
            });
        });
    }

    saveApiKeyBtn?.addEventListener("click", () => {
        saveApiKey().catch((error) => {
            appendMessage("ai", `Failed to save API key: ${error.message || "Unknown error"}`);
        });
    });
})();
