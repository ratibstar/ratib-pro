<?php
/**
 * EN: Enterprise Help & Learning Center — AI-native knowledge hub.
 * AR: مركز المساعدة والتعلم المؤسسي — مركز المعرفة المدعوم بالذكاء الاصطناعي.
 */
require_once '../includes/config.php';
require_once '../includes/permissions.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

$pageTitle = "Enterprise Operations Knowledge Center";
$pageCss = [
    asset('css/help-center/help-center.css') . "?v=" . time(),
    asset('css/help-center/help-center-enterprise.css') . "?v=" . time(),
    asset('css/help-center/help-center-polish.css') . "?v=" . time(),
    asset('css/contextual-help.css') . "?v=" . time()
];

$helpCenterJsVersion = file_exists(__DIR__ . '/../js/help-center/help-center.js')
    ? filemtime(__DIR__ . '/../js/help-center/help-center.js')
    : time();
$enterpriseJsVersion = file_exists(__DIR__ . '/../js/help-center/help-center-enterprise.js')
    ? filemtime(__DIR__ . '/../js/help-center/help-center-enterprise.js')
    : time();
$builtinContentVersion = file_exists(__DIR__ . '/../js/help-center/help-center-builtin-content.js')
    ? filemtime(__DIR__ . '/../js/help-center/help-center-builtin-content.js')
    : time();

$pageJs = [
    asset('js/contextual-help.js') . "?v=" . time(),
    asset('js/help-center/help-center-builtin-content.js') . "?v=" . $builtinContentVersion,
    asset('js/help-center/help-center-translations.js') . "?v=" . time(),
    asset('js/help-center/help-center.js') . "?v=" . $helpCenterJsVersion,
    asset('js/help-center/help-center-enterprise.js') . "?v=" . $enterpriseJsVersion
];

include '../includes/header.php';
?>

