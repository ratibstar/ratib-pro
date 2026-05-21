<?php
/**
 * Help & Learning Center
 */
require_once '../includes/config.php';
require_once '../includes/permissions.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

$pageTitle = "Help Center";
$pageCss = [
    asset('css/help-center/help-center.css') . "?v=" . time(),
    asset('css/help-center/help-center-enterprise.css') . "?v=" . time(),
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
    <header class="hc-site-header" id="hcSiteHeader">
        <div class="hc-top-bar">
            <div class="hc-top-bar-inner">
                <a href="#" class="hc-top-bar-brand breadcrumb-link" data-action="home">
                    <span data-translate="enterpriseTitle">Help Center</span>
                </a>
                <div class="hc-search hc-search--top">
                    <i class="fas fa-search hc-search-icon" aria-hidden="true"></i>
                    <input type="text" id="helpSearchInput" class="hc-search-input search-input"
                        data-translate-placeholder="searchPlaceholder"
                        placeholder="Search guides…" autocomplete="off">
                </div>
                <div class="hc-top-bar-actions">
                    <div class="hc-progress-compact" title="Learning progress">
                        <div class="hc-progress-bar" id="hcProgressBar">
                            <div class="hc-progress-bar-fill" id="hcProgressBarFill"></div>
                        </div>
                        <span class="hc-progress-text" id="hcProgressText">0%</span>
                    </div>
                    <div class="language-switcher-container">
                        <label for="helpLanguageSwitcher" class="language-label">
                            <span class="language-label-text" data-translate="languageLabel">Language</span>
                        </label>
                        <select id="helpLanguageSwitcher" class="language-switcher">
                            <option value="en">English</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <nav class="hc-top-nav" id="categoriesList" aria-label="Categories">
            <div class="hc-top-nav-loading">
                <span data-translate="loadingCategories">Loading categories...</span>
            </div>
        </nav>

        <nav class="help-breadcrumbs hc-breadcrumbs hc-top-breadcrumbs" id="helpBreadcrumbs" aria-label="Breadcrumb">
            <a href="#" class="breadcrumb-link" data-action="home">
                <span data-translate="home">Home</span>
            </a>
            <span class="breadcrumb-separator">/</span>
            <span class="breadcrumb-current" data-translate="knowledgeHub">All guides</span>
        </nav>

        <nav class="hc-top-toc help-hidden" id="hcArticleToc" aria-label="On this page">
            <span class="hc-top-toc-label" data-translate="onThisPage">On this page</span>
            <div class="hc-top-toc-scroll" id="hcTocNav"></div>
        </nav>
    </header>

    <div class="hc-shell">
        <main class="hc-main">
            <section class="hc-hero" id="hcHero">
                <h1 class="hc-hero-title" data-translate="enterpriseTitle">Help Center</h1>
                <p class="hc-hero-subtitle" data-translate="enterpriseSubtitle">Guides and documentation for the RATEB platform.</p>
                <div class="hc-quick-links" id="hcQuickLinks"></div>
            </section>

            <div class="help-center-content hc-content">
                <div class="help-main-content hc-main-content">
                    <div class="help-view-mode" id="homeHubView">
                        <header class="hc-section-header">
                            <h2 data-translate="browseCategories">Categories</h2>
                            <p data-translate="browseCategoriesSub">Browse guides by topic</p>
                        </header>
                        <div class="categories-grid hc-categories-grid" id="categoriesGrid">
                            <div class="loading-placeholder">
                                <span data-translate="loadingCategories">Loading categories...</span>
                            </div>
                        </div>
                    </div>

                    <div class="help-view-mode help-hidden" id="categoryGridView">
                        <div class="categories-grid" id="categoriesGridLegacy"></div>
                    </div>

                    <div class="help-view-mode help-hidden" id="tutorialListView">
                        <button class="help-back-button" id="backFromTutorialList" data-action="backToCategories" type="button">
                            <i class="fas fa-arrow-left"></i>
                            <span data-translate="backToCategories">Back</span>
                        </button>
                        <header class="hc-section-header hc-section-header--inline">
                            <h2 id="tutorialListTitle" data-translate="tutorials">Tutorials</h2>
                        </header>
                        <div class="tutorial-list grid-view" id="tutorialList"></div>
                        <div class="pagination-wrapper help-hidden" id="tutorialPagination"></div>
                    </div>

                    <div class="help-view-mode help-hidden" id="tutorialDetailView">
                        <button class="help-back-button" id="backFromTutorialDetail" data-action="backFromDetail" type="button">
                            <i class="fas fa-arrow-left"></i>
                            <span data-translate="backToTutorials">Back</span>
                        </button>
                        <div class="hc-article-layout">
                            <article class="hc-article-main">
                                <div class="tutorial-detail" id="tutorialDetail"></div>
                                <footer class="hc-article-footer help-hidden" id="hcArticleFooter">
                                    <p class="hc-article-feedback" data-translate="wasThisHelpful">Was this helpful?</p>
                                    <div class="hc-feedback-actions">
                                        <button type="button" class="hc-feedback-btn" data-reaction="yes" aria-label="Yes"><i class="fas fa-thumbs-up"></i></button>
                                        <button type="button" class="hc-feedback-btn" data-reaction="no" aria-label="No"><i class="fas fa-thumbs-down"></i></button>
                                    </div>
                                </footer>
                            </article>
                        </div>
                    </div>

                    <div class="help-view-mode help-hidden" id="searchResultsView">
                        <button class="help-back-button" id="backFromSearchResults" data-action="backToCategories" type="button">
                            <i class="fas fa-arrow-left"></i>
                            <span data-translate="backToCategories">Back</span>
                        </button>
                        <header class="hc-section-header hc-section-header--inline">
                            <h2 data-translate="searchResults">Search results</h2>
                            <span class="hc-results-count" id="searchResultsCount">0 <span data-translate="results">results</span></span>
                        </header>
                        <div class="search-results" id="searchResults"></div>
                    </div>

                    <div class="empty-state help-hidden" id="emptyState">
                        <p data-translate="noTutorialsFoundTitle">No tutorials found</p>
                        <p data-translate="noTutorialsFoundText">Try another search or browse categories.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<div class="loading-overlay" id="helpLoadingOverlay">
    <div class="loading-spinner"></div>
</div>

<?php include '../includes/footer.php'; ?>
