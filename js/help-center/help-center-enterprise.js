/**
 * RATIB Help Center — Enterprise layer
 * AI copilot, command palette, smart search, onboarding intelligence, trust UI
 */
(function () {
    'use strict';

    const STORAGE_RECENT = 'hc_recent_searches';
    const STORAGE_PAGES = 'hc_recent_pages';
    const MAX_RECENT = 8;

    const HC_QUICK_ACTIONS = [
        { label: 'How do I onboard a worker?', query: 'onboard worker', icon: 'fa-user-plus' },
        { label: 'Generate a contract workflow', query: 'contract workflow recruitment', icon: 'fa-file-contract' },
        { label: 'Fix payroll sync issue', query: 'payroll sync troubleshooting', icon: 'fa-sync' },
        { label: 'Configure permissions', query: 'permissions roles system settings', icon: 'fa-lock' },
        { label: 'Review recruitment lifecycle', query: 'recruitment lifecycle workflow', icon: 'fa-sitemap' }
    ];

    const HC_TRENDING = [
        'worker onboarding',
        'partner agencies deployment',
        'accounting transactions',
        'user permissions',
        'reports export'
    ];

    const HC_ONBOARDING_STEPS = [
        { id: 'workspace', label: 'Workspace configuration', icon: 'fa-cog' },
        { id: 'agency', label: 'Agency setup', icon: 'fa-building' },
        { id: 'permissions', label: 'Permissions & roles', icon: 'fa-shield-alt' },
        { id: 'workforce', label: 'Workforce pipeline', icon: 'fa-users' },
        { id: 'operations', label: 'Operational workflows', icon: 'fa-project-diagram' }
    ];

    const HC_ENTERPRISE_MODULES = [
        {
            id: 'quick-start',
            title: 'Quick Start Hub',
            icon: 'fa-rocket',
            type: 'timeline',
            items: [
                { title: 'First-time onboarding', desc: 'Complete platform orientation', slug: 'getting-started', query: 'getting started' },
                { title: 'Guided workspace setup', desc: 'Configure your agency workspace', slug: 'getting-started', query: 'workspace setup' },
                { title: 'Permissions setup', desc: 'Roles, access, and security', slug: 'user-management', query: 'permissions' },
                { title: 'Agency configuration', desc: 'Partner offices and deployment', slug: 'partner-agencies', query: 'partner agencies' }
            ]
        },
        {
            id: 'playbooks',
            title: 'Operations Playbooks',
            icon: 'fa-book-open',
            type: 'workflow',
            items: [
                { title: 'Recruitment lifecycle', desc: 'Intake → contract → deployment', categoryMatch: 'contract', icon: 'fa-route' },
                { title: 'Workforce workflows', desc: 'Worker status and documents', categoryMatch: 'worker', icon: 'fa-hard-hat' },
                { title: 'Payroll operations', desc: 'HR, salaries, reconciliation', categoryMatch: 'finance', icon: 'fa-money-check-alt' },
                { title: 'Client management', desc: 'Agents, sub-agents, cases', categoryMatch: 'client', icon: 'fa-handshake' },
                { title: 'Compliance operations', desc: 'Records, audit, legal readiness', categoryMatch: 'compliance', icon: 'fa-balance-scale' }
            ]
        },
        {
            id: 'troubleshooting',
            title: 'Incident & Troubleshooting',
            icon: 'fa-life-ring',
            type: 'workflow',
            items: [
                { title: 'Known issues & fixes', desc: 'Common operational blockers', query: 'troubleshooting', icon: 'fa-wrench' },
                { title: 'Sync problems', desc: 'Data sync and integration', query: 'sync', icon: 'fa-sync-alt' },
                { title: 'Debugging workflows', desc: 'Step-by-step diagnostics', query: 'debug', icon: 'fa-bug' },
                { title: 'Escalation procedures', desc: 'When and how to escalate', query: 'contact support', icon: 'fa-level-up-alt' }
            ]
        },
        {
            id: 'ai-automation',
            title: 'AI Automation Guides',
            icon: 'fa-robot',
            type: 'workflow',
            items: [
                { title: 'Automation workflows', desc: 'Triggers and smart actions', query: 'automation notifications', icon: 'fa-bolt' },
                { title: 'AI operational assistants', desc: 'Copilot and chat integration', query: 'AI assistant', icon: 'fa-comments' },
                { title: 'Smart notifications', desc: 'Alerts and routing rules', categoryMatch: 'notification', icon: 'fa-bell' }
            ]
        },
        {
            id: 'status',
            title: 'System Status & Releases',
            icon: 'fa-server',
            type: 'compact',
            items: [
                { title: 'Release notes', desc: 'Latest platform updates', query: 'release notes', badge: 'v2026.05' },
                { title: 'Feature rollouts', desc: 'New capabilities', query: 'new features', badge: 'Active' },
                { title: 'Maintenance events', desc: 'Scheduled windows', query: 'maintenance', badge: 'None scheduled' },
                { title: 'Operational notices', desc: 'Production advisories', query: 'operational notice', badge: 'Clear' }
            ]
        }
    ];

    const HC_COPILOT_CHIPS = [
        'Summarize this article',
        'Generate onboarding checklist',
        'Explain permissions model',
        'Troubleshoot payroll sync'
    ];

    const STORAGE_COPILOT_MEMORY = 'hc_copilot_memory';
    const STORAGE_AI_USAGE = 'hc_ai_usage_count';

    const HC_COMMAND_ACTIONS = [
        { title: 'Open payroll workflow', meta: 'Command · Workflow', icon: 'fa-money-check-alt', query: 'payroll operations' },
        { title: 'Start onboarding', meta: 'Command · Quick start', icon: 'fa-rocket', query: 'getting started' },
        { title: 'Generate permissions guide', meta: 'Command · AI', icon: 'fa-lock', query: 'permissions roles' },
        { title: 'Open troubleshooting', meta: 'Command · Incident', icon: 'fa-life-ring', query: 'troubleshooting' },
        { title: 'Create setup checklist', meta: 'Command · Checklist', icon: 'fa-list-check', query: 'onboarding checklist' }
    ];

    const HC_ACTIVITY_FEED = [
        { type: 'update', icon: 'fa-pen', title: 'Payroll workflow updated', actor: 'Ops Team', time: '12m ago', status: 'ok' },
        { type: 'ai', icon: 'fa-magic', title: 'AI assistant generated workflow checklist', actor: 'Copilot', time: '28m ago', status: 'new' },
        { type: 'update', icon: 'fa-book', title: 'New onboarding tutorial published', actor: 'Knowledge', time: '1h ago', status: 'new' },
        { type: 'validate', icon: 'fa-circle-check', title: 'Recruitment lifecycle validated', actor: 'Audit', time: '2h ago', status: 'ok' },
        { type: 'update', icon: 'fa-shield-alt', title: 'Permissions article revised', actor: 'Security', time: '4h ago', status: 'ok' },
        { type: 'ai', icon: 'fa-robot', title: 'Smart search index refreshed', actor: 'System', time: '6h ago', status: 'ok' }
    ];

    const HC_TELEMETRY_DEFAULTS = [
        { id: 'health', label: 'Knowledge health', value: '98%', badge: 'Healthy', state: 'ok', trend: [70, 72, 75, 78, 82, 88, 94, 98] },
        { id: 'completed', label: 'Tutorials done', value: '0', badge: 'This week', state: 'neutral', trend: [2, 3, 3, 4, 5, 6, 7, 8] },
        { id: 'workflows', label: 'Active workflows', value: '24', badge: 'Live', state: 'ok', trend: [18, 19, 20, 21, 22, 23, 24, 24] },
        { id: 'ai', label: 'AI assistance', value: '0', badge: 'Sessions', state: 'ok', trend: [4, 6, 8, 12, 15, 18, 22, 28] },
        { id: 'incidents', label: 'Recent incidents', value: '0', badge: 'Open', state: 'warn', trend: [3, 2, 2, 1, 1, 0, 0, 0] },
        { id: 'audit', label: 'Last audit sync', value: '2h', badge: 'Verified', state: 'ok', trend: [24, 20, 16, 12, 8, 6, 4, 2] },
        { id: 'readiness', label: 'Platform readiness', value: '100%', badge: 'Production', state: 'ok', trend: [95, 96, 97, 98, 99, 99, 100, 100] },
        { id: 'coverage', label: 'Doc coverage', value: '94%', badge: 'Modules', state: 'ok', trend: [80, 82, 85, 87, 89, 91, 93, 94] }
    ];

    const HC_CONTEXT = {
        view: 'homeHubView',
        categoryName: null,
        tutorialTitle: null,
        categoryId: null
    };

    function t(key) {
        if (typeof getTranslation === 'function' && typeof HelpCenterState !== 'undefined') {
            return getTranslation(key, HelpCenterState.currentLanguage);
        }
        return key;
    }

    function getRecentSearches() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_RECENT) || '[]');
        } catch (e) {
            return [];
        }
    }

    function saveRecentSearch(q) {
        if (!q || q.length < 2) return;
        let list = getRecentSearches().filter(function (x) { return x !== q; });
        list.unshift(q);
        list = list.slice(0, MAX_RECENT);
        localStorage.setItem(STORAGE_RECENT, JSON.stringify(list));
    }

    function getRecentPages() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_PAGES) || '[]');
        } catch (e) {
            return [];
        }
    }

    function saveRecentPage(title, tutorialId) {
        if (!title) return;
        let list = getRecentPages().filter(function (x) { return x.id !== tutorialId; });
        list.unshift({ title: title, id: tutorialId, at: Date.now() });
        list = list.slice(0, MAX_RECENT);
        localStorage.setItem(STORAGE_PAGES, JSON.stringify(list));
        renderRecentPages();
    }

    function fuzzyScore(text, query) {
        if (!text || !query) return 0;
        const t = text.toLowerCase();
        const q = query.toLowerCase();
        if (t.indexOf(q) >= 0) return 100 - t.indexOf(q);
        let score = 0;
        let qi = 0;
        for (let i = 0; i < t.length && qi < q.length; i++) {
            if (t[i] === q[qi]) {
                score += 2;
                qi++;
            }
        }
        return qi === q.length ? score : 0;
    }

    function collectAllTutorials() {
        const out = [];
        const H = window.HELP_CENTER_BUILTIN || {};
        Object.keys(H).forEach(function (cid) {
            (H[cid] || []).forEach(function (tut) {
                out.push(Object.assign({}, tut, { categoryId: cid, source: 'builtin' }));
            });
        });
        if (typeof HelpCenterState !== 'undefined' && HelpCenterState.tutorials) {
            HelpCenterState.tutorials.forEach(function (tut) {
                if (!out.some(function (x) { return x.id === tut.id; })) {
                    out.push(Object.assign({}, tut, { source: 'api' }));
                }
            });
        }
        return out;
    }

    function findCategoryByMatch(match) {
        if (typeof HelpCenterState === 'undefined' || !HelpCenterState.categories) return null;
        const flat = flattenCats(HelpCenterState.categories);
        const m = (match || '').toLowerCase();
        return flat.find(function (c) {
            const name = (c.name || '').toLowerCase();
            const slug = (c.slug || '').toLowerCase();
            return name.indexOf(m) >= 0 || slug.indexOf(m) >= 0;
        });
    }

    function flattenCats(cats) {
        let flat = [];
        (cats || []).forEach(function (c) {
            flat.push(c);
            if (c.children && c.children.length) flat = flat.concat(flattenCats(c.children));
        });
        return flat;
    }

    function smartSearch(query, filter) {
        filter = filter || 'all';
        const results = [];
        const q = (query || '').trim();
        if (q.length < 1) return results;

        if (filter === 'all' || filter === 'tutorials') {
            collectAllTutorials().forEach(function (tut) {
                const score = Math.max(
                    fuzzyScore(tut.title, q),
                    fuzzyScore(tut.overview, q),
                    fuzzyScore((tut.content || '').replace(/<[^>]+>/g, ' '), q)
                );
                if (score > 0) {
                    results.push({
                        type: 'tutorial',
                        title: tut.title,
                        meta: (tut.estimated_time || 5) + ' min · ' + (tut.difficulty_level || 'beginner'),
                        score: score,
                        action: function () {
                            HelpCenterController.loadTutorial(tut.id);
                        }
                    });
                }
            });
        }

        if (filter === 'all' || filter === 'workflows') {
            HC_ENTERPRISE_MODULES.forEach(function (mod) {
                mod.items.forEach(function (item) {
                    const score = fuzzyScore(item.title + ' ' + (item.desc || ''), q);
                    if (score > 0) {
                        results.push({
                            type: 'workflow',
                            title: item.title,
                            meta: mod.title,
                            score: score,
                            action: function () { navigateModuleItem(item); }
                        });
                    }
                });
            });
        }

        if (filter === 'all' || filter === 'actions') {
            HC_COMMAND_ACTIONS.forEach(function (cmd) {
                const score = fuzzyScore(cmd.title + ' ' + cmd.query, q);
                if (score > 0) {
                    results.push({
                        type: 'action',
                        title: cmd.title,
                        meta: cmd.meta,
                        score: score + 5,
                        action: function () { runSearch(cmd.query); bumpAiUsage(); }
                    });
                }
            });
            HC_QUICK_ACTIONS.forEach(function (a) {
                const score = fuzzyScore(a.label + ' ' + a.query, q);
                if (score > 0) {
                    results.push({
                        type: 'action',
                        title: a.label,
                        meta: 'Quick action',
                        score: score,
                        action: function () { runSearch(a.query); }
                    });
                }
            });
        }

        return results.sort(function (a, b) { return b.score - a.score; }).slice(0, 12);
    }

    function navigateModuleItem(item) {
        if (item.query) {
            runSearch(item.query);
            return;
        }
        const cat = findCategoryByMatch(item.categoryMatch || item.slug);
        if (cat && typeof HelpCenterController !== 'undefined') {
            HelpCenterController.loadTutorialsByCategory(cat.id);
        }
    }

    function runSearch(query) {
        if (!query) return;
        saveRecentSearch(query);
        closeCmdPalette();
        const input = document.getElementById('helpSearchInput');
        if (input) input.value = query;
        if (typeof HelpCenterController !== 'undefined') {
            HelpCenterController.searchTutorials(query);
        }
    }

    function renderQuickActions() {
        const row = document.getElementById('hcQuickActionChips');
        if (!row) return;
        row.innerHTML = '';
        HC_QUICK_ACTIONS.forEach(function (a) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'hc-chip';
            btn.textContent = a.label;
            btn.addEventListener('click', function () {
                runSearch(a.query);
                appendCopilotUser(a.label);
                streamCopilotResponse(buildCopilotAnswer(a.query), true, a.label);
            });
            row.appendChild(btn);
        });
    }

    function renderOnboardingSteps(completedPct) {
        const wrap = document.getElementById('hcOnboardingSteps');
        if (!wrap) return;
        const pct = completedPct || 0;
        const activeIdx = Math.min(HC_ONBOARDING_STEPS.length - 1, Math.floor(pct / (100 / HC_ONBOARDING_STEPS.length)));
        wrap.innerHTML = HC_ONBOARDING_STEPS.map(function (step, i) {
            let cls = 'hc-onboarding-step';
            if (i < activeIdx) cls += ' hc-onboarding-step--done';
            else if (i === activeIdx) cls += ' hc-onboarding-step--active';
            const icon = i < activeIdx ? 'fa-check-circle' : step.icon;
            return '<div class="' + cls + '"><i class="fas ' + icon + '"></i><span>' + step.label + '</span></div>';
        }).join('');
    }

    function renderEnterpriseModules() {
        const root = document.getElementById('hcEnterpriseModules');
        if (!root) return;
        root.innerHTML = '';

        HC_ENTERPRISE_MODULES.forEach(function (mod) {
            const section = document.createElement('section');
            section.className = 'hc-module-section';
            section.id = 'hc-module-' + mod.id;
            section.dataset.module = mod.id;

            let body = '';
            if (mod.type === 'timeline') {
                body = '<div class="hc-timeline">' + mod.items.map(function (item, i) {
                    return '<div class="hc-timeline-item" data-idx="' + i + '"><strong>' + item.title + '</strong><br><span style="color:var(--hc-text-muted);font-size:0.82rem">' + item.desc + '</span></div>';
                }).join('') + '</div>';
            } else if (mod.type === 'workflow') {
                body = '<div class="hc-workflow-row">' + mod.items.map(function (item) {
                    const icon = item.icon || 'fa-arrow-right';
                    return '<a href="#" class="hc-workflow-card" data-module-item="' + mod.id + '"><div class="hc-workflow-icon"><i class="fas ' + icon + '"></i></div><div class="hc-workflow-body"><h3>' + item.title + '</h3><p>' + item.desc + '</p></div></a>';
                }).join('') + '</div>';
            } else {
                body = '<div class="hc-workflow-row">' + mod.items.map(function (item) {
                    return '<a href="#" class="hc-workflow-card hc-workflow-card--compact"><div class="hc-workflow-body"><h3>' + item.title + ' <span class="hc-meta-badge">' + (item.badge || '') + '</span></h3><p>' + item.desc + '</p></div></a>';
                }).join('') + '</div>';
            }

            section.innerHTML =
                '<div class="hc-module-header"><h2><i class="fas ' + mod.icon + '"></i> ' + mod.title + '</h2></div>' + body;

            section.querySelectorAll('.hc-timeline-item, .hc-workflow-card').forEach(function (el, idx) {
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                    navigateModuleItem(mod.items[idx]);
                });
            });

            root.appendChild(section);
        });
    }

    function renderRecentPages() {
        const list = document.getElementById('hcRecentList');
        if (!list) return;
        const pages = getRecentPages();
        list.innerHTML = pages.length ? pages.map(function (p) {
            return '<li><a href="#" data-tutorial-id="' + (p.id || '') + '">' + p.title + '</a></li>';
        }).join('') : '<li style="font-size:0.8rem;color:var(--hc-text-muted)">No recent pages</li>';

        list.querySelectorAll('[data-tutorial-id]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                const id = a.getAttribute('data-tutorial-id');
                if (id && typeof HelpCenterController !== 'undefined') {
                    HelpCenterController.loadTutorial(id);
                }
            });
        });
    }

    async function loadProgressUI() {
        try {
            if (typeof HelpCenterAPIHandler === 'undefined') return;
            const res = await HelpCenterAPIHandler.getProgress();
            let completed = 0;
            let inProgress = 0;
            let lastTutorial = null;
            if (res.success && res.data) {
                completed = res.data.completed_count || 0;
                inProgress = res.data.in_progress_count || 0;
                if (res.data.recent && res.data.recent.length) {
                    lastTutorial = res.data.recent[0];
                }
            }
            const total = Math.max(completed + inProgress, 1);
            const pct = Math.round((completed / total) * 100);

            const cc = document.getElementById('completedCount');
            const ic = document.getElementById('inProgressCount');
            const ring = document.getElementById('hcProgressRingFill');
            const pctEl = document.getElementById('hcProgressPercent');
            if (cc) cc.textContent = String(completed);
            if (ic) ic.textContent = String(inProgress);
            if (ring) ring.setAttribute('stroke-dasharray', pct + ', 100');
            if (pctEl) pctEl.textContent = pct + '%';

            renderOnboardingSteps(pct);
            renderContinueCard(lastTutorial, pct);
            renderRecentTutorialList(res.data && res.data.recent ? res.data.recent : []);
            if (window.HelpCenterEnterprise && typeof window.HelpCenterEnterprise.onProgressLoaded === 'function') {
                window.HelpCenterEnterprise.onProgressLoaded(res.data || {});
            }
        } catch (e) {
            renderOnboardingSteps(0);
            const card = document.getElementById('hcContinueCard');
            if (card) {
                card.innerHTML = '<h4>Getting Started – Complete Program Overview</h4><p>Begin your onboarding journey</p><div class="hc-continue-progress"><div class="hc-continue-progress-fill" style="width:0%"></div></div>';
                card.onclick = function () {
                    if (typeof HelpCenterController !== 'undefined') {
                        HelpCenterController.loadTutorial('builtin-1-0');
                    }
                };
            }
        }
    }

    function renderContinueCard(last, pct) {
        const card = document.getElementById('hcContinueCard');
        if (!card) return;
        const title = last && last.title ? last.title : 'Getting Started – Complete Program Overview';
        const id = last && last.tutorial_id ? last.tutorial_id : 'builtin-1-0';
        const prog = last && last.progress_percentage ? last.progress_percentage : 0;
        card.classList.remove('hc-shimmer');
        card.innerHTML = '<h4>' + title + '</h4><p>' + prog + '% complete · Pick up where you left off</p><div class="hc-continue-progress"><div class="hc-continue-progress-fill" style="width:' + Math.max(prog, pct) + '%"></div></div>';
        card.onclick = function () {
            if (typeof HelpCenterController !== 'undefined') {
                HelpCenterController.loadTutorial(id);
            }
        };
    }

    function renderRecentTutorialList(recent) {
        const list = document.getElementById('hcRecentTutorialList');
        if (!list) return;
        if (!recent || !recent.length) {
            list.innerHTML = '<li><a href="#" data-tutorial-id="builtin-1-0">Getting Started Overview</a></li>';
        } else {
            list.innerHTML = recent.slice(0, 5).map(function (r) {
                return '<li><a href="#" data-tutorial-id="' + (r.tutorial_id || r.id || '') + '">' + (r.title || 'Tutorial') + '</a></li>';
            }).join('');
        }
        list.querySelectorAll('[data-tutorial-id]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                HelpCenterController.loadTutorial(a.getAttribute('data-tutorial-id'));
            });
        });
    }

    /* Command palette */
    let cmdFilter = 'all';
    let cmdActiveIndex = 0;
    let cmdLiveResults = [];

    function openCmdPalette() {
        const pal = document.getElementById('hcCmdPalette');
        if (!pal) return;
        pal.classList.remove('help-hidden');
        const input = document.getElementById('hcCmdInput');
        if (input) {
            input.value = '';
            setTimeout(function () { input.focus(); }, 50);
        }
        renderCmdCommands();
        renderCmdTrending();
        renderCmdRecent();
        renderCmdLive('');
    }

    function closeCmdPalette() {
        const pal = document.getElementById('hcCmdPalette');
        if (pal) pal.classList.add('help-hidden');
    }

    function renderCmdTrending() {
        const list = document.getElementById('hcCmdTrendingList');
        if (!list) return;
        list.innerHTML = HC_TRENDING.map(function (q, i) {
            return cmdItemHtml({ title: q, meta: 'Trending', icon: 'fa-fire' }, i);
        }).join('');
        bindCmdItems(list, HC_TRENDING.map(function (q) {
            return { action: function () { runSearch(q); } };
        }));
    }

    function renderCmdRecent() {
        const list = document.getElementById('hcCmdRecentList');
        const recent = getRecentSearches();
        const group = document.getElementById('hcCmdRecent');
        if (!list || !group) return;
        if (!recent.length) {
            group.style.display = 'none';
            return;
        }
        group.style.display = '';
        list.innerHTML = recent.map(function (q, i) {
            return cmdItemHtml({ title: q, meta: 'Recent', icon: 'fa-history' }, i);
        }).join('');
        bindCmdItems(list, recent.map(function (q) {
            return { action: function () { runSearch(q); } };
        }));
    }

    function renderCmdLive(query) {
        const list = document.getElementById('hcCmdLiveList');
        const group = document.getElementById('hcCmdLive');
        if (!list) return;
        cmdLiveResults = smartSearch(query, cmdFilter);
        cmdActiveIndex = 0;
        if (!query) {
            group.style.display = 'none';
            return;
        }
        group.style.display = '';
        list.innerHTML = cmdLiveResults.length
            ? cmdLiveResults.map(function (r, i) {
                return cmdItemHtml({ title: r.title, meta: r.meta, icon: r.type === 'tutorial' ? 'fa-book' : 'fa-bolt' }, i);
            }).join('')
            : '<li class="hc-cmd-item"><span class="hc-cmd-item-meta">No results</span></li>';
        bindCmdItems(list, cmdLiveResults);
    }

    function cmdItemHtml(item, idx) {
        return '<li class="hc-cmd-item' + (idx === cmdActiveIndex ? ' active' : '') + '" data-cmd-idx="' + idx + '">' +
            '<span class="hc-cmd-item-icon"><i class="fas ' + (item.icon || 'fa-search') + '"></i></span>' +
            '<span class="hc-cmd-item-body"><span class="hc-cmd-item-title">' + item.title + '</span><br><span class="hc-cmd-item-meta">' + item.meta + '</span></span></li>';
    }

    function bindCmdItems(list, items) {
        list.querySelectorAll('.hc-cmd-item[data-cmd-idx]').forEach(function (el) {
            el.addEventListener('click', function () {
                const idx = parseInt(el.getAttribute('data-cmd-idx'), 10);
                if (items[idx] && items[idx].action) items[idx].action();
            });
        });
    }

    function setupCmdPalette() {
        const trigger = document.getElementById('hcCmdTrigger');
        const backdrop = document.getElementById('hcCmdBackdrop');
        const input = document.getElementById('hcCmdInput');
        if (trigger) trigger.addEventListener('click', openCmdPalette);
        if (backdrop) backdrop.addEventListener('click', closeCmdPalette);

        document.querySelectorAll('.hc-cmd-filter').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.hc-cmd-filter').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                cmdFilter = btn.getAttribute('data-filter') || 'all';
                if (input) renderCmdLive(input.value);
            });
        });

        if (input) {
            let debounce;
            input.addEventListener('input', function () {
                clearTimeout(debounce);
                debounce = setTimeout(function () { renderCmdLive(input.value.trim()); }, 120);
            });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeCmdPalette();
                if (e.key === 'Enter' && cmdLiveResults[cmdActiveIndex]) {
                    cmdLiveResults[cmdActiveIndex].action();
                }
            });
        }

        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                openCmdPalette();
            }
        });
    }

    /* AI Copilot */
    function buildCopilotAnswer(query) {
        const q = (query || '').toLowerCase();
        let ctxPrefix = '';
        if (HC_CONTEXT.tutorialTitle) {
            ctxPrefix = 'While you view **' + HC_CONTEXT.tutorialTitle + '**: ';
        } else if (HC_CONTEXT.categoryName) {
            ctxPrefix = 'In **' + HC_CONTEXT.categoryName + '**: ';
        }
        const tutorials = collectAllTutorials();
        let best = null;
        let bestScore = 0;
        tutorials.forEach(function (tut) {
            const s = fuzzyScore(tut.title + ' ' + tut.overview, query);
            if (s > bestScore) {
                bestScore = s;
                best = tut;
            }
        });

        if (best && bestScore > 4) {
            const plain = (best.overview || '').substring(0, 280);
            return ctxPrefix + 'Based on **' + best.title + '**: ' + plain + '…\n\n[Open full guide](' + best.id + ')';
        }
        if (q.indexOf('checklist') >= 0) {
            return ctxPrefix + '**Onboarding checklist:**\n1. Configure workspace & agency profile\n2. Set roles and permissions\n3. Add agents and partner agencies\n4. Register first worker with documents\n5. Run a test accounting transaction\n6. Review reports and notifications';
        }
        if (q.indexOf('payroll') >= 0 || q.indexOf('sync') >= 0) {
            return ctxPrefix + '**Payroll sync troubleshooting:** Verify HR employee records exist, check fiscal period is open, confirm transaction dates align, refresh the page, and review permission for Accounting + HR modules.';
        }
        if (q.indexOf('permission') >= 0) {
            return ctxPrefix + '**Permissions model:** Go to System Settings → assign roles with least-privilege access. Each menu item maps to a permission flag. Users only see modules their role allows.';
        }
        if (q.indexOf('summarize') >= 0 && HC_CONTEXT.tutorialTitle) {
            return ctxPrefix + 'This guide covers operational steps for **' + HC_CONTEXT.tutorialTitle + '**. Use checklist mode in the article toolbar to track execution.';
        }
        return ctxPrefix + 'I searched the knowledge base for "' + query + '". Try the command palette (Ctrl+K) for grouped results, or browse Operations Playbooks for workflow guides.';
    }

    function appendCopilotUser(text) {
        const box = document.getElementById('hcCopilotMessages');
        if (!box) return;
        const div = document.createElement('div');
        div.className = 'hc-copilot-msg hc-copilot-msg--user';
        div.innerHTML = '<div class="hc-copilot-avatar"><i class="fas fa-user"></i></div><div class="hc-copilot-bubble"><p>' + text + '</p></div>';
        box.appendChild(div);
        box.scrollTop = box.scrollHeight;
    }

    function streamCopilotResponse(text, persistMemory, lastUserText) {
        bumpAiUsage();
        const box = document.getElementById('hcCopilotMessages');
        if (!box) return;
        const div = document.createElement('div');
        div.className = 'hc-copilot-msg hc-copilot-msg--assistant hc-copilot-typing';
        const bubble = document.createElement('div');
        bubble.className = 'hc-copilot-bubble hc-copilot-streaming';
        bubble.innerHTML = '<p></p>';
        div.innerHTML = '<div class="hc-copilot-avatar"><i class="fas fa-robot"></i></div>';
        div.appendChild(bubble);
        box.appendChild(div);

        const conf = document.getElementById('hcCopilotConfidence');
        if (conf) conf.textContent = (88 + Math.floor(Math.random() * 8)) + '%';

        const p = bubble.querySelector('p');
        let i = 0;
        const plain = text.replace(/\*\*(.*?)\*\*/g, '$1').replace(/\[([^\]]+)\]\([^)]+\)/g, '$1');
        const interval = setInterval(function () {
            i += 4;
            p.textContent = plain.substring(0, i);
            box.scrollTop = box.scrollHeight;
            if (i >= plain.length) {
                clearInterval(interval);
                div.classList.remove('hc-copilot-typing');
                bubble.classList.remove('hc-copilot-streaming');
                let html = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="#" data-copilot-link="$2">$1</a>');
                const linkMatch = text.match(/\[([^\]]+)\]\(([^)]+)\)/);
                if (linkMatch) {
                    html += '<motion class="hc-copilot-inline-card"><a href="#" data-copilot-link="' + linkMatch[2] + '">Open guide: ' + linkMatch[1] + '</a></motion>';
                    html = html.replace(/<\/?motion\b/g, function (m) { return m.replace('motion', 'div'); });
                }
                p.innerHTML = html;
                p.querySelectorAll('[data-copilot-link]').forEach(function (a) {
                    a.addEventListener('click', function (e) {
                        e.preventDefault();
                        HelpCenterController.loadTutorial(a.getAttribute('data-copilot-link'));
                    });
                });
                if (persistMemory && lastUserText) {
                    saveCopilotMemory(lastUserText, plain.substring(0, 240));
                }
            }
        }, 16);
    }

    function setupCopilot() {
        const root = document.getElementById('hcCopilot');
        const toggle = document.getElementById('hcCopilotToggle');
        const minBtn = document.getElementById('hcCopilotMinimize');
        const expandBtn = document.getElementById('hcCopilotExpand');
        const dockBtn = document.getElementById('hcCopilotDock');
        const sendBtn = document.getElementById('hcCopilotSend');
        const input = document.getElementById('hcCopilotInput');
        const chips = document.getElementById('hcCopilotChips');

        if (chips) {
            HC_COPILOT_CHIPS.forEach(function (c) {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'hc-chip';
                b.textContent = c;
                b.addEventListener('click', function () {
                    appendCopilotUser(c);
                    streamCopilotResponse(buildCopilotAnswer(c), true, c);
                });
                chips.appendChild(b);
            });
        }

        function setMode(mode) {
            if (!root) return;
            root.className = 'hc-copilot hc-copilot--' + mode;
        }

        if (toggle) toggle.addEventListener('click', function () {
            setMode(root.classList.contains('hc-copilot--minimized') ? 'expanded' : 'minimized');
        });
        if (minBtn) minBtn.addEventListener('click', function () { setMode('minimized'); });
        if (expandBtn) expandBtn.addEventListener('click', function () {
            setMode(root.classList.contains('hc-copilot--fullscreen') ? 'expanded' : 'fullscreen');
        });
        if (dockBtn) dockBtn.addEventListener('click', function () { setMode('docked'); });

        function sendCopilot() {
            const val = (input && input.value || '').trim();
            if (!val) return;
            appendCopilotUser(val);
            input.value = '';
            streamCopilotResponse(buildCopilotAnswer(val), true, val);
        }
        if (sendBtn) sendBtn.addEventListener('click', sendCopilot);
        if (input) input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendCopilot();
            }
        });
    }

    function setupSidebar() {
        document.querySelectorAll('.hc-sidebar-section-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const expanded = btn.getAttribute('aria-expanded') === 'true';
                btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            });
        });

        document.querySelectorAll('.hc-shortcut-link').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                const action = a.getAttribute('data-hc-action');
                if (action === 'home' && typeof HelpCenterController !== 'undefined') {
                    HelpCenterController.loadCategories();
                } else {
                    const el = document.getElementById('hc-module-' + action);
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        const mob = document.getElementById('hcSidebarMobileToggle');
        const sidebar = document.getElementById('helpSidebar');
        if (mob && sidebar) {
            mob.addEventListener('click', function () { sidebar.classList.add('active'); });
        }
    }

    function setupHeroSearch() {
        const submit = document.getElementById('hcAiSubmit');
        const input = document.getElementById('helpSearchInput');
        const voice = document.getElementById('hcVoiceBtn');

        if (submit && input) {
            submit.addEventListener('click', function () {
                const q = input.value.trim();
                runSearch(q);
                appendCopilotUser(q);
                streamCopilotResponse(buildCopilotAnswer(q), true, q);
            });
        }

        if (voice && input) {
            voice.addEventListener('click', function () {
                const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
                if (!SR) {
                    if (typeof showErrorMessage === 'function') showErrorMessage('Voice search is not supported in this browser.');
                    return;
                }
                const rec = new SR();
                rec.lang = 'en-US';
                voice.classList.add('hc-voice-active');
                rec.onresult = function (ev) {
                    input.value = ev.results[0][0].transcript;
                    runSearch(input.value.trim());
                    voice.classList.remove('hc-voice-active');
                };
                rec.onerror = function () { voice.classList.remove('hc-voice-active'); };
                rec.onend = function () { voice.classList.remove('hc-voice-active'); };
                rec.start();
            });
        }
    }

    function buildArticleToc() {
        const body = document.querySelector('.tutorial-detail-body');
        const nav = document.getElementById('hcTocNav');
        const tocPanel = document.getElementById('hcArticleToc');
        const summaryPanel = document.getElementById('hcAiSummaryPanel');
        const footer = document.getElementById('hcArticleFooter');
        if (!body || !nav) return;

        nav.innerHTML = '';
        const headings = body.querySelectorAll('h2, h3');
        if (!headings.length) {
            if (tocPanel) tocPanel.style.display = 'none';
        } else {
            if (tocPanel) tocPanel.style.display = '';
            headings.forEach(function (h, i) {
                if (!h.id) h.id = 'hc-h-' + i;
                const a = document.createElement('a');
                a.href = '#' + h.id;
                a.textContent = h.textContent;
                a.style.paddingLeft = (h.tagName === 'H3' ? '20px' : '12px');
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    h.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
                nav.appendChild(a);
            });
        }

        if (summaryPanel) summaryPanel.classList.remove('help-hidden');
        if (footer) footer.classList.remove('help-hidden');

        const gen = document.getElementById('hcGenerateSummary');
        if (gen) {
            gen.onclick = function () {
                const bodyEl = document.getElementById('hcAiSummaryBody');
                const title = document.querySelector('.tutorial-detail-title');
                if (bodyEl) {
                    bodyEl.textContent = 'Generating summary…';
                    setTimeout(function () {
                        const firstP = body.querySelector('p');
                        bodyEl.textContent = (title ? title.textContent + ': ' : '') + (firstP ? firstP.textContent.substring(0, 320) : 'Summary unavailable.') + '…';
                    }, 600);
                }
            };
        }

        setupReadingProgress();
    }

    function setupReadingProgress() {
        const fill = document.getElementById('hcReadingFill');
        const article = document.querySelector('.hc-article-main');
        if (!fill || !article) return;

        function onScroll() {
            const rect = article.getBoundingClientRect();
            const total = article.scrollHeight - window.innerHeight;
            const scrolled = Math.min(Math.max(-rect.top, 0), total);
            const pct = total > 0 ? (scrolled / total) * 100 : 0;
            fill.style.width = pct + '%';
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    function updateCopilotContext(text) {
        const el = document.getElementById('hcCopilotContextText');
        const viewing = HC_CONTEXT.tutorialTitle || HC_CONTEXT.categoryName || text || 'Knowledge Hub';
        if (el) {
            el.textContent = HC_CONTEXT.tutorialTitle
                ? 'Viewing: ' + HC_CONTEXT.tutorialTitle
                : (HC_CONTEXT.categoryName ? 'Category: ' + HC_CONTEXT.categoryName : (text || 'Knowledge Hub'));
        }
        refreshCopilotDynamicChips();
    }

    function bumpAiUsage() {
        try {
            const n = parseInt(localStorage.getItem(STORAGE_AI_USAGE) || '0', 10) + 1;
            localStorage.setItem(STORAGE_AI_USAGE, String(n));
            const aiCard = document.querySelector('[data-telemetry="ai"] .hc-telemetry-value');
            if (aiCard) aiCard.textContent = String(n);
        } catch (e) { /* ignore */ }
    }

    function saveCopilotMemory(user, assistant) {
        try {
            let mem = JSON.parse(localStorage.getItem(STORAGE_COPILOT_MEMORY) || '[]');
            mem.push({ user: user, assistant: assistant, at: Date.now() });
            mem = mem.slice(-6);
            localStorage.setItem(STORAGE_COPILOT_MEMORY, JSON.stringify(mem));
            const hint = document.getElementById('hcCopilotMemoryHint');
            if (hint) hint.textContent = 'Memory: ' + mem.length + ' recent exchanges in this session';
        } catch (e) { /* ignore */ }
    }

    function sparklineSvg(values) {
        const w = 120;
        const h = 28;
        const max = Math.max.apply(null, values);
        const min = Math.min.apply(null, values);
        const range = max - min || 1;
        const pts = values.map(function (v, i) {
            const x = (i / (values.length - 1)) * w;
            const y = h - ((v - min) / range) * (h - 4) - 2;
            return x + ',' + y;
        });
        return '<svg class="hc-sparkline" viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="none">' +
            '<path class="fill" d="M0,' + h + ' L' + pts.join(' L') + ' L' + w + ',' + h + ' Z"/>' +
            '<path d="M' + pts.join(' L') + '"/></svg>';
    }

    function renderTelemetryStrip(stats) {
        const wrap = document.getElementById('hcTelemetryScroll');
        if (!wrap) return;
        stats = stats || {};
        const completed = stats.completed != null ? stats.completed : 0;
        const aiCount = parseInt(localStorage.getItem(STORAGE_AI_USAGE) || '12', 10);
        const incidents = stats.incidents != null ? stats.incidents : 0;

        wrap.innerHTML = HC_TELEMETRY_DEFAULTS.map(function (card) {
            let val = card.value;
            if (card.id === 'completed') val = String(completed);
            if (card.id === 'ai') val = String(aiCount);
            if (card.id === 'incidents') val = String(incidents);
            const badgeCls = card.state === 'warn' ? 'hc-telemetry-badge--warn' : (card.state === 'neutral' ? 'hc-telemetry-badge--neutral' : '');
            return '<article class="hc-telemetry-card" data-telemetry="' + card.id + '">' +
                '<div class="hc-telemetry-label">' + card.label + '</div>' +
                '<div class="hc-telemetry-value">' + val + '</div>' +
                '<span class="hc-telemetry-badge ' + badgeCls + '">' + card.badge + '</span>' +
                sparklineSvg(card.trend) +
                '</article>';
        }).join('');
    }

    function renderActivityFeed() {
        const feed = document.getElementById('hcActivityFeed');
        if (!feed) return;
        feed.innerHTML = HC_ACTIVITY_FEED.map(function (item, i) {
            const iconCls = 'hc-activity-icon--' + (item.type === 'ai' ? 'ai' : (item.type === 'validate' ? 'validate' : 'update'));
            return '<li class="hc-activity-item" style="animation-delay:' + (i * 0.05) + 's">' +
                '<span class="hc-activity-icon ' + iconCls + '"><i class="fas ' + item.icon + '"></i></span>' +
                '<div class="hc-activity-body">' +
                '<p class="hc-activity-title">' + item.title + '</p>' +
                '<div class="hc-activity-meta">' +
                '<span class="hc-activity-actor">' + item.actor + '</span>' +
                '<span>' + item.time + '</span>' +
                '<span class="hc-activity-status hc-activity-status--' + item.status + '">' + (item.status === 'new' ? 'New' : 'Verified') + '</span>' +
                '</div></div></li>';
        }).join('');
    }

    function renderQuickPanels() {
        const root = document.getElementById('hcQuickPanels');
        if (!root) return;
        const recent = getRecentPages();
        const searches = getRecentSearches().slice(0, 4);
        const pending = [
            'Complete workspace setup',
            'Assign role permissions',
            'Link first partner agency'
        ];
        root.innerHTML =
            '<div class="hc-widget"><h4>Recently viewed</h4><ul class="hc-widget-list">' +
            (recent.length ? recent.map(function (p) {
                return '<li><a href="#" data-tutorial-id="' + (p.id || '') + '">' + p.title + '</a></li>';
            }).join('') : '<li>No history yet</li>') +
            '</ul></div>' +
            '<div class="hc-widget"><h4>Trending searches</h4><ul class="hc-widget-list">' +
            HC_TRENDING.slice(0, 4).map(function (q) {
                return '<li><a href="#" data-hc-search="' + q.replace(/"/g, '') + '">' + q + '</a></li>';
            }).join('') +
            '</ul></div>' +
            '<div class="hc-widget"><h4>AI suggested actions</h4><ul class="hc-widget-list">' +
            HC_QUICK_ACTIONS.slice(0, 3).map(function (a) {
                return '<li><a href="#" data-hc-search="' + a.query + '">' + a.label + '</a></li>';
            }).join('') +
            '</ul></div>' +
            '<div class="hc-widget"><h4>Pending setup</h4>' +
            pending.map(function (task) {
                return '<label class="hc-widget-task"><input type="checkbox"> ' + task + '</label>';
            }).join('') +
            '</div>' +
            '<div class="hc-widget"><h4>Recommended workflows</h4><ul class="hc-widget-list">' +
            '<li><a href="#" data-hc-module="playbooks">Recruitment lifecycle</a></li>' +
            '<li><a href="#" data-hc-module="playbooks">Workforce pipeline</a></li>' +
            '<li><a href="#" data-hc-search="payroll">Payroll reconciliation</a></li>' +
            '</ul></div>';

        root.querySelectorAll('[data-tutorial-id]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                HelpCenterController.loadTutorial(a.getAttribute('data-tutorial-id'));
            });
        });
        root.querySelectorAll('[data-hc-search]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                runSearch(a.getAttribute('data-hc-search'));
            });
        });
        root.querySelectorAll('[data-hc-module]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                const el = document.getElementById('hc-module-' + a.getAttribute('data-hc-module'));
                if (el) el.scrollIntoView({ behavior: 'smooth' });
            });
        });
    }

    function renderFeaturedBanner() {
        const el = document.getElementById('hcFeaturedBanner');
        if (!el) return;
        el.innerHTML =
            '<div><h3>Getting Started — Operational Critical</h3>' +
            '<p>Complete program orientation · ~15 min · Production-safe</p>' +
            '<div class="hc-featured-tags">' +
            '<span class="hc-tag hc-tag--critical">Operational Critical</span>' +
            '<span class="hc-tag hc-tag--hot">Most Used</span>' +
            '<span class="hc-tag hc-tag--ai">AI Assisted</span>' +
            '</div></div>' +
            '<span class="hc-meta-badge hc-meta-badge--verified"><i class="fas fa-circle-check"></i> Audit verified</span>';
        el.addEventListener('click', function () {
            HelpCenterController.loadTutorial('builtin-1-0');
        });
    }

    function renderSidebarExtras() {
        const fav = document.getElementById('hcFavoritesList');
        const aiList = document.getElementById('hcAiShortcutList');
        const favorites = [
            { label: 'Worker Management', query: 'worker' },
            { label: 'Permissions', query: 'permissions' },
            { label: 'Partner Agencies', query: 'partner agencies' }
        ];
        if (fav) {
            fav.innerHTML = favorites.map(function (f) {
                return '<li><a href="#" class="hc-shortcut-link" data-hc-fav="' + f.query + '"><i class="fas fa-thumbtack"></i> ' + f.label + '</a></li>';
            }).join('');
            fav.querySelectorAll('[data-hc-fav]').forEach(function (a) {
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    runSearch(a.getAttribute('data-hc-fav'));
                });
            });
        }
        if (aiList) {
            aiList.innerHTML = HC_COPILOT_CHIPS.map(function (c) {
                return '<li><a href="#" class="hc-shortcut-link" data-hc-ai-chip="' + c.replace(/"/g, '') + '"><i class="fas fa-magic"></i> ' + c + '</a></li>';
            }).join('');
            aiList.querySelectorAll('[data-hc-ai-chip]').forEach(function (a) {
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    const chip = a.getAttribute('data-hc-ai-chip');
                    appendCopilotUser(chip);
                    streamCopilotResponse(buildCopilotAnswer(chip), true, chip);
                    document.getElementById('hcCopilot').className = 'hc-copilot hc-copilot--expanded';
                });
            });
        }
    }

    function renderParticles() {
        const box = document.getElementById('hcParticles');
        if (!box || box.childElementCount > 0) return;
        for (let i = 0; i < 24; i++) {
            const p = document.createElement('span');
            p.className = 'hc-particle';
            p.style.left = Math.random() * 100 + '%';
            p.style.top = Math.random() * 100 + '%';
            p.style.animationDelay = (Math.random() * 12) + 's';
            p.style.animationDuration = (10 + Math.random() * 8) + 's';
            box.appendChild(p);
        }
    }

    function renderCmdCommands() {
        const list = document.getElementById('hcCmdCommandsList');
        if (!list) return;
        const items = HC_COMMAND_ACTIONS.map(function (cmd) {
            return {
                title: cmd.title,
                meta: cmd.meta,
                icon: cmd.icon,
                action: function () { runSearch(cmd.query); bumpAiUsage(); }
            };
        });
        list.innerHTML = items.map(function (item, i) {
            return cmdItemHtml({ title: item.title, meta: item.meta, icon: item.icon }, i);
        }).join('');
        bindCmdItems(list, items);
    }

    function refreshCopilotDynamicChips() {
        const chips = document.getElementById('hcCopilotChips');
        if (!chips) return;
        const dynamic = [];
        if (HC_CONTEXT.tutorialTitle) {
            dynamic.push('Summarize this section');
            dynamic.push('Explain this workflow');
            dynamic.push('What should I do next?');
        } else if (HC_CONTEXT.categoryName) {
            dynamic.push('Best tutorial in ' + HC_CONTEXT.categoryName);
            dynamic.push('Generate checklist for ' + HC_CONTEXT.categoryName);
        } else {
            dynamic.push('Show onboarding path');
            dynamic.push('Open incident center');
        }
        chips.querySelectorAll('.hc-chip--dynamic').forEach(function (n) { n.remove(); });
        dynamic.forEach(function (label) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'hc-chip hc-chip--dynamic';
            b.textContent = label;
            b.addEventListener('click', function () {
                appendCopilotUser(label);
                streamCopilotResponse(buildCopilotAnswer(label), true, label);
            });
            chips.appendChild(b);
        });
    }

    function setHubChromeVisible(visible) {
        ['hcHero', 'hcTelemetry', 'hcTrustStrip'].forEach(function (id) {
            const el = document.getElementById(id);
            if (!el) return;
            el.classList.toggle(id === 'hcHero' ? 'hc-hero--hidden' : (id === 'hcTelemetry' ? 'hc-telemetry--hidden' : 'hc-trust-strip--hidden'), !visible);
        });
    }

    function setupCategorySpotlight() {
        document.addEventListener('mousemove', function (e) {
            const card = e.target.closest('.hc-categories-adaptive .category-card');
            if (!card) return;
            const r = card.getBoundingClientRect();
            card.style.setProperty('--spot-x', ((e.clientX - r.left) / r.width * 100) + '%');
            card.style.setProperty('--spot-y', ((e.clientY - r.top) / r.height * 100) + '%');
        });
    }

    function setupArticleToolbar(tutorial) {
        const bar = document.getElementById('hcArticleToolbar');
        if (bar) bar.classList.remove('help-hidden');
        const deps = document.getElementById('hcArticleDeps');
        if (deps) {
            deps.innerHTML = '<span>Updated ~2h ago</span> · <span>AI reviewed</span> · <span>Prerequisites: basic navigation</span>';
        }
        const checklistBtn = document.getElementById('hcChecklistMode');
        const main = document.querySelector('.hc-article-main');
        if (checklistBtn && main) {
            checklistBtn.onclick = function () {
                main.classList.toggle('hc-checklist-mode');
                checklistBtn.classList.toggle('active');
            };
        }
        const explainBtn = document.getElementById('hcExplainWorkflow');
        if (explainBtn) {
            explainBtn.onclick = function () {
                appendCopilotUser('Explain this workflow');
                streamCopilotResponse(buildCopilotAnswer('explain workflow ' + (HC_CONTEXT.tutorialTitle || '')), true, 'Explain this workflow');
                document.getElementById('hcCopilot').className = 'hc-copilot hc-copilot--expanded';
            };
        }
        const sumBtn = document.getElementById('hcCopilotSummarize');
        if (sumBtn) {
            sumBtn.onclick = function () {
                const gen = document.getElementById('hcGenerateSummary');
                if (gen) gen.click();
                appendCopilotUser('Summarize this article');
                streamCopilotResponse(buildCopilotAnswer('summarize ' + (HC_CONTEXT.tutorialTitle || '')), true, 'Summarize this article');
            };
        }
    }

    function updateLastAuditRelative() {
        const t = document.getElementById('hcLastAuditDate');
        if (t) {
            t.textContent = '2 hours ago';
            t.removeAttribute('datetime');
        }
    }

    /* Public hooks for help-center.js */
    window.HelpCenterEnterprise = {
        init: function () {
            renderRecentPages();
            loadProgressUI();
            setupCmdPalette();
            setupCopilot();
            setupSidebar();
            setupHeroSearch();
            setHubChromeVisible(true);
        },
        onCategoriesRendered: function () {},
        onCategoryOpened: function (category) {
            HC_CONTEXT.categoryName = category && category.name ? category.name : null;
            HC_CONTEXT.categoryId = category && category.id ? category.id : null;
            HC_CONTEXT.tutorialTitle = null;
            updateCopilotContext();
        },
        onTutorialLoaded: function (tutorial) {
            const title = (tutorial.content && tutorial.content.title) || tutorial.title || 'Tutorial';
            HC_CONTEXT.tutorialTitle = title;
            saveRecentPage(title, tutorial.id);
            updateCopilotContext(title);
            setTimeout(function () {
                buildArticleToc();
                setupArticleToolbar(tutorial);
            }, 100);
        },
        onViewChange: function (view) {
            HC_CONTEXT.view = view;
            const isHub = view === 'homeHubView' || view === 'categoryGridView';
            setHubChromeVisible(isHub);
            const toolbar = document.getElementById('hcArticleToolbar');
            if (toolbar) toolbar.classList.toggle('help-hidden', view !== 'tutorialDetailView');
            if (isHub) {
                HC_CONTEXT.tutorialTitle = null;
                HC_CONTEXT.categoryName = null;
                updateCopilotContext('Knowledge Hub');
            } else if (view === 'tutorialListView' && HC_CONTEXT.categoryName) {
                updateCopilotContext();
            }
        },
        onProgressLoaded: function () {},
        saveRecentPage: saveRecentPage,
        smartSearch: smartSearch,
        buildCopilotAnswer: buildCopilotAnswer
    };

    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('helpCenterRoot')) {
            window.HelpCenterEnterprise.init();
        }
    });
})();