<div class="help-center-wrapper hc-enterprise" id="helpCenterRoot">
    <!-- Ambient mesh background -->
    <div class="hc-mesh" aria-hidden="true">
        <div class="hc-mesh-noise"></div>
    </div>

    <!-- Top utility bar -->
    <header class="hc-top-bar">
        <div class="hc-top-bar-inner">
            <button type="button" class="hc-sidebar-mobile-toggle" id="hcSidebarMobileToggle" aria-label="Open navigation">
                <i class="fas fa-bars"></i>
            </button>
            <div class="hc-top-bar-meta"></div>
            <div class="hc-top-bar-actions">
                <button type="button" class="hc-cmd-trigger" id="hcCmdTrigger" title="Command palette (Ctrl+K)">
                    <i class="fas fa-search"></i>
                    <span data-translate="searchCmdHint">Search docs…</span>
                    <kbd>Ctrl K</kbd>
                </button>
                <div class="language-switcher-container">
                    <label for="helpLanguageSwitcher" class="language-label">
                        <i class="fas fa-globe"></i>
                        <span class="language-label-text" data-translate="languageLabel">Language:</span>
                    </label>
                    <select id="helpLanguageSwitcher" class="language-switcher">
                        <option value="en">English</option>
                    </select>
                </div>
            </div>
        </div>
    </header>

    <div class="hc-shell">
        <!-- Slim contextual sidebar -->
        <aside class="help-sidebar hc-sidebar" id="helpSidebar">
            <div class="sidebar-header hc-sidebar-header">
                <h3 data-translate="navLabel">Navigate</h3>
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Close sidebar" data-translate-aria-label="closeSidebar">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="hc-sidebar-section">
                <button type="button" class="hc-sidebar-section-toggle" data-section="shortcuts" aria-expanded="true">
                    <span data-translate="shortcuts">Shortcuts</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="hc-sidebar-section-body" id="hcSidebarShortcuts">
                    <ul class="hc-shortcut-list">
                        <li><a href="#" class="hc-shortcut-link" data-hc-action="home"><i class="fas fa-home"></i> <span data-translate="home">Home</span></a></li>
                        <li><a href="#" class="hc-shortcut-link" data-hc-action="quick-start"><i class="fas fa-rocket"></i> <span data-translate="quickStartHub">Quick Start</span></a></li>
                        <li><a href="#" class="hc-shortcut-link" data-hc-action="playbooks"><i class="fas fa-sitemap"></i> <span data-translate="operationsPlaybooks">Playbooks</span></a></li>
                        <li><a href="#" class="hc-shortcut-link" data-hc-action="troubleshooting"><i class="fas fa-life-ring"></i> <span data-translate="incidentCenter">Troubleshooting</span></a></li>
                    </ul>
                </div>
            </div>

            <div class="hc-sidebar-section">
                <button type="button" class="hc-sidebar-section-toggle" data-section="categories" aria-expanded="true">
                    <span data-translate="categories">Categories</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="hc-sidebar-section-body" id="categoriesList">
                    <div class="loading-placeholder hc-shimmer">
                        <i class="fas fa-spinner fa-spin"></i> <span data-translate="loadingCategories">Loading categories...</span>
                    </div>
                </div>
            </div>

            <div class="hc-sidebar-section">
                <button type="button" class="hc-sidebar-section-toggle" data-section="progress" aria-expanded="true">
                    <span data-translate="yourProgress">Your Progress</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="hc-sidebar-section-body">
                    <div class="progress-summary" id="progressSummary">
                        <div class="hc-progress-ring-wrap">
                            <svg class="hc-progress-ring" viewBox="0 0 36 36" aria-hidden="true">
                                <path class="hc-progress-ring-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                <path class="hc-progress-ring-fill" id="hcProgressRingFill" stroke-dasharray="0, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                            </svg>
                            <span class="hc-progress-ring-label" id="hcProgressPercent">0%</span>
                        </div>
                        <div class="progress-stats">
                            <div class="stat-item">
                                <span class="stat-label" data-translate="completed">Completed</span>
                                <span class="stat-value" id="completedCount">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label" data-translate="inProgress">In Progress</span>
                                <span class="stat-value" id="inProgressCount">0</span>
                            </div>
                        </div>
                    </div>
                    <div class="hc-recent-pages" id="hcRecentPages">
                        <h4 data-translate="recentPages">Recent</h4>
                        <ul class="hc-recent-list" id="hcRecentList"></ul>
                    </div>
                </div>
            </div>
        </aside>

        <main class="hc-main">
            <!-- Hero: split layout -->
            <section class="hc-hero" id="hcHero">
                <div class="hc-hero-left">
                    <p class="hc-eyebrow" data-translate="heroEyebrow">RATIB Enterprise Knowledge</p>
                    <h1 class="hc-hero-title" data-translate="enterpriseTitle">Enterprise Operations Knowledge Center</h1>
                    <p class="hc-hero-subtitle" data-translate="enterpriseSubtitle">Interactive guidance, operational workflows, AI assistance, and production-grade learning for the RATIB platform.</p>

                    <div class="hc-ai-ask-bar" id="hcAiAskBar">
                        <div class="hc-ai-ask-inner">
                            <i class="fas fa-magic hc-ai-sparkle"></i>
                            <input type="text" id="helpSearchInput" class="hc-ai-ask-input search-input" data-translate-placeholder="aiAskPlaceholder" placeholder="Ask AI or search workflows, guides, and playbooks…" autocomplete="off" aria-label="Search and ask AI">
                            <button type="button" class="hc-voice-btn" id="hcVoiceBtn" aria-label="Voice search" title="Voice search"><i class="fas fa-microphone"></i></button>
                            <button type="button" class="hc-ai-submit" id="hcAiSubmit" aria-label="Search"><i class="fas fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <div class="hc-quick-actions" id="hcQuickActions">
                        <span class="hc-quick-label" data-translate="aiQuickActions">AI quick actions</span>
                        <div class="hc-chip-row" id="hcQuickActionChips"></div>
                    </div>

                </div>

                <div class="hc-hero-right">
                    <div class="hc-panel hc-panel--glass" id="hcOnboardingPanel">
                        <div class="hc-panel-header">
                            <h3 data-translate="continueLearning">Continue where you left off</h3>
                            
                        </div>
                        <div class="hc-continue-card" id="hcContinueCard">
                            <div class="hc-shimmer hc-continue-placeholder" data-translate="loadingProgress">Loading your progress…</div>
                        </div>
                        <div class="hc-recent-tutorials" id="hcRecentTutorials">
                            <h4 data-translate="recentTutorials">Recent tutorials</h4>
                            <ul class="hc-recent-tutorial-list" id="hcRecentTutorialList"></ul>
                        </div>
                    </div>
                </div>
            </section>
