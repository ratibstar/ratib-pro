/**
 * EN: Implements frontend interaction behavior in `js/contextual-help.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/contextual-help.js`.
 */
/**
 * Contextual Help System - Clickable Explanations
 * Provides detailed explanations for system features and elements
 */

class ContextualHelp {
    constructor() {
        this.explanations = {};
        this.activeExplanation = null;
        this.init();
    }

    init() {
        // Load explanations data
        this.loadExplanations();
        
        // Create explanation modal/overlay
        this.createExplanationUI();
        
        // Setup event listeners
        this.setupEventListeners();
    }

    /**
     * Load explanations data
     */
    loadExplanations() {
        this.explanations = {
            // Dashboard
            'dashboard-overview': {
                title: 'Dashboard Overview',
                content: 'The dashboard provides a comprehensive overview of your business operations. Here you can see key metrics, recent activity, quick stats, and access important features quickly. Use the summary cards to monitor workers, agents, and financial information at a glance.',
                category: 'Dashboard',
                image: 'assets/images/help/dashboard-overview.png',
                related: ['dashboard-stats', 'dashboard-activity']
            },
            
            // Agent Management
            'agent-management': {
                title: 'Agent Management',
                content: 'Manage your agents and their information. Agents are key partners in your workforce management system. You can add new agents, view agent details, manage contracts, track performance, and handle all agent-related operations from this module.',
                category: 'Agent Management',
                image: 'assets/images/help/agent-management.png',
                steps: [
                    'Click "Add New Agent" to create a new agent profile',
                    'Fill in agent information including name, contact details, and business information',
                    'Set up contracts and agreements with agents',
                    'Track agent performance and relationships'
                ],
                tips: [
                    'Keep agent contact information up to date',
                    'Regularly review agent contracts and agreements',
                    'Use filters and search to quickly find specific agents'
                ]
            },
            
            // Worker Management
            'worker-management': {
                title: 'Worker Management',
                content: 'Manage your workforce effectively with comprehensive worker profiles. Track worker information, documents, status, assignments, and all worker-related data. This is the core module for managing your workers throughout their employment lifecycle.',
                category: 'Worker Management',
                image: 'assets/images/help/worker-management.png',
                steps: [
                    'Add workers with complete profile information',
                    'Upload required documents (ID, passport, contracts)',
                    'Track worker status and assignments',
                    'Manage worker contracts and renewals',
                    'View worker history and activity'
                ]
            },
            
            // Accounting
            'accounting-system': {
                title: 'Accounting System',
                content: 'Professional double-entry accounting system for comprehensive financial management. Manage chart of accounts, journal entries, invoices, payments, receipts, and generate financial reports. This system supports multi-currency transactions and integrates with all business modules.',
                category: 'Finance & Accounting',
                image: 'assets/images/help/accounting-system.png',
                important: [
                    'Every transaction uses double-entry bookkeeping',
                    'Multiple currencies are supported (SAR, USD, EUR, GBP, JOD)',
                    'All transactions are linked to agents, workers, and HR entities'
                ]
            },
            
            // Help Center
            'help-center': {
                title: 'Help & Learning Center',
                content: 'Your comprehensive guide to using the system. Browse tutorials, search for help topics, track your learning progress, and get detailed explanations for all system features. Click on any help icon throughout the system to get contextual explanations.',
                category: 'Help & Learning',
                image: 'assets/images/help/help-center.png',
                tips: [
                    'Use the search bar to quickly find specific topics',
                    'Browse categories to explore different modules',
                    'Track your progress as you learn the system',
                    'Rate tutorials to help others find useful content'
                ]
            },
            
            // User Management
            'user-management': {
                title: 'User Management & Permissions',
                content: 'Manage system users, roles, and permissions. Control who can access which features and maintain security through role-based access control. Assign roles, set permissions, and manage user accounts.',
                category: 'Administration',
                important: [
                    'Roles define what users can access',
                    'Permissions can be customized per user',
                    'Regularly review user access for security'
                ]
            },
            
            // Reports
            'reports-analytics': {
                title: 'Reports & Analytics',
                content: 'Generate comprehensive reports to track performance, analyze data, and make informed business decisions. Access financial reports, worker reports, agent reports, HR reports, and custom analytics.',
                category: 'Reports',
                tips: [
                    'Use filters to customize report data',
                    'Export reports for external analysis',
                    'Schedule regular reports for monitoring'
                ]
            },
            
            // Notifications
            'notifications': {
                title: 'Notifications System',
                content: 'Stay informed with system notifications. Receive alerts for important events, document expirations, payment reminders, contract renewals, and system updates. Configure notification preferences to control what you receive.',
                category: 'Notifications',
                tips: [
                    'Check notifications regularly for important updates',
                    'Configure notification preferences',
                    'Set up automated alerts for critical events'
                ]
            }
        };
    }

