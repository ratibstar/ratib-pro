<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/help-center.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/help-center.php`.
 */
require_once '../includes/config.php';
require_once '../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

$pageTitle = "Help & Learning Center";
$pageCss = [
    asset('css/help-center/help-center.css') . "?v=" . time(),
    asset('css/contextual-help.css') . "?v=" . time()
];

$helpCenterJsVersion = file_exists(__DIR__ . '/../js/help-center/help-center.js')
    ? filemtime(__DIR__ . '/../js/help-center/help-center.js')
    : time();

$builtinContentVersion = file_exists(__DIR__ . '/../js/help-center/help-center-builtin-content.js')
    ? filemtime(__DIR__ . '/../js/help-center/help-center-builtin-content.js')
    : time();
$pageJs = [
    asset('js/contextual-help.js') . "?v=" . time(),
    asset('js/help-center/help-center-builtin-content.js') . "?v=" . time(), // Force refresh with time() instead of filemtime
    asset('js/help-center/help-center-translations.js') . "?v=" . time(),
    asset('js/help-center/help-center.js') . "?v=" . $helpCenterJsVersion
];

include '../includes/header.php';
?>

<div class="help-center-wrapper">
    <!-- Top Language Bar -->
    <div class="help-center-top-bar">
        <div class="top-bar-content">
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

    <!-- Header Section -->
    <div class="help-center-header">
        <div class="header-content">
            <h1 class="help-center-title">
                <i class="fas fa-question-circle"></i>
                <span data-translate="title">Help & Learning Center</span>
            </h1>
            <p class="help-center-subtitle" data-translate="subtitle">Master the system with step-by-step guides, interactive tutorials, and expert tips</p>
        </div>
        
        <!-- Search Bar -->
        <div class="search-container">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="helpSearchInput" class="search-input" data-translate-placeholder="searchPlaceholder" placeholder="Search tutorials, guides, and FAQs..." autocomplete="off">
                <button class="search-clear help-hidden" id="searchClearBtn" aria-label="Clear search" data-translate-aria-label="clearSearch">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

    </div>

    <!-- Breadcrumbs -->
    <nav class="help-breadcrumbs" id="helpBreadcrumbs">
        <a href="#" class="breadcrumb-link" data-action="home">
            <i class="fas fa-home"></i> <span class="breadcrumb-home-text" data-translate="home">Home</span>
        </a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current" data-translate="allCategories">All Categories</span>
    </nav>

    <!-- Main Content Area -->
    <div class="help-center-content">
        <!-- Sidebar -->
        <aside class="help-sidebar" id="helpSidebar">
            <div class="sidebar-header">
                <h3 data-translate="categories">Categories</h3>
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Close sidebar" data-translate-aria-label="closeSidebar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="categories-list" id="categoriesList">
                <!-- Categories will be loaded dynamically -->
                <div class="loading-placeholder">
                    <i class="fas fa-spinner fa-spin"></i> <span data-translate="loadingCategories">Loading categories...</span>
                </div>
            </div>
            
            <!-- User Progress Summary -->
            <div class="progress-summary help-hidden" id="progressSummary">
                <h4 data-translate="yourProgress">Your Progress</h4>
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
        </aside>

        <!-- Main Content -->
        <main class="help-main-content">
            <!-- Category Grid View -->
            <div class="help-view-mode" id="categoryGridView">
                <div class="categories-grid" id="categoriesGrid">
                    <!-- Categories grid will be loaded dynamically -->
                    <div class="loading-placeholder">
                        <i class="fas fa-spinner fa-spin"></i> <span data-translate="loadingCategories">Loading categories...</span>
                    </div>
                </div>
            </div>

            <!-- Tutorial List View -->
            <div class="help-view-mode help-hidden" id="tutorialListView">
                <div class="tutorial-list-header">
                    <button class="help-back-button" id="backFromTutorialList" data-action="backToCategories" aria-label="Back" data-translate-aria-label="back">
                        <i class="fas fa-arrow-left"></i>
                        <span data-translate="backToCategories">Back to Categories</span>
                    </button>
                    <h2 id="tutorialListTitle" data-translate="tutorials">Tutorials</h2>
                    <div class="view-controls">
                        <button class="view-toggle active" data-view="grid" data-translate-title="gridView" title="Grid View">
                            <i class="fas fa-th"></i>
                        </button>
                        <button class="view-toggle" data-view="list" data-translate-title="listView" title="List View">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                </div>
                <div class="tutorial-list" id="tutorialList">
                    <!-- Tutorials will be loaded dynamically -->
                </div>
                <div class="pagination-wrapper help-hidden" id="tutorialPagination">
                    <!-- Pagination will be loaded dynamically -->
                </div>
            </div>

            <!-- Tutorial Detail View -->
            <div class="help-view-mode help-hidden" id="tutorialDetailView">
                <button class="help-back-button" id="backFromTutorialDetail" data-action="backFromDetail" aria-label="Back" data-translate-aria-label="back">
                    <i class="fas fa-arrow-left"></i>
                    <span data-translate="backToTutorials">Back to Tutorials</span>
                </button>
                <div class="tutorial-detail" id="tutorialDetail">
                    <!-- Tutorial detail will be loaded dynamically -->
                </div>
            </div>

            <!-- Search Results View -->
            <div class="help-view-mode help-hidden" id="searchResultsView">
                <div class="search-results-header">
                    <button class="help-back-button" id="backFromSearchResults" data-action="backToCategories" aria-label="Back" data-translate-aria-label="back">
                        <i class="fas fa-arrow-left"></i>
                        <span data-translate="backToCategories">Back to Categories</span>
                    </button>
                    <h2 data-translate="searchResults">Search Results</h2>
                    <span class="results-count" id="searchResultsCount"><span class="results-count-number">0</span> <span data-translate="results">results</span></span>
                </div>
                <div class="search-results" id="searchResults">
                    <!-- Search results will be loaded dynamically -->
                </div>
            </div>

            <!-- Empty State -->
            <div class="empty-state help-hidden" id="emptyState">
                <i class="fas fa-book-open"></i>
                <h3 data-translate="noTutorialsFoundTitle">No tutorials found</h3>
                <p data-translate="noTutorialsFoundText">Try adjusting your search or browse categories</p>
            </div>
        </main>
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
        <div class="modal-body tutorial-viewer-body" id="tutorialViewerBody">
            <!-- Tutorial content will be loaded here -->
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="helpLoadingOverlay">
    <div class="loading-spinner"></div>
</div>

<?php include '../includes/footer.php'; ?>
