/**
 * Help Center — lightweight UI helpers (search, progress, TOC, quick links)
 */
(function () {
    'use strict';

    const HC_QUICK_LINKS = [
        { label: 'Getting started', query: 'getting started' },
        { label: 'Permissions', query: 'permissions roles' },
        { label: 'Payroll', query: 'payroll' },
        { label: 'Troubleshooting', query: 'troubleshooting' }
    ];

    let currentTutorialId = null;

    function runSearch(query) {
        if (!query || typeof HelpCenterController === 'undefined') return;
        HelpCenterController.search(query);
    }

    function renderQuickLinks() {
        const root = document.getElementById('hcQuickLinks');
        if (!root) return;
        root.innerHTML = '';
        HC_QUICK_LINKS.forEach(function (item) {
            const a = document.createElement('a');
            a.href = '#';
            a.textContent = item.label;
            a.addEventListener('click', function (e) {
                e.preventDefault();
                const input = document.getElementById('helpSearchInput');
                if (input) input.value = item.query;
                runSearch(item.query);
            });
            root.appendChild(a);
        });
    }

    async function loadProgressUI() {
        try {
            if (typeof HelpCenterAPIHandler === 'undefined') return;
            const res = await HelpCenterAPIHandler.getProgress();
            let completed = 0;
            let inProgress = 0;
            if (res.success && res.data) {
                completed = res.data.completed_count || 0;
                inProgress = res.data.in_progress_count || 0;
            }
            const total = Math.max(completed + inProgress, 1);
            const pct = Math.round((completed / total) * 100);
            const fill = document.getElementById('hcProgressBarFill');
            const text = document.getElementById('hcProgressText');
            if (fill) fill.style.width = pct + '%';
            if (text) text.textContent = pct + '%';
        } catch (e) {
            /* ignore */
        }
    }

    function setTocVisible(visible) {
        const root = document.getElementById('helpCenterRoot');
        if (root) root.classList.toggle('hc-toc-visible', !!visible);
    }

    function setupFeedback() {
        document.querySelectorAll('.hc-feedback-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.hc-feedback-btn').forEach(function (b) {
                    b.classList.remove('active');
                });
                btn.classList.add('active');
                if (typeof showSuccessMessage === 'function' && typeof window.t === 'function') {
                    showSuccessMessage(window.t('thanksForFeedback'));
                }
            });
        });
    }

    function buildArticleToc() {
        const body = document.querySelector('.tutorial-detail-body');
        const nav = document.getElementById('hcTocNav');
        const tocPanel = document.getElementById('hcArticleToc');
        const footer = document.getElementById('hcArticleFooter');
        if (!body || !nav) return;

        nav.innerHTML = '';
        const headings = body.querySelectorAll('h2, h3');
        if (!headings.length) {
            if (tocPanel) tocPanel.classList.add('help-hidden');
            setTocVisible(false);
        } else {
            if (tocPanel) tocPanel.classList.remove('help-hidden');
            setTocVisible(true);
            headings.forEach(function (h, i) {
                if (!h.id) h.id = 'hc-h-' + i;
                const a = document.createElement('a');
                a.href = '#' + h.id;
                a.textContent = h.textContent;
                if (h.tagName === 'H3') a.classList.add('hc-toc-h3');
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    h.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
                nav.appendChild(a);
            });
        }

        if (footer) footer.classList.remove('help-hidden');
        setupFeedback();
    }

    function setHubChromeVisible(visible) {
        const hero = document.getElementById('hcHero');
        if (hero) hero.classList.toggle('hc-hero--hidden', !visible);
    }

    function highlightSidebarCategory(categoryId) {
        document.querySelectorAll('.category-link').forEach(function (link) {
            link.classList.toggle('active', link.dataset.categoryId === String(categoryId));
        });
    }

    window.HelpCenterEnterprise = {
        init: function () {
            renderQuickLinks();
            loadProgressUI();
            setHubChromeVisible(true);
        },
        onCategoriesRendered: function () {},
        onCategoryOpened: function (category) {
            if (category && category.id) highlightSidebarCategory(category.id);
        },
        onTutorialLoaded: function (tutorial) {
            currentTutorialId = tutorial && tutorial.id ? tutorial.id : null;
            setTimeout(buildArticleToc, 50);
        },
        onViewChange: function (view) {
            const isHub = view === 'homeHubView' || view === 'categoryGridView';
            const isArticle = view === 'tutorialDetailView';
            setHubChromeVisible(isHub);
            if (!isArticle) {
                const tocPanel = document.getElementById('hcArticleToc');
                if (tocPanel) tocPanel.classList.add('help-hidden');
                setTocVisible(false);
            }
            if (view === 'homeHubView') {
                document.querySelectorAll('.category-link').forEach(function (link) {
                    link.classList.remove('active');
                });
            }
        },
        onProgressLoaded: function () {}
    };

    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('helpCenterRoot')) {
            window.HelpCenterEnterprise.init();
        }
    });
})();