    /**
     * Create explanation UI elements
     */
    createExplanationUI() {
        // Create explanation modal overlay
        const overlay = document.createElement('div');
        overlay.id = 'contextualHelpOverlay';
        overlay.className = 'contextual-help-overlay';
        overlay.innerHTML = `
            <div class="contextual-help-modal">
                <div class="contextual-help-header">
                    <h3 class="contextual-help-title"></h3>
                    <button class="contextual-help-close" id="contextualHelpClose">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="contextual-help-body">
                    <div class="contextual-help-image"></div>
                    <div class="contextual-help-content"></div>
                    <div class="contextual-help-steps"></div>
                    <div class="contextual-help-tips"></div>
                    <div class="contextual-help-important"></div>
                </div>
                <div class="contextual-help-footer">
                    <button class="btn-help-more" id="contextualHelpMore">Learn More in Help Center</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Close button
        document.addEventListener('click', (e) => {
            if (e.target.closest('#contextualHelpClose') || e.target.closest('.contextual-help-overlay')) {
                if (e.target.closest('.contextual-help-overlay') && !e.target.closest('.contextual-help-modal')) {
                    this.hideExplanation();
                } else if (e.target.closest('#contextualHelpClose')) {
                    this.hideExplanation();
                }
            }
            
            // Handle clickable help icons
            if (e.target.closest('[data-help-id]')) {
                const helpId = e.target.closest('[data-help-id]').dataset.helpId;
                this.showExplanation(helpId);
            }
        });

        // Learn more button
        document.addEventListener('click', (e) => {
            if (e.target.closest('#contextualHelpMore')) {
                const helpId = this.activeExplanation;
                if (helpId && this.explanations[helpId]) {
                    const category = this.explanations[helpId].category;
                    window.location.href = 'help-center.php?category=' + encodeURIComponent(category);
                }
            }
        });

        // ESC key to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.activeExplanation) {
                this.hideExplanation();
            }
        });
    }

    /**
     * Show explanation
     */
    showExplanation(helpId) {
        const explanation = this.explanations[helpId];
        if (!explanation) {
            console.warn('Explanation not found:', helpId);
            return;
        }

        this.activeExplanation = helpId;
        const overlay = document.getElementById('contextualHelpOverlay');
        const modal = overlay.querySelector('.contextual-help-modal');
        const title = modal.querySelector('.contextual-help-title');
        const content = modal.querySelector('.contextual-help-content');
        const steps = modal.querySelector('.contextual-help-steps');
        const tips = modal.querySelector('.contextual-help-tips');
        const important = modal.querySelector('.contextual-help-important');

        // Set title
        title.textContent = explanation.title;

        // Set main content
        content.innerHTML = `<p>${explanation.content}</p>`;

        // Show steps if available
        if (explanation.steps && explanation.steps.length > 0) {
            steps.classList.add('contextual-help-visible');
            steps.innerHTML = `
                <h4><i class="fas fa-list-ol"></i> Steps:</h4>
                <ol>
                    ${explanation.steps.map(step => `<li>${step}</li>`).join('')}
                </ol>
            `;
        } else {
            steps.style.display = 'none';
        }

        // Show tips if available
        if (explanation.tips && explanation.tips.length > 0) {
            tips.style.display = 'block';
            tips.innerHTML = `
                <h4><i class="fas fa-lightbulb"></i> Tips:</h4>
                <ul>
                    ${explanation.tips.map(tip => `<li>${tip}</li>`).join('')}
                </ul>
            `;
        } else {
            tips.classList.remove('contextual-help-visible');
        }

        // Show important notes if available
        if (explanation.important && explanation.important.length > 0) {
            important.classList.add('contextual-help-visible');
            important.innerHTML = `
                <h4><i class="fas fa-exclamation-triangle"></i> Important:</h4>
                <ul>
                    ${explanation.important.map(note => `<li>${note}</li>`).join('')}
                </ul>
            `;
        } else {
            important.classList.remove('contextual-help-visible');
        }

        // Show overlay
        overlay.classList.add('active');
        document.body.classList.add('contextual-help-open');
    }

    /**
     * Hide explanation
     */
    hideExplanation() {
        const overlay = document.getElementById('contextualHelpOverlay');
        overlay.classList.remove('active');
        document.body.classList.remove('contextual-help-open');
        this.activeExplanation = null;
    }

    /**
     * Add explanation data dynamically
     */
    addExplanation(id, data) {
        this.explanations[id] = data;
    }

    /**
     * Get explanation data
     */
    getExplanation(id) {
        return this.explanations[id];
    }
}

// Initialize contextual help system
let contextualHelp;
document.addEventListener('DOMContentLoaded', () => {
    contextualHelp = new ContextualHelp();
    window.contextualHelp = contextualHelp; // Make it globally accessible
});

/**
 * Helper function to add help icon to elements
 */
function addHelpIcon(element, helpId, position = 'after') {
    if (!element || !helpId) return;
    
    const helpIcon = document.createElement('span');
    helpIcon.className = 'help-icon';
    helpIcon.setAttribute('data-help-id', helpId);
    helpIcon.innerHTML = '<i class="fas fa-question-circle"></i>';
    helpIcon.title = 'Click for explanation';
    
    if (position === 'after') {
        element.appendChild(helpIcon);
    } else if (position === 'before') {
        element.insertBefore(helpIcon, element.firstChild);
    }
    
    return helpIcon;
}
