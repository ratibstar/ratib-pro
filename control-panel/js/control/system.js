/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/control/system.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/control/system.js`.
 */
/**
 * Control Panel System - Unified JavaScript
 */
(function() {
    'use strict';

    function init() {
        // Sidebar toggle
        const sidebar = document.getElementById('control-sidebar');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebarToggleMobile = document.getElementById('sidebar-toggle-mobile');

        function applyMobileSidebarDefaultState() {
            if (!sidebar) return;
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
            } else {
                sidebar.classList.remove('collapsed');
            }
        }
        
        function toggleSidebar() {
            if (sidebar) {
                sidebar.classList.toggle('collapsed');
            }
        }
        
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
        }
        
        if (sidebarToggleMobile) {
            sidebarToggleMobile.addEventListener('click', toggleSidebar);
        }

        // Global mobile behavior: show page content first, not sidebar overlay.
        applyMobileSidebarDefaultState();
        window.addEventListener('resize', applyMobileSidebarDefaultState);
        
        // Close sidebar on mobile when clicking outside
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768 && sidebar) {
                if (!sidebar.contains(e.target) && 
                    sidebarToggle && !sidebarToggle.contains(e.target) && 
                    sidebarToggleMobile && !sidebarToggleMobile.contains(e.target) && 
                    !sidebar.classList.contains('collapsed')) {
                    sidebar.classList.add('collapsed');
                }
            }
        });

        // On mobile, collapse sidebar after selecting a menu item.
        if (sidebar) {
            sidebar.addEventListener('click', function (e) {
                if (window.innerWidth > 768) return;
                var link = e.target.closest && e.target.closest('a.sidebar-item, a.sidebar-subitem');
                if (!link) return;
                sidebar.classList.add('collapsed');
            });
        }
        
        // Sidebar links use native href - no JS override to avoid navigation issues
        initSidebarCollapsibles();
        syncSidebarActiveState();
        
        // Load recent registration requests if on dashboard
        if (document.getElementById('recent-requests')) {
            loadRecentRequests();
        }

        // Select Country cards: open in new tab
        document.addEventListener('click', function(e) {
            var card = e.target.closest && e.target.closest('a.country-card');
            if (!card) return;
            var href = card.href || card.getAttribute('href');
            if (href && href.indexOf('#') !== 0 && href.indexOf('javascript:') !== 0) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                window.open(href, '_blank', 'noopener,noreferrer');
            }
        }, true);
    }

    function syncSidebarActiveState() {
        const sidebar = document.getElementById('control-sidebar');
        if (!sidebar) return;

        const links = Array.from(sidebar.querySelectorAll('a.sidebar-item[href], a.sidebar-subitem[href]'));
        if (links.length === 0) return;

        const currentUrl = new URL(window.location.href);
        const current = normalizePath(currentUrl.pathname || '/');
        let bestMatch = null;

        links.forEach(link => {
            const href = link.getAttribute('href') || '';
            if (!href || href.startsWith('javascript:') || href.startsWith('#')) return;

            let parsed = null;
            let targetPath = '';
            try {
                parsed = new URL(href, window.location.origin);
                if (parsed.origin !== window.location.origin) return;
                targetPath = normalizePath(parsed.pathname);
            } catch (_) {
                return;
            }

            if (!targetPath) return;
            if (!(current === targetPath || current.endsWith(targetPath) || targetPath.endsWith(current))) {
                return;
            }

            const params = Array.from(parsed.searchParams.entries());
            const paramScore = params.reduce((score, pair) => {
                const key = pair[0];
                const value = pair[1];
                return currentUrl.searchParams.get(key) === value ? score + 1 : score - 10;
            }, 0);

            if (paramScore < 0) {
                return;
            }

            const totalScore = (targetPath.length * 10) + paramScore;
            if (!bestMatch || totalScore > bestMatch.score) {
                bestMatch = { link, score: totalScore };
            }
        });

        if (bestMatch) {
            links.forEach(l => l.classList.remove('active'));
            bestMatch.link.classList.add('active');
            const wrapper = bestMatch.link.closest('.sidebar-collapsible');
            if (wrapper) {
                wrapper.classList.add('is-open');
                const toggle = wrapper.querySelector('[data-sidebar-toggle]');
                const panel = wrapper.querySelector('[data-sidebar-panel]');
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'true');
                }
                if (panel) {
                    panel.hidden = false;
                }
            }
        }
    }

    function initSidebarCollapsibles() {
        const toggles = Array.from(document.querySelectorAll('[data-sidebar-toggle]'));
        if (toggles.length === 0) return;

        toggles.forEach(toggle => {
            const key = toggle.getAttribute('data-sidebar-toggle');
            const panel = key ? document.querySelector('[data-sidebar-panel="' + key + '"]') : null;
            const wrapper = toggle.closest('.sidebar-collapsible');
            if (!panel || !wrapper) return;

            toggle.addEventListener('click', function () {
                const isOpen = wrapper.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                panel.hidden = !isOpen;
            });
        });
    }

    function normalizePath(path) {
        if (!path) return '/';
        let normalized = path.toLowerCase();
        normalized = normalized.replace(/\.php$/i, '');
        normalized = normalized.replace(/\/+$/, '');
        if (normalized === '') normalized = '/';
        return normalized;
    }
    
    function loadRecentRequests() {
        const container = document.getElementById('recent-requests');
        if (!container) return;
        
        const configEl = document.getElementById('control-config');
        const apiBase = configEl ? configEl.dataset.apiBase : '/api/control';
        
        fetch(`${apiBase}/registration-requests.php?limit=5&control=1`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.list && data.list.length > 0) {
                    container.innerHTML = data.list.map(req => `
                        <div class="recent-item">
                            <div class="recent-item-info">
                                <div class="recent-item-title">${escapeHtml(req.agency_name || 'N/A')}</div>
                                <div class="recent-item-meta">
                                    ${req.contact_email || ''} • ${formatDate(req.created_at)}
                                    ${req.country_name ? ' • ' + escapeHtml(req.country_name) : ''}
                                </div>
                            </div>
                            <span class="recent-item-status ${req.status || 'pending'}">
                                ${(req.status || 'pending').charAt(0).toUpperCase() + (req.status || 'pending').slice(1)}
                            </span>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = '<div class="loading-state"><i class="fas fa-inbox"></i><p>No recent requests</p></div>';
                }
            })
            .catch(error => {
                console.error('Error loading recent requests:', error);
                container.innerHTML = '<div class="loading-state"><i class="fas fa-exclamation-triangle"></i><p>Failed to load recent requests</p></div>';
            });
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        if (isNaN(date)) return dateString;
        return date.toLocaleDateString('en-GB', { 
            day: '2-digit', 
            month: 'short', 
            year: 'numeric' 
        });
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