<nav class="help-breadcrumbs hc-breadcrumbs" id="helpBreadcrumbs">
                <a href="#" class="breadcrumb-link" data-action="home">
                    <i class="fas fa-home"></i> <span class="breadcrumb-home-text" data-translate="home">Home</span>
                </a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-current" data-translate="knowledgeHub">Knowledge Hub</span>
            </nav>

            <div class="help-center-content hc-content">
                <div class="help-main-content hc-main-content">
                    <!-- Home Hub (default) -->
                    <div class="help-view-mode" id="homeHubView">
                        <div class="hc-section-header hc-section-header--spaced">
                            <h2 data-translate="browseCategories">Browse all categories</h2>
                            <p data-translate="browseCategoriesSub">Guides and workflows for your team</p>
                        </div>
                        <div class="categories-grid hc-categories-adaptive" id="categoriesGrid">
                            <div class="loading-placeholder hc-shimmer">
                                <i class="fas fa-spinner fa-spin"></i> <span data-translate="loadingCategories">Loading categories...</span>
                            </div>
                        </div>
                    </div>

                    <!-- Category-only grid (legacy compat) -->
                    <div class="help-view-mode help-hidden" id="categoryGridView">
                        <div class="categories-grid" id="categoriesGridLegacy"></div>
                    </div>

                    <!-- Tutorial List -->
                    <div class="help-view-mode help-hidden" id="tutorialListView">
                        <div class="tutorial-list-header hc-list-header">
                            <button class="help-back-button" id="backFromTutorialList" data-action="backToCategories" aria-label="Back" data-translate-aria-label="back">
                                <i class="fas fa-arrow-left"></i>
                                <span data-translate="backToCategories">Back to Categories</span>
                            </button>
                            <h2 id="tutorialListTitle" data-translate="tutorials">Tutorials</h2>
                            <div class="view-controls">
                                <button class="view-toggle active" data-view="grid" data-translate-title="gridView" title="Grid View"><i class="fas fa-th"></i></button>
                                <button class="view-toggle" data-view="list" data-translate-title="listView" title="List View"><i class="fas fa-list"></i></button>
                            </div>
                        </div>
                        <div class="tutorial-list grid-view" id="tutorialList"></div>
                        <div class="pagination-wrapper help-hidden" id="tutorialPagination"></div>
                    </div>

                    <!-- Tutorial Detail -->
                    <div class="help-view-mode help-hidden" id="tutorialDetailView">
                        <button class="help-back-button" id="backFromTutorialDetail" data-action="backFromDetail" aria-label="Back" data-translate-aria-label="back">
                            <i class="fas fa-arrow-left"></i>
                            <span data-translate="backToTutorials">Back to Tutorials</span>
                        </button>
                        <div class="hc-article-layout">
                            <aside class="hc-article-toc" id="hcArticleToc">
                                <div class="hc-toc-sticky">
                                    <h4 data-translate="onThisPage">On this page</h4>
                                    <nav id="hcTocNav"></nav>
                                    <div class="hc-reading-progress">
                                        <span data-translate="readingProgress">Reading progress</span>
                                        <div class="hc-reading-bar"><div class="hc-reading-fill" id="hcReadingFill"></div></div>
                                    </div>
                                </div>
                            </aside>
                            <div class="hc-article-main">
                                <div class="hc-article-toolbar help-hidden" id="hcArticleToolbar">
                                    <button type="button" class="hc-toolbar-btn" id="hcChecklistMode">Checklist mode</button>
                                    <button type="button" class="hc-toolbar-btn" id="hcExplainWorkflow">Explain workflow</button>
                                    <button type="button" class="hc-toolbar-btn" id="hcCopilotSummarize">Summarize</button>
                                    <div class="hc-article-deps" id="hcArticleDeps"></div>
                                </div>
                                <div class="hc-ai-summary-panel help-hidden" id="hcAiSummaryPanel">
                                    <div class="hc-ai-summary-header">
                                        <i class="fas fa-magic"></i>
                                        <span data-translate="aiSummary">AI Summary</span>
                                        <button type="button" class="hc-ai-summary-generate" id="hcGenerateSummary" data-translate="generateSummary">Generate</button>
                                    </div>
                                    <div class="hc-ai-summary-body" id="hcAiSummaryBody"></div>
                                </div>
                                <div class="tutorial-detail" id="tutorialDetail"></div>
                                <div class="hc-article-footer help-hidden" id="hcArticleFooter">
                                    <div class="hc-feedback-reactions" id="hcFeedbackReactions">
                                        <span data-translate="wasThisHelpful">Was this helpful?</span>
                                        <button type="button" class="hc-reaction" data-reaction="yes"><i class="fas fa-thumbs-up"></i></button>
                                        <button type="button" class="hc-reaction" data-reaction="no"><i class="fas fa-thumbs-down"></i></button>
                                    </div>
                                    <div class="hc-related-articles" id="hcRelatedArticles">
                                        <h4 data-translate="relatedArticles">Related articles</h4>
                                        <ul id="hcRelatedList"></ul>
                                    </div>
                                    <div class="hc-next-steps" id="hcNextSteps">
                                        <h4 data-translate="recommendedNext">Recommended next steps</h4>
                                        <div class="hc-next-step-chips" id="hcNextStepChips"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Search Results -->
                    <div class="help-view-mode help-hidden" id="searchResultsView">
                        <div class="search-results-header hc-list-header">
                            <button class="help-back-button" id="backFromSearchResults" data-action="backToCategories" aria-label="Back" data-translate-aria-label="back">
                                <i class="fas fa-arrow-left"></i>
                                <span data-translate="backToCategories">Back to Categories</span>
                            </button>
                            <h2 data-translate="searchResults">Search Results</h2>
                            <span class="results-count" id="searchResultsCount"><span class="results-count-number">0</span> <span data-translate="results">results</span></span>
                        </div>
                        <div class="search-results hc-search-grouped" id="searchResults"></div>
                    </div>

                    <div class="empty-state help-hidden" id="emptyState">
                        <i class="fas fa-book-open"></i>
                        <h3 data-translate="noTutorialsFoundTitle">No tutorials found</h3>
                        <p data-translate="noTutorialsFoundText">Try adjusting your search or browse categories</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Command palette -->
