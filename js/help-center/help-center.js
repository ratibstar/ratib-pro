/**
 * EN: Implements frontend interaction behavior in `js/help-center/help-center.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/help-center/help-center.js`.
 */
/**
 * Help & Learning Center - Main JavaScript
 */

// API Configuration
const HelpCenterAPI = {
    base: ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (window.BASE_PATH || '')) + '/api/help-center',
    endpoints: {
        tutorials: '/tutorials.php',
        categories: '/categories.php',
        search: '/search.php',
        progress: '/progress.php',
        ratings: '/ratings.php'
    }
};

// State Management
const HelpCenterState = {
    currentLanguage: 'en',
    currentView: 'categories',
    currentCategory: null,
    currentTutorial: null,
    categories: [],
    tutorials: [],
    searchQuery: '',
    currentPage: 1,
    itemsPerPage: 20
};

// Translation helper
function t(key) {
    return getTranslation(key, HelpCenterState.currentLanguage);
}

// Notification functions (if not defined globally)
if (typeof showErrorMessage === 'undefined') {
    function showErrorMessage(message) {
        if (window.notifications) {
            window.notifications.error(message);
        } else if (typeof showNotification === 'function') {
            showNotification(message, 'error');
        } else {
            alert(message);
        }
    }
    window.showErrorMessage = showErrorMessage;
}

if (typeof showSuccessMessage === 'undefined') {
    function showSuccessMessage(message) {
        if (window.notifications) {
            window.notifications.success(message);
        } else if (typeof showNotification === 'function') {
            showNotification(message, 'success');
        } else {
            alert(message);
        }
    }
    window.showSuccessMessage = showSuccessMessage;
}

// Built-in tutorials (deep details); loaded from help-center-builtin-content.js or fallback below
const HELP_CENTER_BUILTIN = window.HELP_CENTER_BUILTIN || {
    1: [{ id: 'builtin-1-0', title: 'Getting Started', overview: 'Introduction to the Ratib program.', content: '<p>Use the left menu: Dashboard, Agent, SubAgent, Workers, Cases, Accounting, HR, Reports, Contact, Notifications. Bookmark the Help Center.</p>', estimated_time: 5, difficulty_level: 'beginner', views_count: 0 }],
    2: [{ id: 'builtin-2-0', title: 'Dashboard', overview: 'Overview and key numbers.', content: '<p>Review summary cards and charts. Click through to detailed sections. Refresh for latest data.</p>', estimated_time: 5, difficulty_level: 'beginner', views_count: 0 }],
    3: [{ id: 'builtin-3-0', title: 'User Management', overview: 'Roles and permissions.', content: '<p>Go to System Settings. Add users, assign roles, set permissions. Give each role only what it needs.</p>', estimated_time: 5, difficulty_level: 'beginner', views_count: 0 }],
    4: [{ id: 'builtin-4-0', title: 'Contracts & Recruitment', overview: 'Contracts and recruitment workflows.', content: '<p>Create contracts, link parties, track status. Use filters to find items that need action.</p>', estimated_time: 5, difficulty_level: 'beginner', views_count: 0 }],
    5: [{ id: 'builtin-5-0', title: 'Client Management', overview: 'Agents and SubAgents.', content: '<p>Use Agent and SubAgent pages. Add clients with name, contact, phone, email. Link workers and cases.</p>', estimated_time: 5, difficulty_level: 'beginner', views_count: 0 }],
    6: [{ id: 'builtin-6-0', title: 'Worker Management', overview: 'Workers and documents.', content: '<p>Add workers, upload documents, set status. Use filters by status or agent.</p>', estimated_time: 5, difficulty_level: 'beginner', views_count: 0 }],
    7: [{ id: 'builtin-7-0', title: 'Finance & Billing', overview: 'Accounting and transactions.', content: '<p>Open Accounting. Record transactions with date, amount, accounts. Reconcile regularly.</p>', estimated_time: 5, difficulty_level: 'beginner', views_count: 0 }],
    8: [{ id: 'builtin-8-0', title: 'Reports & Analytics', overview: 'Generate and export reports.', content: '<p>Choose report type, set filters, run report. Export to Excel or PDF.</p>', estimated_time: 5, difficulty_level: 'beginner', views_count: 0 }],
    9: [{ id: 'builtin-9-0', title: 'Notifications', overview: 'Alerts and automation.', content: '<p>Check Notifications. Open related records to take action. Adjust settings if available.</p>', estimated_time: 5, difficulty_level: 'beginner', views_count: 0 }],
    10: [{ id: 'builtin-10-0', title: 'Troubleshooting', overview: 'Common issues and fixes.', content: '<p>Refresh and clear cache. Check permissions for missing menus. Read error messages for failed saves.</p>', estimated_time: 5, difficulty_level: 'beginner', views_count: 0 }],
    11: [{ id: 'builtin-11-0', title: 'Best Practices', overview: 'Consistency and security.', content: '<p>Use consistent naming. Review data on a schedule. Do not share passwords.</p>', estimated_time: 5, difficulty_level: 'beginner', views_count: 0 }],
    12: [{ id: 'builtin-12-0', title: 'Compliance & Legal', overview: 'Records and compliance.', content: '<p>Keep accurate records. Do not delete audit-relevant data. Report concerns through the correct channel.</p>', estimated_time: 5, difficulty_level: 'beginner', views_count: 0 }],
    13: [{ id: 'builtin-13-0', title: '🌍 Partner Agencies', overview: 'Partner offices and deployment table.', content: '<p>Open <strong>Partner Agencies</strong> from the menu. Use <strong>View</strong> on a row to see workers sent and update deployment status.</p>', estimated_time: 5, difficulty_level: 'beginner', views_count: 0 }]
};
// Chat widget and other scripts read only window — keep in sync (fallback above if builtin file failed).
window.HELP_CENTER_BUILTIN = HELP_CENTER_BUILTIN;