<div class="hc-cmd-palette help-hidden" id="hcCmdPalette" role="dialog" aria-modal="true" aria-label="Command palette">
    <div class="hc-cmd-backdrop" id="hcCmdBackdrop"></div>
    <div class="hc-cmd-panel">
        <div class="hc-cmd-search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="hcCmdInput" class="hc-cmd-input" placeholder="Search commands, tutorials, workflows…" autocomplete="off">
            <span class="hc-cmd-ai-badge"><i class="fas fa-magic"></i> AI</span>
        </div>
        <div class="hc-cmd-filters" id="hcCmdFilters">
            <button type="button" class="hc-cmd-filter active" data-filter="all">All</button>
            <button type="button" class="hc-cmd-filter" data-filter="tutorials">Tutorials</button>
            <button type="button" class="hc-cmd-filter" data-filter="workflows">Workflows</button>
            <button type="button" class="hc-cmd-filter" data-filter="actions">Actions</button>
        </div>
        <div class="hc-cmd-sections" id="hcCmdResults">
            <div class="hc-cmd-group" id="hcCmdCommands">
                <h4 data-translate="quickCommands">Commands</h4>
                <ul class="hc-cmd-list" id="hcCmdCommandsList"></ul>
            </div>
            <div class="hc-cmd-group" id="hcCmdTrending">
                <h4 data-translate="trendingSearches">Trending</h4>
                <ul class="hc-cmd-list" id="hcCmdTrendingList"></ul>
            </div>
            <div class="hc-cmd-group" id="hcCmdRecent">
                <h4 data-translate="recentSearches">Recent</h4>
                <ul class="hc-cmd-list" id="hcCmdRecentList"></ul>
            </div>
            <div class="hc-cmd-group" id="hcCmdLive">
                <h4 data-translate="results">Results</h4>
                <ul class="hc-cmd-list" id="hcCmdLiveList"></ul>
            </div>
        </div>
        <div class="hc-cmd-footer">
            <span><kbd>↑↓</kbd> navigate</span>
            <span><kbd>↵</kbd> select</span>
            <span><kbd>esc</kbd> close</span>
        </div>
    </div>
</div>

<!-- AI Copilot -->
<div class="hc-copilot hc-copilot--minimized" id="hcCopilot" aria-live="polite">
    <button type="button" class="hc-copilot-toggle" id="hcCopilotToggle" aria-label="Open AI copilot">
        <i class="fas fa-robot"></i>
        <span class="hc-copilot-badge" id="hcCopilotBadge">AI</span>
    </button>
    <div class="hc-copilot-panel" id="hcCopilotPanel">
        <header class="hc-copilot-header">
            <div class="hc-copilot-title">
                <i class="fas fa-magic"></i>
                <span data-translate="copilotTitle">RATIB Operations Copilot</span>
            </div>
            <div class="hc-copilot-controls">
                <button type="button" class="hc-copilot-ctrl" id="hcCopilotDock" title="Dock"><i class="fas fa-columns"></i></button>
                <button type="button" class="hc-copilot-ctrl" id="hcCopilotExpand" title="Expand"><i class="fas fa-expand"></i></button>
                <button type="button" class="hc-copilot-ctrl" id="hcCopilotMinimize" title="Minimize"><i class="fas fa-minus"></i></button>
            </div>
        </header>
        <div class="hc-copilot-context" id="hcCopilotContext">
            <span class="hc-copilot-context-label" data-translate="context">Context:</span>
            <span id="hcCopilotContextText">Knowledge Hub</span>
            <span class="hc-copilot-confidence" id="hcCopilotConfidence">94%</span>
        </div>
        <div class="hc-copilot-messages" id="hcCopilotMessages">
            <div class="hc-copilot-msg hc-copilot-msg--assistant">
                <div class="hc-copilot-avatar"><i class="fas fa-robot"></i></div>
                <div class="hc-copilot-bubble">
                    <p data-translate="copilotWelcome">I can explain workflows, generate checklists, troubleshoot operations, and navigate you to the right guide.</p>
                    <div class="hc-copilot-chips" id="hcCopilotChips"></div>
                </div>
            </div>
        </div>
        <div class="hc-copilot-memory" id="hcCopilotMemoryHint"></div>
        <div class="hc-copilot-composer">
            <textarea id="hcCopilotInput" rows="1" placeholder="Ask about onboarding, permissions, payroll sync…" data-translate-placeholder="copilotPlaceholder"></textarea>
            <button type="button" id="hcCopilotSend" aria-label="Send"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>
</div>

<!-- Tutorial Viewer Modal -->
<div class="modal tutorial-viewer-modal" id="tutorialViewerModal">
    <div class="modal-content tutorial-viewer-content">
        <div class="modal-header">
            <h2 id="tutorialModalTitle" data-translate="tutorial">Tutorial</h2>
            <button class="modal-close" id="tutorialModalClose" aria-label="Close" data-translate-aria-label="close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body tutorial-viewer-body" id="tutorialViewerBody"></div>
    </div>
</div>

<div class="loading-overlay" id="helpLoadingOverlay">
    <div class="loading-spinner"></div>
</div>

<?php include '../includes/footer.php'; ?>