/** Find category in API tree (root may have children). */
function helpCenterFindCategory(nodes, categoryId) {
    if (!Array.isArray(nodes) || categoryId == null || categoryId === '') return null;
    for (let i = 0; i < nodes.length; i++) {
        const n = nodes[i];
        if (String(n.id) === String(categoryId)) return n;
        if (n.children && n.children.length) {
            const sub = helpCenterFindCategory(n.children, categoryId);
            if (sub) return sub;
        }
    }
    return null;
}

/** Built-in tutorials: DB id may differ from 13 if insert used auto-increment — match by slug. */
function helpCenterBuiltinTutorialsForCategory(categoryId) {
    const cat = helpCenterFindCategory(HelpCenterState.categories, categoryId);
    if (cat && String(cat.slug || '') === 'partner-agencies' && HELP_CENTER_BUILTIN[13]) {
        return HELP_CENTER_BUILTIN[13];
    }
    return HELP_CENTER_BUILTIN[categoryId];
}

// Helper Functions
function getApiBase() {
    return (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (window.BASE_PATH || '');
}

function formatDate(dateString) {
    if (!dateString) return '';
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString;
        
        // English-only locale
        const locale = 'en-US';
        return date.toLocaleDateString(locale, { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
    } catch (e) {
        return dateString;
    }
}

function showLoading(show = true) {
    const overlay = document.getElementById('helpLoadingOverlay');
    if (overlay) {
        overlay.classList.toggle('active', show);
    }
}

function showView(viewName) {
    const views = ['categoryGridView', 'tutorialListView', 'tutorialDetailView', 'searchResultsView'];
    views.forEach(view => {
        const element = document.getElementById(view);
        if (element) {
            const show = view === viewName;
            element.classList.toggle('help-hidden', !show);
            element.style.display = show ? '' : 'none';
        }
    });
    HelpCenterState.currentView = viewName.replace('View', '').replace(/([A-Z])/g, ' $1').trim().toLowerCase();
}

// API Functions
const HelpCenterAPIHandler = {
    async fetch(endpoint, params = {}, options = {}) {
        try {
            const queryString = new URLSearchParams({
                ...params,
                lang: HelpCenterState.currentLanguage
            }).toString();
            
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout
            
            const response = await fetch(`${HelpCenterAPI.base}${endpoint}?${queryString}`, {
                ...options,
                headers: {
                    'Accept': 'application/json',
                    ...(options.headers || {})
                },
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            
            const text = await response.text();
            if (!response.ok) {
                let errorMessage = `${t('httpError')} ${response.status}`;
                try {
                    const errorData = JSON.parse(text);
                    if (errorData.message) {
                        // Try to translate common API error messages
                        const apiErrorMessages = {
                            'en': {
                                'User not authenticated': 'User not authenticated',
                                'Tutorial ID required': 'Tutorial ID required',
                                'Search query required': 'Search query required',
                                'Invalid rating data': 'Invalid rating data',
                                'Invalid JSON data': 'Invalid data sent. Please try again.',
                                'Database connection failed': 'Database connection failed',
                                'Failed to fetch': 'Failed to fetch',
                                'Method not allowed': 'Method not allowed',
                                'Internal server error': 'Internal server error',
                                'Permission denied': 'Permission denied',
                                'Tutorial not found': 'Tutorial not found',
                                'Invalid action': 'Invalid action'
                            },
                            'bn': {
                                'User not authenticated': 'ব্যবহারকারী প্রমাণীকরণ করা হয়নি',
                                'Tutorial ID required': 'টিউটোরিয়াল ID প্রয়োজন',
                                'Search query required': 'অনুসন্ধান প্রশ্ন প্রয়োজন',
                                'Invalid rating data': 'অবৈধ রেটিং ডেটা',
                                'Invalid JSON data': 'অবৈধ ডেটা পাঠানো হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।',
                                'Database connection failed': 'ডাটাবেস সংযোগ ব্যর্থ হয়েছে',
                                'Failed to fetch': 'আনতে ব্যর্থ হয়েছে',
                                'Method not allowed': 'পদ্ধতি অনুমোদিত নয়',
                                'Internal server error': 'অভ্যন্তরীণ সার্ভার ত্রুটি',
                                'Permission denied': 'অনুমতি প্রত্যাখ্যান করা হয়েছে',
                                'Tutorial not found': 'টিউটোরিয়াল পাওয়া যায়নি',
                                'Invalid action': 'অবৈধ ক্রিয়া'
                            }
                        };
                        const lang = HelpCenterState.currentLanguage;
                        const msg = errorData.message;
                        if (apiErrorMessages[lang] && apiErrorMessages[lang][msg]) {
                            errorMessage = apiErrorMessages[lang][msg];
                        } else if (msg && msg.includes('Failed to')) {
                            // Pick the most appropriate generic message by keyword
                            if (msg.toLowerCase().includes('tutorial') && !msg.toLowerCase().includes('tutorials')) {
                                errorMessage = t('failedToLoadTutorial');
                            } else if (msg.toLowerCase().includes('tutorials') || msg.toLowerCase().includes('categories')) {
                                errorMessage = msg.toLowerCase().includes('categor') ? t('failedToLoadCategories') : t('failedToLoadTutorials');
                            } else if (msg.toLowerCase().includes('search')) {
                                errorMessage = t('failedToSearch');
                            } else if (msg.toLowerCase().includes('rating')) {
                                errorMessage = t('failedToSubmitRating');
                            } else {
                                errorMessage = t('failedToLoadCategories');
                            }
                        } else {
                            errorMessage = msg || errorMessage;
                        }
                    }
                } catch (e) {
                    // Use default error message
                }
                throw new Error(errorMessage);
            }
            
            return JSON.parse(text);
        } catch (error) {
            if (error.name === 'AbortError') {
                console.error('API Request timeout:', endpoint);
                throw new Error(t('requestTimeout'));
            }
            console.error('API Error:', error);
            throw error;
        }
    },

    async getCategories() {
        return this.fetch(HelpCenterAPI.endpoints.categories);
    },

    async getTutorials(filters = {}) {
        const params = {
            action: 'list',
            ...filters
        };
        return this.fetch(HelpCenterAPI.endpoints.tutorials, params);
    },

    async getTutorial(id) {
        return this.fetch(HelpCenterAPI.endpoints.tutorials, {
            action: 'get',
            id: id
        });
    },

    async searchTutorials(query) {
        return this.fetch(HelpCenterAPI.endpoints.search, {
            q: query
        });
    },

    async getProgress() {
        return this.fetch(HelpCenterAPI.endpoints.progress);
    },

    async updateProgress(data) {
        try {
            // Ensure language_code is included in progress data
            const progressData = {
                ...data,
                language_code: data.language_code || HelpCenterState.currentLanguage
            };
            
            const response = await fetch(`${HelpCenterAPI.base}${HelpCenterAPI.endpoints.progress}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(progressData)
            });
            const text = await response.text();
            return JSON.parse(text);
        } catch (error) {
            console.error('Progress Update Error:', error);
            throw error;
        }
    },

    async submitRating(data) {
        try {
            const response = await fetch(`${HelpCenterAPI.base}${HelpCenterAPI.endpoints.ratings}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });
            const text = await response.text();
            return JSON.parse(text);
        } catch (error) {
            console.error('Rating Error:', error);
            throw error;
        }
    }
};

// UI Rendering Functions
const HelpCenterUI = {
    renderCategories(categories) {
        const grid = document.getElementById('categoriesGrid');
        const sidebar = document.getElementById('categoriesList');
        
        if (!grid && !sidebar) return;

        // Flatten categories if they're in a tree structure
        const flattenCategories = (cats) => {
            let flat = [];
            if (Array.isArray(cats)) {
                cats.forEach(cat => {
                    flat.push(cat);
                    if (cat.children && Array.isArray(cat.children) && cat.children.length > 0) {
                        flat = flat.concat(flattenCategories(cat.children));
                    }
                });
            }
            return flat;
        };
        
        const flatCategories = flattenCategories(categories);

        const renderCategory = (category) => {
            const card = document.createElement('a');
            card.className = 'category-card';
            card.href = '#';
            card.dataset.categoryId = category.id;
            
            card.innerHTML = `
                <div class="category-card-icon">
                    <i class="fas ${category.icon || 'fa-circle'}"></i>
                </div>
                <h3 class="category-card-title">${category.name || t('category')}</h3>
                <p class="category-card-description">${category.description || ''}</p>
                <div class="category-card-count">
                    <i class="fas fa-book"></i>
                    ${category.tutorial_count || 0} ${t('tutorialsLabel')}
                </div>
            `;
            
            card.addEventListener('click', (e) => {
                e.preventDefault();
                HelpCenterController.loadTutorialsByCategory(category.id);
            });
            
            return card;
        };

        // Render grid
        if (grid) {
            grid.innerHTML = '';
            if (flatCategories && flatCategories.length > 0) {
                flatCategories.forEach(category => {
                    grid.appendChild(renderCategory(category));
                });
            } else {
                console.warn('No categories to render');
            }
        } else {
            console.error('Categories grid element not found!');
        }

        // Render sidebar
        if (sidebar) {
            sidebar.innerHTML = '';
            const ul = document.createElement('ul');
            ul.className = 'categories-list';
            
            flatCategories.forEach(category => {
                    const li = document.createElement('li');
                    li.className = 'category-item';
                    const link = document.createElement('a');
                    link.className = 'category-link';
                    link.href = '#';
                    link.dataset.categoryId = category.id;
                    link.innerHTML = `
                        <i class="fas ${category.icon || 'fa-circle'}"></i>
                        <span>${category.name || t('category')}</span>
                        <span class="category-count">${category.tutorial_count || 0}</span>
                    `;
                    link.addEventListener('click', (e) => {
                        e.preventDefault();
                        HelpCenterController.loadTutorialsByCategory(category.id);
                    });
                    li.appendChild(link);
                    ul.appendChild(li);
                });
                
            sidebar.appendChild(ul);
        }
    },

    renderTutorials(tutorials) {
        const container = document.getElementById('tutorialList');
        if (!container) return;

        container.innerHTML = '';
        
        // Ensure default grid-view class is applied
        if (!container.classList.contains('grid-view') && !container.classList.contains('list-view')) {
            container.classList.add('grid-view');
        }
        
        if (!tutorials || tutorials.length === 0) {
            container.innerHTML = `<div class="empty-state"><p>${t('noTutorialsFound')}</p></div>`;
            return;
        }

        const colorCount = 12; // Increased from 8 to 12 for more color variety
        const useBn = HelpCenterState.currentLanguage === 'bn' && window.HELP_CENTER_BUILTIN_BN;
        tutorials.forEach((tutorial, index) => {
            var displayTitle = tutorial.title;
            var displayOverview = tutorial.overview;
            if (useBn && tutorial.id && window.HELP_CENTER_BUILTIN_BN[tutorial.id]) {
                var bnT = window.HELP_CENTER_BUILTIN_BN[tutorial.id];
                if (bnT.title) displayTitle = bnT.title;
                if (bnT.overview) displayOverview = bnT.overview;
            }
            const card = document.createElement('a');
            card.className = 'tutorial-card tutorial-card--color-' + (index % colorCount);
            card.href = '#';
            card.dataset.tutorialId = tutorial.id;
            
            const difficultyClass = tutorial.difficulty_level || 'beginner';
            const progress = tutorial.progress || null;
            
            card.innerHTML = `
                <div class="tutorial-card-header">
                    <h3 class="tutorial-card-title">${displayTitle || t('untitledTutorial')}</h3>
                    <span class="tutorial-card-badge ${difficultyClass}">${t(difficultyClass)}</span>
                </div>
                <p class="tutorial-card-overview">${displayOverview || ''}</p>
                <div class="tutorial-card-footer">
                    <div class="tutorial-card-meta">
                        <span><i class="fas fa-clock"></i> ${tutorial.estimated_time || 5} ${t('min')}</span>
                        <span><i class="fas fa-eye"></i> ${tutorial.views_count || 0} ${t('views')}</span>
                    </div>
                    ${progress ? `<div class="tutorial-card-progress">
                        <span>${progress.progress_percentage || 0}%</span>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${progress.progress_percentage || 0}%"></div>
                        </div>
                    </div>` : ''}
                </div>
            `;
            
            card.addEventListener('click', (e) => {
                e.preventDefault();
                HelpCenterController.loadTutorial(tutorial.id);
            });
            
            container.appendChild(card);
        });
    },

    renderTutorialDetail(tutorial) {
        const container = document.getElementById('tutorialDetail');
        if (!container) return;

        const content = tutorial.content || {};
        const videos = tutorial.videos || [];
        const isBuiltIn = String(tutorial.id || '').indexOf('builtin-') === 0;
        const showEnglishNotice = isBuiltIn && HelpCenterState.currentLanguage === 'bn' && !tutorial._contentIsBengali;
        
        // Back button is already in HTML, so we don't need to add it here
        container.innerHTML = `
            ${showEnglishNotice ? `<div class="tutorial-detail-notice content-available-notice" role="status">${t('contentAvailableInEnglish')}</div>` : ''}
            <div class="tutorial-detail-header">
                <h1 class="tutorial-detail-title">${content.title || t('tutorial')}</h1>
                <div class="tutorial-detail-meta">
                    <span><i class="fas fa-clock"></i> ${tutorial.estimated_time || 5} ${t('min')}</span>
                    <span><i class="fas fa-signal"></i> ${t(tutorial.difficulty_level || 'beginner')}</span>
                    <span><i class="fas fa-eye"></i> ${tutorial.views_count || 0} ${t('views')}</span>
                    ${tutorial.updated_at ? `<span><i class="fas fa-calendar-alt"></i> ${t('updated')}: ${formatDate(tutorial.updated_at)}</span>` : ''}
                    ${tutorial.last_updated ? `<span><i class="fas fa-calendar-alt"></i> ${t('updated')}: ${formatDate(tutorial.last_updated)}</span>` : ''}
                </div>
            </div>
            
            ${videos.length > 0 ? `
                <div class="video-section">
                    <div class="video-selector" id="videoSelector">
                        ${videos.map((video, index) => `
                            <button class="video-format-btn ${index === 0 ? 'active' : ''}" 
                                    data-video-type="${video.video_type}" 
                                    data-video-path="${video.video_path}">
                                ${video.video_type === 'vertical' ? t('verticalVideo') : t('horizontalVideo')}
                            </button>
                        `).join('')}
                    </div>
                    <div class="video-container">
                        <video class="video-player" id="tutorialVideoPlayer" controls>
                            <source src="${videos[0].video_path}" type="video/mp4">
                            ${t('videoNotSupported')}
                        </video>
                    </div>
                </div>
            ` : ''}
            
            <div class="tutorial-detail-body">
                ${this.parseContent(content.content || '')}
            </div>
            
            <div class="tutorial-ratings" id="tutorialRatings">
                <h3>${t('wasThisHelpful')}</h3>
                <div class="rating-form">
                    <div class="rating-stars" id="ratingStars">
                        ${[1, 2, 3, 4, 5].map(i => `<i class="fas fa-star rating-star" data-rating="${i}"></i>`).join('')}
                    </div>
                    <button class="btn btn-primary" id="submitRatingBtn">${t('submitRating')}</button>
                </div>
            </div>
        `;

        // Setup video selector
        const videoSelector = document.getElementById('videoSelector');
        if (videoSelector) {
            videoSelector.addEventListener('click', (e) => {
                if (e.target.classList.contains('video-format-btn')) {
                    document.querySelectorAll('.video-format-btn').forEach(btn => btn.classList.remove('active'));
                    e.target.classList.add('active');
                    const videoPath = e.target.dataset.videoPath;
                    const videoPlayer = document.getElementById('tutorialVideoPlayer');
                    if (videoPlayer) {
                        videoPlayer.src = videoPath;
                        videoPlayer.load();
                    }
                }
            });
        }

        // Setup rating
        this.setupRating(tutorial.id);
    },

    parseContent(content) {
        if (typeof content === 'string') {
            return content;
        }
        if (typeof content === 'object') {
            // Handle structured content
            let html = '';
            if (content.overview) {
                html += `<div class="content-section"><h2>${t('overview')}</h2><p>${content.overview}</p></div>`;
            }
            if (content.steps && Array.isArray(content.steps)) {
                html += `<div class="content-section"><h2>${t('steps')}</h2><ol>`;
                content.steps.forEach(step => {
                    html += `<li>${step}</li>`;
                });
                html += `</ol></div>`;
            }
            return html;
        }
        return '';
    },

    setupRating(tutorialId) {
        const ratingStars = document.getElementById('ratingStars');
        const submitBtn = document.getElementById('submitRatingBtn');
        let selectedRating = 0;
        const isBuiltin = tutorialId && String(tutorialId).indexOf('builtin-') === 0;

        if (ratingStars) {
            ratingStars.addEventListener('click', (e) => {
                if (e.target.classList.contains('rating-star')) {
                    const rating = parseInt(e.target.dataset.rating);
                    selectedRating = rating;
                    document.querySelectorAll('.rating-star').forEach((star, index) => {
                        if (index < rating) {
                            star.classList.add('active');
                        } else {
                            star.classList.remove('active');
                        }
                    });
                }
            });
        }

        if (submitBtn) {
            submitBtn.addEventListener('click', async () => {
                if (selectedRating === 0) {
                    showErrorMessage(t('pleaseSelectRating'));
                    return;
                }
                if (isBuiltin) {
                    showSuccessMessage(t('thanksForFeedback'));
                    return;
                }
                try {
                    await HelpCenterAPIHandler.submitRating({
                        tutorial_id: tutorialId,
                        rating: selectedRating,
                        language_code: HelpCenterState.currentLanguage
                    });
                    showSuccessMessage(t('ratingSubmitted'));
                } catch (error) {
                    showErrorMessage(error.message || t('failedToSubmitRating'));
                }
            });
        }
    },

    renderSearchResults(results) {
        const container = document.getElementById('searchResults');
        const countElement = document.getElementById('searchResultsCount');
        
        if (countElement) {
            const count = results.length || 0;
            countElement.innerHTML = `<span class="results-count-number">${count}</span> <span data-translate="results">${t('results')}</span>`;
        }
        
        if (!container) return;

        container.innerHTML = '';
        
        if (!results || results.length === 0) {
            container.innerHTML = `<div class="empty-state"><p>${t('noSearchResults')}</p></div>`;
            return;
        }

        const colorCount = 12; // Increased from 8 to 12 for more color variety
        results.forEach((tutorial, index) => {
            const card = document.createElement('a');
            card.className = 'tutorial-card tutorial-card--color-' + (index % colorCount);
            card.href = '#';
            card.dataset.tutorialId = tutorial.id;
            
            card.innerHTML = `
                <div class="tutorial-card-header">
                    <h3 class="tutorial-card-title">${tutorial.title || t('untitledTutorial')}</h3>
                </div>
                <p class="tutorial-card-overview">${tutorial.overview || ''}</p>
            `;
            
            card.addEventListener('click', (e) => {
                e.preventDefault();
                HelpCenterController.loadTutorial(tutorial.id);
            });
            
            container.appendChild(card);
        });
    },

    updateBreadcrumbs(items) {
        const breadcrumbs = document.getElementById('helpBreadcrumbs');
        if (!breadcrumbs) return;

        breadcrumbs.innerHTML = '';
        items.forEach((item, index) => {
            if (index > 0) {
                breadcrumbs.appendChild(document.createTextNode(' / '));
            }
            if (item.link) {
                const link = document.createElement('a');
                link.className = 'breadcrumb-link';
                link.href = '#';
                link.textContent = item.text;
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (item.action) {
                        HelpCenterController[item.action]();
                    }
                });
                breadcrumbs.appendChild(link);
            } else {
                const span = document.createElement('span');
                span.className = 'breadcrumb-current';
                span.textContent = item.text;
                breadcrumbs.appendChild(span);
            }
        });
    }
};

// Controller
const HelpCenterController = {
    async init() {
        // Initialize language
        await this.initLanguage();
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Load initial data
        await this.loadCategories();

        // Deep link from chat widget or bookmarks: help-center.php?tutorial=builtin-6-0
        try {
            const tutorialParam = new URLSearchParams(window.location.search).get('tutorial');
            if (tutorialParam && String(tutorialParam).trim()) {
                await this.loadTutorial(String(tutorialParam).trim());
            }
        } catch (e) {
            /* ignore */
        }
    },

    async initLanguage() {
        const langSwitcher = document.getElementById('helpLanguageSwitcher');
        if (langSwitcher) {
            const bnOption = langSwitcher.querySelector('option[value="bn"]');
            if (bnOption) bnOption.remove();
        }
        try {
            // Get current language from API
            const response = await fetch(`${HelpCenterAPI.base}/languages.php?action=current`);
            const data = await response.json();
            if (data.success && data.data) {
                let lang = 'en';
                HelpCenterState.currentLanguage = lang;
                if (window.HelpCenterTranslations) {
                    window.HelpCenterTranslations.setLanguage(lang);
                }
                if (langSwitcher) {
                    langSwitcher.value = lang;
                }
                if (typeof translateUI === 'function') {
                    translateUI(lang);
                }
            }
        } catch (error) {
            console.error('Failed to load language:', error);
            HelpCenterState.currentLanguage = 'en';
            if (typeof translateUI === 'function') {
                translateUI('en');
            }
        }
    },

    handleBackButton(action) {
        if (action === 'backToCategories') {
            // Go back to categories
            this.loadCategories();
        } else if (action === 'backFromDetail') {
            // Go back from tutorial detail to tutorial list or categories
            if (HelpCenterState.currentCategory) {
                this.loadTutorialsByCategory(HelpCenterState.currentCategory);
            } else {
                this.loadCategories();
            }
        }
    },

    async switchLanguage(langCode) {
        try {
            langCode = 'en';
            HelpCenterState.currentLanguage = 'en';
            
            // Update session via API
            await fetch(`${HelpCenterAPI.base}/languages.php?action=switch&lang=${langCode}`, {
                method: 'GET'
            });
            
            // Update translations
            if (window.HelpCenterTranslations) {
                window.HelpCenterTranslations.setLanguage(langCode);
            }
            
            // Reload current view with new language (so all content updates, not just title/subtitle)
            if (HelpCenterState.currentCategory) {
                await this.loadTutorialsByCategory(HelpCenterState.currentCategory);
            } else if (HelpCenterState.currentTutorial) {
                await this.loadTutorial(HelpCenterState.currentTutorial.id);
            } else if (HelpCenterState.searchQuery) {
                await this.searchTutorials(HelpCenterState.searchQuery);
            } else {
                await this.loadCategories();
            }
        } catch (error) {
            console.error('Failed to switch language:', error);
            showErrorMessage(t('failedToSwitchLanguage'));
        }
    },

    setupEventListeners() {
        // Search
        const searchInput = document.getElementById('helpSearchInput');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                const query = e.target.value.trim();
                if (query.length >= 2) {
                    searchTimeout = setTimeout(() => {
                        this.searchTutorials(query);
                    }, 300);
                } else if (query.length === 0) {
                    this.loadCategories();
                }
            });
        }

        // Back buttons
        document.addEventListener('click', (e) => {
            const backButton = e.target.closest('.help-back-button');
            if (backButton) {
                e.preventDefault();
                const action = backButton.getAttribute('data-action');
                this.handleBackButton(action);
            }
        });

        // View toggle buttons (Grid/List)
        document.addEventListener('click', (e) => {
            if (e.target.closest('.view-toggle')) {
                const button = e.target.closest('.view-toggle');
                const viewType = button.dataset.view;
                if (viewType === 'grid' || viewType === 'list') {
                    this.toggleView(viewType);
                }
            }
        });

        // Sidebar toggle (mobile)
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('helpSidebar');
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });
        }

        // Language switcher
        const langSwitcher = document.getElementById('helpLanguageSwitcher');
        if (langSwitcher) {
            langSwitcher.addEventListener('change', (e) => {
                this.switchLanguage(e.target.value);
            });
        }
    },

    toggleView(viewType) {
        const tutorialList = document.getElementById('tutorialList');
        const viewToggles = document.querySelectorAll('.view-toggle');
        
        if (!tutorialList) return;
        
        // Update button active states
        viewToggles.forEach(btn => {
            if (btn.dataset.view === viewType) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
        
        // Update tutorial list class
        if (viewType === 'grid') {
            tutorialList.classList.remove('list-view');
            tutorialList.classList.add('grid-view');
        } else {
            tutorialList.classList.remove('grid-view');
            tutorialList.classList.add('list-view');
        }
    },

    async loadCategories() {
        try {
            showLoading(true);
            const response = await HelpCenterAPIHandler.getCategories();
            if (response.success && response.data) {
                HelpCenterState.categories = response.data;
                HelpCenterUI.renderCategories(response.data);
                showView('categoryGridView');
                HelpCenterUI.updateBreadcrumbs([
                    { text: t('home'), link: true, action: 'loadCategories' },
                    { text: t('allCategories'), link: false }
                ]);
            }
            if (typeof translateUI === 'function') {
                translateUI(HelpCenterState.currentLanguage);
            }
        } catch (error) {
            console.error('Failed to load categories:', error);
        } finally {
            showLoading(false);
        }
    },

    async loadTutorialsByCategory(categoryId) {
        try {
            showLoading(true);
            HelpCenterState.currentCategory = categoryId;
            const response = await HelpCenterAPIHandler.getTutorials({
                category_id: categoryId
            });
            let tutorials = (response && response.success && response.data) ? (response.data.tutorials || []) : [];
            const builtinPack = helpCenterBuiltinTutorialsForCategory(categoryId);
            if (tutorials.length === 0 && builtinPack) {
                tutorials = builtinPack;
            }
            HelpCenterState.tutorials = tutorials;
            HelpCenterUI.renderTutorials(tutorials);
            showView('tutorialListView');
            const category = helpCenterFindCategory(HelpCenterState.categories, categoryId);
            HelpCenterUI.updateBreadcrumbs([
                { text: t('home'), link: true, action: 'loadCategories' },
                { text: category?.name || t('category'), link: false }
            ]);
            if (typeof translateUI === 'function') {
                translateUI(HelpCenterState.currentLanguage);
            }
        } catch (error) {
            console.error('Failed to load tutorials:', error);
            var fallback = HelpCenterState.currentCategory ? (helpCenterBuiltinTutorialsForCategory(HelpCenterState.currentCategory) || []) : [];
            HelpCenterState.tutorials = fallback;
            HelpCenterUI.renderTutorials(fallback);
            showView('tutorialListView');
            HelpCenterUI.updateBreadcrumbs([
                { text: t('home'), link: true, action: 'loadCategories' },
                { text: t('category'), link: false }
            ]);
        } finally {
            showLoading(false);
        }
    },

    async loadTutorial(tutorialId) {
        try {
            showLoading(true);
            if (String(tutorialId).indexOf('builtin-') === 0) {
                var tutorial = HelpCenterState.tutorials.find(function(x) { return x.id === tutorialId; });
                if (!tutorial && window.HELP_CENTER_BUILTIN) {
                    var H = window.HELP_CENTER_BUILTIN;
                    for (var cid in H) {
                        if (!Object.prototype.hasOwnProperty.call(H, cid)) continue;
                        var arr = H[cid];
                        if (!Array.isArray(arr)) continue;
                        for (var i = 0; i < arr.length; i++) {
                            if (arr[i] && arr[i].id === tutorialId) {
                                tutorial = arr[i];
                                HelpCenterState.currentCategory = cid;
                                break;
                            }
                        }
                        if (tutorial) break;
                    }
                }
                if (tutorial) {
                    var title = tutorial.title;
                    var overview = tutorial.overview;
                    var bodyContent = tutorial.content;
                    var usedBengaliContent = false;
                    if (HelpCenterState.currentLanguage === 'bn' && window.HELP_CENTER_BUILTIN_BN && window.HELP_CENTER_BUILTIN_BN[tutorialId]) {
                        var bn = window.HELP_CENTER_BUILTIN_BN[tutorialId];
                        if (bn.title) title = bn.title;
                        if (bn.overview) overview = bn.overview;
                        if (bn.content) { bodyContent = bn.content; usedBengaliContent = true; }
                    }
                    HelpCenterState.currentTutorial = { id: tutorialId, content: { title: title, content: bodyContent }, estimated_time: tutorial.estimated_time || 5, difficulty_level: tutorial.difficulty_level || 'beginner', views_count: tutorial.views_count || 0, videos: [], _contentIsBengali: usedBengaliContent };
                    HelpCenterUI.renderTutorialDetail(HelpCenterState.currentTutorial);
                    showView('tutorialDetailView');
                    HelpCenterUI.updateBreadcrumbs([
                        { text: t('home'), link: true, action: 'loadCategories' },
                        { text: t('tutorial'), link: false },
                        { text: tutorial.title || t('tutorial'), link: false }
                    ]);
                }
                if (typeof translateUI === 'function') {
                    translateUI(HelpCenterState.currentLanguage);
                }
                showLoading(false);
                return;
            }
            const response = await HelpCenterAPIHandler.getTutorial(tutorialId);
            if (response.success && response.data) {
                HelpCenterState.currentTutorial = response.data;
                HelpCenterUI.renderTutorialDetail(response.data);
                showView('tutorialDetailView');
                const tutorial = response.data;
            HelpCenterUI.updateBreadcrumbs([
                { text: t('home'), link: true, action: 'loadCategories' },
                { text: t('tutorial'), link: false },
                { text: tutorial.content?.title || t('tutorial'), link: false }
            ]);
            }
            if (typeof translateUI === 'function') {
                translateUI(HelpCenterState.currentLanguage);
            }
        } catch (error) {
            console.error('Failed to load tutorial:', error);
        } finally {
            showLoading(false);
        }
    },

    async searchTutorials(query) {
        try {
            showLoading(true);
            HelpCenterState.searchQuery = query;
            const response = await HelpCenterAPIHandler.searchTutorials(query);
            if (response.success && response.data) {
                HelpCenterUI.renderSearchResults(response.data.results || []);
                showView('searchResultsView');
                HelpCenterUI.updateBreadcrumbs([
                    { text: t('home'), link: true, action: 'loadCategories' },
                    { text: `${t('searchPrefix')} ${query}`, link: false }
                ]);
            }
            if (typeof translateUI === 'function') {
                translateUI(HelpCenterState.currentLanguage);
            }
        } catch (error) {
            console.error('Failed to search tutorials:', error);
        } finally {
            showLoading(false);
        }
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    HelpCenterController.init();
});
