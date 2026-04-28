/**
 * EN: Implements frontend interaction behavior in `js/modern-forms.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/modern-forms.js`.
 */
// Modern Forms System - Complete CRUD functionality
function getApiBaseModernForms() {
    const base = (window.APP_CONFIG && window.APP_CONFIG.apiBase) || window.API_BASE;
    if (typeof base === 'string' && base.length) return base.replace(/\/$/, '');
    const path = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || window.BASE_PATH || '';
    return (path ? String(path).replace(/\/$/, '') + '/api' : '/api');
}
function getSettingsApiPathModernForms() {
    const base = getApiBaseModernForms();
    const el = document.getElementById('app-config');
    const isControl = el && el.getAttribute('data-control') === '1';
    return base + (isControl ? '/control/settings-api.php' : '/settings/settings-api.php');
}
function isControlModernForms() {
    const el = document.getElementById('app-config');
    return !!(el && el.getAttribute('data-control') === '1');
}
function getControlSuffixModernForms() {
    return isControlModernForms() ? '&control=1' : '';
}

// Sub-options for "Role / category detail" (stored in description) per visa type — keys must match Visa Type dropdown values exactly
const VISA_SUBTYPE_OCCUPATIONS = ['Nurse / Midwife', 'Doctor / Physician', 'Dentist', 'Pharmacist', 'Housemaid / Domestic helper', 'Nanny / Childcare', 'Driver / Chauffeur', 'Cook / Chef', 'Engineer', 'Accountant', 'Teacher / Instructor', 'Laborer / General worker', 'Electrician / Technician', 'Plumber / HVAC', 'Mechanic', 'Sales / Retail', 'IT / Software', 'HR / Administration', 'Security / Guard', 'Beautician / Salon', 'Agriculture / Farm', 'Warehouse / Logistics', 'Construction worker', 'Hotel / Housekeeping', 'Other occupation'];
const VISA_SUBTYPE_CREW = ['Master / Captain', 'Deck officer', 'Engine officer', 'Rating / Able seafarer', 'Maritime cook', 'Steward / Hospitality (ship)', 'Other maritime'];
const VISA_SUBTYPE_STUDENT = ['Undergraduate', 'Masters degree', 'PhD / Doctorate', 'Diploma / Certificate course', 'Foundation / Pathway', 'Exchange semester', 'Language school student'];
const VISA_SUBTYPE_FAMILY = ['Spouse / Partner', 'Child', 'Parent', 'Sibling', 'Extended family', 'Other dependent'];
const VISA_SUBTYPE_BUSINESS = ['Short business meeting', 'Contract / negotiation', 'Market research', 'Training delivery', 'Sales visit', 'Site inspection', 'After-sales support', 'Other business purpose'];
const VISA_SUBTYPE_TOURISM = ['Leisure / Holiday', 'Visiting friends or family', 'Sightseeing', 'Medical wellness trip', 'Sports / Event attendance', 'Other visit purpose'];
const VISA_SUBTYPE_INVESTOR = ['New business setup', 'Existing business investment', 'Real estate investment', 'Startup / Innovation', 'Partner in firm', 'Other investor activity'];
const VISA_SUBTYPE_MEDICAL = ['Planned treatment', 'Emergency treatment', 'Consultation / Check-up', 'Rehabilitation', 'Accompanying patient', 'Other medical'];
const VISA_SUBTYPE_RELIGIOUS = ['Pilgrimage', 'Religious worker', 'Mission / Outreach', 'Study (seminary)', 'Other religious'];
const VISA_SUBTYPE_ARTS = ['Musician / Singer', 'Actor / Performer', 'Dancer', 'Film / TV production', 'Visual artist', 'Technical crew (show)', 'Other entertainment'];
const VISA_SUBTYPE_SPORTS = ['Professional athlete', 'Coach / Trainer', 'Sports official', 'Training camp', 'Competition support', 'Other sports'];
const VISA_SUBTYPE_NGO = ['Volunteer (unpaid)', 'Paid NGO staff', 'Disaster relief', 'Community development', 'Other NGO'];
const VISA_SUBTYPE_DIPLO = ['Diplomatic passport', 'Official passport (government)', 'Consular post', 'International assignment', 'Other official'];
const VISA_SUBTYPE_HUMANITARIAN = ['Refugee / Asylum-related', 'Temporary protection', 'Family reunification (humanitarian)', 'Other protection'];

const VISA_SUBTYPE_CHOICES_BY_TYPE = {
    'Tourist / Visit Visa': [...VISA_SUBTYPE_TOURISM],
    'eVisa / ETA (Electronic)': [...VISA_SUBTYPE_TOURISM, 'Airport entry (electronic authorization)'],
    'Visa on Arrival': ['Tourism / Leisure', 'Business (short)', 'Emergency entry', 'Other VOA purpose'],
    'Single Entry Visit': [...VISA_SUBTYPE_TOURISM],
    'Multiple Entry Visit': ['Frequent business visitor', 'Frequent family visits', 'Tourism (multi-entry)', 'Mixed purpose'],
    'Business Visa': [...VISA_SUBTYPE_BUSINESS],
    'Conference / Exhibition': ['Attendee', 'Speaker / Presenter', 'Exhibitor / Booth staff', 'Organizer staff', 'Media (event)', 'Other event role'],
    'Work Visa / Employment': [...VISA_SUBTYPE_OCCUPATIONS],
    'Temporary Work / Seasonal': ['Seasonal agriculture', 'Seasonal tourism / Hospitality', 'Event / Festival staff', 'Short project staff', 'Other seasonal'],
    'Skilled Worker / Professional': ['Healthcare professional', 'Engineer / Architect', 'Accountant / Finance', 'Legal / Compliance', 'IT specialist', 'Manager / Executive', 'Teacher / Academic', 'Other skilled role'],
    'Intra-Company Transfer': ['Executive / Manager', 'Specialist / Expert', 'Trainee (ICT)', 'Support staff (ICT)', 'Other ICT role'],
    'Domestic Worker / Household': ['Housemaid / Cleaner', 'Nanny / Childcare', 'Private driver', 'Private cook', 'Caregiver (elderly)', 'Gardener / Other household', 'Other domestic'],
    'Crew / Seafarer / Maritime': [...VISA_SUBTYPE_CREW],
    'Working Holiday': ['Hospitality / Tourism', 'Agriculture / Farm', 'Retail / Sales', 'Office / Admin (WH)', 'Other WH job'],
    'Internship / Training': ['Paid internship', 'Unpaid internship', 'Graduate trainee', 'Vocational training', 'Medical internship', 'Other training'],
    'Digital Nomad / Remote Work': ['Employed (remote employer)', 'Freelancer (clients abroad)', 'Self-employed online', 'Mixed remote work'],
    'Freelance / Self-Employed': ['Consultant', 'Contractor', 'Creative freelancer', 'Trader / Agent', 'Other freelance'],
    'Investor / Entrepreneur': [...VISA_SUBTYPE_INVESTOR],
    'Golden Visa / Long-Stay Investor': ['Real estate route', 'Capital transfer route', 'Job creation route', 'Other golden visa'],
    'Free Zone / Business Setup': ['Free zone company owner', 'Free zone employee', 'Branch setup', 'Other free zone'],
    'Student Visa': [...VISA_SUBTYPE_STUDENT],
    'Language Course / Study Short-Term': ['Intensive language', 'Semester language', 'Exam preparation', 'Other short study'],
    'Research / Academic': ['Researcher / Postdoc', 'Visiting scholar', 'Laboratory staff', 'Field research', 'Other academic'],
    'Exchange / Au Pair / Cultural': ['Au pair', 'Cultural exchange participant', 'Summer camp staff', 'School exchange', 'Other exchange'],
    'Family / Dependent Visa': [...VISA_SUBTYPE_FAMILY],
    'Spouse / Partner / Marriage': ['Spouse', 'Fiancé / Fiancée (where applicable)', 'Partner (recognized)', 'Newly married joining', 'Other partner route'],
    'Parent / Super Visa (Family)': ['Parent visit (long-stay)', 'Grandparent', 'Dependent parent', 'Other parent category'],
    'Child / Minor Dependent': ['Minor child', 'Adopted child (process)', 'Student child', 'Other minor'],
    'Residence / Iqama': ['Employee Iqama', 'Dependent Iqama', 'Investor residence', 'Family residence', 'Other residence'],
    'Permanent Residence': ['Skilled PR route', 'Family PR', 'Investor PR', 'Long residence route', 'Other PR category'],
    'Transit Visa': ['Single transit', 'Double transit', 'Cruise passenger transit', 'Other transit'],
    'Airport Transit (Sterile)': ['Airside transit only', 'Connecting flight', 'Other sterile transit'],
    'Medical Treatment': [...VISA_SUBTYPE_MEDICAL],
    'Medical Escort / Companion': ['Escorting patient', 'Family support', 'Medical assistant', 'Other escort'],
    'Hajj / Umrah': ['Hajj pilgrim', 'Umrah pilgrim', 'Pilgrim group leader', 'Support staff (pilgrim)', 'Other pilgrimage'],
    'Religious / Missionary': [...VISA_SUBTYPE_RELIGIOUS],
    'Retirement': ['Retiree (income-based)', 'Retiree (property-based)', 'Accompanying spouse (retirement)', 'Other retirement'],
    'Artist / Entertainer / Performer': [...VISA_SUBTYPE_ARTS],
    'Athlete / Sports': [...VISA_SUBTYPE_SPORTS],
    'Journalist / Media': ['Staff correspondent', 'Freelance journalist', 'Film / Documentary crew', 'Photographer', 'Other media'],
    'NGO / Volunteer': [...VISA_SUBTYPE_NGO],
    'Diplomatic / Official': [...VISA_SUBTYPE_DIPLO],
    'Courtesy / Official Guest': ['State guest', 'Official delegation member', 'Invited guest (government)', 'Other courtesy'],
    'UN / International Organization': ['UN staff', 'Specialized agency staff', 'Military observer / Peacekeeping', 'Consultant (IO)', 'Other IO'],
    'Humanitarian / Protection': [...VISA_SUBTYPE_HUMANITARIAN],
    'Adoption': ['Prospective adoptive parent', 'Child (adoption in process)', 'Post-adoption follow-up', 'Other adoption'],
    'Embassy / Consular Staff': ['Diplomatic staff (embassy)', 'Administrative & technical staff', 'Locally employed staff', 'Other post'],
    'Other': ['Mixed / Multiple purposes', 'Legacy / Historical record', 'Not listed — use Requirements', 'General — unspecified']
};

// Informal / typo country names → canonical English name (must match countries-cities / getCountryData keys)
const RECRUITMENT_COUNTRY_INPUT_ALIASES = {
    'sauadi': 'Saudi Arabia',
    'saudia': 'Saudi Arabia',
    'saudi': 'Saudi Arabia',
    'saudia arabia': 'Saudi Arabia',
    'kingdom of saudi arabia': 'Saudi Arabia',
    'ksa': 'Saudi Arabia',
    'uae': 'United Arab Emirates',
    'emirates': 'United Arab Emirates',
    'dubai': 'United Arab Emirates',
    'philipines': 'Philippines',
    'phillipines': 'Philippines',
    'filipines': 'Philippines',
    'bangladish': 'Bangladesh',
    'banglades': 'Bangladesh',
    'sri lanka': 'Sri Lanka',
    'srilanka': 'Sri Lanka',
    'india': 'India',
    'pakistan': 'Pakistan',
    'nepal': 'Nepal',
    'egypt': 'Egypt',
    'jordan': 'Jordan',
    'lebanon': 'Lebanon',
    'kuwait': 'Kuwait',
    'qatar': 'Qatar',
    'bahrain': 'Bahrain',
    'oman': 'Oman',
    'yemen': 'Yemen',
    'iraq': 'Iraq',
    'syria': 'Syria',
    'palestine': 'Palestine',
    'morocco': 'Morocco',
    'tunisia': 'Tunisia',
    'algeria': 'Algeria',
    'sudan': 'Sudan',
    'ethiopia': 'Ethiopia',
    'kenya': 'Kenya',
    'uganda': 'Uganda',
    'nigeria': 'Nigeria',
    'ghana': 'Ghana',
    'indonesia': 'Indonesia',
    'malaysia': 'Malaysia',
    'thailand': 'Thailand',
    'vietnam': 'Vietnam',
    'china': 'China',
    'uk': 'United Kingdom',
    'usa': 'United States',
    'america': 'United States'
};

function recruitmentLevenshtein(a, b) {
    const m = a.length;
    const n = b.length;
    if (m === 0) return n;
    if (n === 0) return m;
    const dp = new Array(n + 1);
    for (let j = 0; j <= n; j++) dp[j] = j;
    for (let i = 1; i <= m; i++) {
        let prev = dp[0];
        dp[0] = i;
        for (let j = 1; j <= n; j++) {
            const tmp = dp[j];
            const cost = a[i - 1] === b[j - 1] ? 0 : 1;
            dp[j] = Math.min(dp[j] + 1, dp[j - 1] + 1, prev + cost);
            prev = tmp;
        }
    }
    return dp[n];
}

class ModernForms {
    constructor() {
        this.currentTable = '';
        this.currentAction = '';
        this.currentId = null;
        this.data = [];
        this.searchTerm = '';
        this.page = 1;
        this.perPage = 5; // default entries
        this.selectedIds = new Set();
        this.countryNameCache = new Map(); // Cache for country ID -> name mapping
        this.searchDebounceTimer = null;
        this.isCompanyInfoMode = false; // Track if we're in company info mode
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadStats();
        // Pre-load countries dataset when page loads
        this.preloadCountriesDataset();
    }
    
    // Pre-load countries dataset to ensure it's available when forms open
    async preloadCountriesDataset() {
        try {
            await this.loadCountriesDataset();
        } catch (e) {
            console.error('Failed to pre-load countries dataset:', e);
        }
    }
    
    bindEvents() {
        // Modal close events
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modern-modal') && !e.target.closest('.modern-modal-content')) {
                this.closeAllModals();
            }
        });
        
        // Escape key to close modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });
        
        // Event delegation for dynamically generated buttons and inputs
        const modalBody = document.getElementById('modalBody');
        const formPopupBody = document.getElementById('formPopupBody');
        
        // Handle form submissions via event delegation
        if (formPopupBody) {
            formPopupBody.addEventListener('submit', (e) => {
                e.preventDefault();
                const form = e.target;
                if (form.tagName === 'FORM') {
                    this.handleFormSubmit(e, this.currentTable);
                }
            });
            
            formPopupBody.addEventListener('click', (e) => {
                const btn = e.target.closest('button');
                if (!btn) return;
                
                if (btn.hasAttribute('data-action') && btn.getAttribute('data-action') === 'close-form') {
                    this.closeFormModal();
                }
                
                // Handle password toggle in form fields
                if (btn.classList.contains('password-toggle-btn') || e.target.closest('.password-toggle-btn')) {
                    const toggleBtn = btn.classList.contains('password-toggle-btn') ? btn : e.target.closest('.password-toggle-btn');
                    const targetId = toggleBtn.getAttribute('data-target');
                    const passwordInput = document.getElementById(targetId);
                    if (passwordInput) {
                        const icon = toggleBtn.querySelector('i');
                        if (passwordInput.type === 'password') {
                            passwordInput.type = 'text';
                            icon.classList.remove('fa-eye');
                            icon.classList.add('fa-eye-slash');
                            toggleBtn.classList.remove('btn-hidden');
                            toggleBtn.classList.add('btn-visible');
                        } else {
                            passwordInput.type = 'password';
                            icon.classList.remove('fa-eye-slash');
                            icon.classList.add('fa-eye');
                            toggleBtn.classList.remove('btn-visible');
                            toggleBtn.classList.add('btn-hidden');
                        }
                    }
                }
            });
        }
        
        if (modalBody) {
            // Handle password toggle click FIRST (before button handlers)
            modalBody.addEventListener('click', (e) => {
                // Check if click is on password cell, password status, or password toggle container
                const passwordCell = e.target.closest('.cell-password');
                const passwordToggle = e.target.closest('.password-toggle-container');
                const passwordStatus = e.target.closest('.password-status');
                
                // Only handle if it's a password-related element
                if (!passwordCell && !passwordToggle && !passwordStatus) return;
                
                // Don't handle if it's a button click (like permissions button)
                if (e.target.closest('button')) return;
                
                // Find the toggle container - prioritize password-toggle-container
                let container = passwordToggle;
                if (!container) {
                    container = passwordStatus;
                }
                if (!container && passwordCell) {
                    // If we clicked in the cell, find the container inside
                    container = passwordCell.querySelector('.password-toggle-container') || passwordCell.querySelector('.password-status');
                }
                
                if (!container) return;
                
                e.preventDefault();
                e.stopPropagation();
                
                    const icon = container.querySelector('.password-toggle-icon');
                    const text = container.querySelector('.password-text');
                    if (!icon || !text) {
                        return;
                    }
                    
                    const isVisible = container.getAttribute('data-password-visible') === 'true';
                    const encodedPassword = container.getAttribute('data-password-value') || container.getAttribute('data-password-hash') || '';
                    
                    // Decode the password from base64
                    let actualPassword = '';
                    if (encodedPassword) {
                        try {
                            actualPassword = decodeURIComponent(escape(atob(encodedPassword)));
                        } catch (err) {
                            actualPassword = '';
                        }
                    }
                    
                    if (isVisible) {
                        // Hide password - show dots
                        icon.classList.remove('fa-eye-slash', 'icon-visible');
                        icon.classList.add('fa-eye', 'icon-hidden');
                        text.classList.remove('password-visible');
                        text.innerHTML = '';
                        text.textContent = '••••••••';
                        container.setAttribute('data-password-visible', 'false');
                    } else {
                        // Show password hash visually
                        icon.classList.remove('fa-eye', 'icon-hidden');
                        icon.classList.add('fa-eye-slash', 'icon-visible');
                        if (actualPassword) {
                            // Show actual password (not hash)
                            requestAnimationFrame(() => {
                                while (text.firstChild) {
                                    text.removeChild(text.firstChild);
                                }
                                const textNode = document.createTextNode(actualPassword);
                                text.appendChild(textNode);
                                text.classList.add('password-visible');
                                void text.offsetHeight;
                            });
                        } else {
                            text.innerHTML = '';
                            text.textContent = 'Set';
                            text.classList.remove('password-visible');
                        }
                        container.setAttribute('data-password-visible', 'true');
                    }
                return; // Stop propagation
            });
            
            modalBody.addEventListener('click', (e) => {
                const btn = e.target.closest('button');
                if (!btn) return;
                
                // Handle data-action attributes
                if (btn.hasAttribute('data-action')) {
                    const action = btn.getAttribute('data-action');
                    if (action === 'open-form-modal') {
                        this.openFormModal('create');
                    } else if (action === 'refresh') {
                        this.refreshData().then(() => {
                            this.loadTableStats(this.currentTable).then(stats => {
                                this.renderTableWithStats(stats);
                            });
                        });
                    } else if (action === 'view-history') {
                        this.openHistoryModal();
                    } else if (action === 'open-system-history') {
                        this.openSystemHistory();
                    } else if (action === 'close-modal') {
                        this.closeAllModals();
                    } else if (action === 'bulk-delete') {
                        if (btn.getAttribute('data-disabled') !== 'true') {
                            this.bulkDelete();
                        }
                    } else if (action === 'bulk-activate') {
                        if (btn.getAttribute('data-disabled') !== 'true') {
                            this.bulkSetStatus('active');
                        }
                    } else if (action === 'bulk-deactivate') {
                        if (btn.getAttribute('data-disabled') !== 'true') {
                            this.bulkSetStatus('inactive');
                        }
                    } else if (action === 'select-all-page') {
                        this.selectAllOnPage();
                    } else if (action === 'clear-selection') {
                        if (btn.getAttribute('data-disabled') !== 'true') {
                            this.clearSelection();
                        }
                    } else if (action === 'export-csv') {
                        if (btn.getAttribute('data-disabled') !== 'true') {
                            this.exportSelectedCSV();
                        }
                    } else if (action === 'backfill') {
                        this.backfillPlaceholders();
                    } else if (action === 'change-page') {
                        if (btn.getAttribute('data-disabled') !== 'true') {
                            const page = parseInt(btn.getAttribute('data-page'));
                            this.changePage(page);
                        }
                    } else if (action === 'edit-item') {
                        const id = parseInt(btn.getAttribute('data-id'));
                        this.openEditForm(id);
                    } else if (action === 'delete-item') {
                        const idStr = btn.getAttribute('data-id');
                        if (!idStr) {
                            console.error('Delete button missing data-id attribute');
                            this.showNotification('Error: Missing item ID', 'error');
                            return;
                        }
                        const id = parseInt(idStr);
                        if (isNaN(id) || id === 0) {
                            this.showNotification('Error: Invalid item ID: ' + idStr, 'error');
                            return;
                        }
                        this.deleteItem(id);
                    } else if (action === 'fingerprint-action') {
                        const id = parseInt(btn.getAttribute('data-id'));
                        const username = btn.getAttribute('data-username') || '';
                        const status = btn.getAttribute('data-status') || '';
                        this.handleFingerprintAction(id, username, status);
                    } else if (action === 'fingerprint-unregister') {
                        const id = parseInt(btn.getAttribute('data-id'));
                        const username = btn.getAttribute('data-username') || '';
                        this.handleFingerprintUnregister(id, username);
                    } else if (action === 'manage-user-permissions') {
                        const userId = parseInt(btn.getAttribute('data-user-id'));
                        const username = btn.getAttribute('data-username') || '';
                        if (window.openUserPermissionsModal) {
                            window.openUserPermissionsModal(userId, username);
                        } else {
                            console.error('openUserPermissionsModal function not found');
                        }
                    } else if (action === 'toggle-status') {
                        const id = parseInt(btn.getAttribute('data-id'));
                        const current = btn.getAttribute('data-current') || 'inactive';
                        const newStatus = current === 'active' ? 'inactive' : 'active';
                        if (id && !isNaN(id)) {
                            this.selectedIds.clear();
                            this.selectedIds.add(id);
                            this.bulkSetStatus(newStatus);
                        }
                    }
                }
            });
            
            // Handle password toggle click - use event delegation on the table
            modalBody.addEventListener('click', (e) => {
                // Check if click is on password toggle container or any child element
                const passwordToggle = e.target.closest('.password-toggle-container');
                const passwordCell = e.target.closest('.cell-password');
                const passwordStatus = e.target.closest('.password-status');
                
                if (passwordToggle || (passwordCell && passwordStatus)) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Find the toggle container
                    const container = passwordToggle || passwordStatus?.closest('.password-toggle-container') || passwordStatus;
                    if (!container) return;
                    
                    const icon = container.querySelector('.password-toggle-icon');
                    const text = container.querySelector('.password-text');
                    if (!icon || !text) return;
                    
                    const isVisible = container.getAttribute('data-password-visible') === 'true';
                    
                    if (isVisible) {
                        // Hide password - show dots
                        icon.classList.remove('fa-eye-slash', 'icon-visible');
                        icon.classList.add('fa-eye', 'icon-hidden');
                        text.classList.remove('password-visible');
                        text.textContent = '••••••••';
                        container.setAttribute('data-password-visible', 'false');
                    } else {
                        // Show password indicator - show "Set"
                        icon.classList.remove('fa-eye', 'icon-hidden');
                        icon.classList.add('fa-eye-slash', 'icon-visible');
                        text.classList.remove('password-visible');
                        text.textContent = 'Set';
                        container.setAttribute('data-password-visible', 'true');
                    }
                }
            });
            
            // Handle history modal close
            const historyModal = document.getElementById('historyModal');
            if (historyModal) {
                historyModal.addEventListener('click', (e) => {
                    if (e.target.hasAttribute('data-action') && e.target.getAttribute('data-action') === 'close-history-modal') {
                        this.closeHistoryModal();
                    } else if (e.target.closest('.modal-close')) {
                        this.closeHistoryModal();
                    } else if (e.target === historyModal) {
                        this.closeHistoryModal();
                    }
                });
            }
            
            modalBody.addEventListener('change', (e) => {
                if (e.target.type === 'checkbox') {
                    if (e.target.hasAttribute('data-select-all')) {
                        this.toggleSelectAll(e.target);
                    } else if (e.target.hasAttribute('data-id')) {
                        const id = parseInt(e.target.getAttribute('data-id'));
                        this.toggleRow(id, e.target);
                    }
                } else if (e.target.classList.contains('entries-select')) {
                    this.changePerPage(e.target.value);
                }
            });
            
            // Search input handler - use multiple events to ensure it works
            modalBody.addEventListener('input', (e) => {
                if (e.target.classList.contains('search-input')) {
                    e.stopPropagation(); // Prevent event bubbling
                    this.handleSearch(e);
                }
            });
            
            // Handle keydown for immediate response
            modalBody.addEventListener('keydown', (e) => {
                if (e.target.classList.contains('search-input')) {
                    // Allow all keys to work normally - don't prevent default
                    // Only handle Enter key specially
                    if (e.key === 'Enter') {
                        e.preventDefault(); // Prevent form submission if any
                        this.handleSearch(e);
                    }
                }
            });
            
            // Also handle keyup for Enter key
            modalBody.addEventListener('keyup', (e) => {
                if (e.target.classList.contains('search-input')) {
                    if (e.key === 'Enter') {
                    this.handleSearch(e);
                    }
                }
            });
        }
    }
    
    // Open setting modal
    async openSettingModal(setting) {
        this.currentTable = setting;
        this.currentAction = 'list';
        
        const modal = document.getElementById('mainModal');
        const title = document.getElementById('modalTitle');
        const body = document.getElementById('modalBody');
        
        if (!modal || !title || !body) return;
        
        title.textContent = this.getSettingTitle(setting);
        body.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        
        modal.classList.remove('modal-hidden');
        modal.classList.add('show');
        
        try {
            // Load table data first
            await this.loadData();
            
            // Load table stats
            const stats = await this.loadTableStats(setting);
            
            // Render stats cards and table
            this.renderTableWithStats(stats);
        } catch (error) {
            const errorMsg = error.message || 'Unknown error occurred';
            const isPermissionError = error.isPermissionError || errorMsg.includes('permission') || errorMsg.includes('Access denied');
            
            if (isPermissionError) {
                // Show user-friendly permission denied message
                body.innerHTML = `
                    <div class="error-state">
                        <i class="fas fa-lock"></i>
                        <h3>Access Denied</h3>
                        <p>You don't have permission to access this module.</p>
                        <p class="error-hint">Required permission: ${errorMsg.includes('Required:') ? errorMsg.split('Required:')[1].trim() : 'Not available'}</p>
                        <p class="error-hint">Please contact your administrator to request access.</p>
                        <button class="modern-btn modern-btn-secondary error-close-btn" data-action="close-modal">Close</button>
                    </div>
                `;
            } else {
                // Show error for actual errors
                console.error('Error loading setting modal:', error);
            body.innerHTML = `
                <div class="error-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p><strong>Error:</strong> ${errorMsg}</p>
                    <p class="error-hint">Please check the browser console for more details.</p>
                    <button class="modern-btn modern-btn-secondary error-close-btn" data-action="close-modal">Close</button>
                </div>
            `;
            }
        }
    }
    
    // Load data from API
    async loadData() {
        try {
            // Populate country name cache BEFORE loading data (non-blocking - errors are handled internally)
            // This is optional and won't block if user doesn't have permissions
            this.populateCountryNameCache().catch(() => {
                // Silently ignore - country cache is optional
            });
            
            // Also ensure countriesCities dataset is loaded for city->country lookup
            if (!window.countriesCities || Object.keys(window.countriesCities).length === 0) {
                await this.loadCountriesDataset();
            }
            
            const response = await this.apiCall('get_all', this.currentTable);
            if (response.success) {
                this.data = response.data || [];
                
                // If we're on recruitment_countries table, populate cache from this data
                if (this.currentTable === 'recruitment_countries' && this.data.length > 0) {
                    this.data.forEach(country => {
                        const id = country.id;
                        const name = country.country_name || country.name;
                        if (id && name) {
                            this.countryNameCache.set(id, name);
                            this.countryNameCache.set(String(id), name);
                        }
                    });
                }
                
                // Check if we're in profile modal context - if so, don't render yet (filtering will handle it)
                const profileModal = document.getElementById('mainModal');
                const modalBody = profileModal?.querySelector('#modalBody') || profileModal?.querySelector('.modal-body');
                const isProfileModal = profileModal && modalBody && modalBody.classList.contains('profile-modal-content');
                
                // Only render if NOT in profile modal context (profile modal will render after filtering)
                if (!isProfileModal || this.currentTable !== 'users') {
                    this.renderTable();
                }
            } else {
                throw new Error(response.message);
            }
        } catch (error) {
            const isPermissionError = error.isPermissionError || (error.message && (error.message.includes('permission') || error.message.includes('Access denied')));
            
            // Don't log permission errors - they're expected
            if (!isPermissionError) {
            console.error('Load error:', error);
            }
            
            throw error;
        }
    }
    
    // Load stats for current table
    async loadTableStats(table) {
        try {
            const response = await this.apiCall('get_stats', table);
            if (response.success) {
                return {
                    total: response.data.total || 0,
                    active: response.data.active || 0,
                    inactive: response.data.inactive || 0,
                    today: response.data.today || 0,
                    thisWeek: response.data.thisWeek || 0,
                    thisMonth: response.data.thisMonth || 0
                };
            }
        } catch (error) {
            console.error(`Failed to load stats for ${table}:`, error);
        }
        return { total: 0, active: 0, inactive: 0, today: 0, thisWeek: 0, thisMonth: 0 };
    }
    
    // Render table with stats cards
    renderTableWithStats(stats = null) {
        if (stats) {
            this.currentTableStats = stats;
        }
        this.renderTable();
    }
    
    // Render table with data
    renderTable() {
        const body = document.getElementById('modalBody');
        if (!body) return;
        
        // Preserve search input focus and cursor position
        const searchInput = body.querySelector('.search-input');
        let searchFocused = false;
        let searchCursorPos = 0;
        if (searchInput && document.activeElement === searchInput) {
            searchFocused = true;
            searchCursorPos = searchInput.selectionStart || 0;
        }
        
        const tableConfig = this.getTableConfig(this.currentTable);
        const filtered = this.getFilteredData();
        const paged = this.getPagedData(filtered);
        const total = filtered.length;
        const totalPages = Math.max(1, Math.ceil(total / this.perPage));
        
        // Get stats (use cached or calculate from data)
        const stats = this.currentTableStats || { 
            total: total, 
            active: this.data.filter(item => {
                const status = String(this.getFieldValue(item, 'status') || '').toLowerCase();
                const isActive = this.getFieldValue(item, 'is_active');
                return status === 'active' || status === '1' || isActive === 1 || isActive === '1';
            }).length,
            inactive: this.data.filter(item => {
                const status = String(this.getFieldValue(item, 'status') || '').toLowerCase();
                const isActive = this.getFieldValue(item, 'is_active');
                return status === 'inactive' || status === '0' || isActive === 0 || isActive === '0';
            }).length,
            today: 0,
            thisWeek: 0,
            thisMonth: 0
        };
        
        let html = `
            <div class="modern-data-table">
                <!-- Table Stats Cards -->
                <div class="table-stats-grid">
                    <div class="table-stat-card">
                        <div class="table-stat-icon"><i class="fas fa-database"></i></div>
                        <div class="table-stat-content">
                            <div class="table-stat-number">${stats.total}</div>
                            <div class="table-stat-label">Total Records</div>
                        </div>
                    </div>
                    <div class="table-stat-card">
                        <div class="table-stat-icon active"><i class="fas fa-check-circle"></i></div>
                        <div class="table-stat-content">
                            <div class="table-stat-number">${stats.active}</div>
                            <div class="table-stat-label">Active</div>
                        </div>
                    </div>
                    <div class="table-stat-card">
                        <div class="table-stat-icon inactive"><i class="fas fa-times-circle"></i></div>
                        <div class="table-stat-content">
                            <div class="table-stat-number">${stats.inactive}</div>
                            <div class="table-stat-label">Inactive</div>
                        </div>
                    </div>
                    <div class="table-stat-card">
                        <div class="table-stat-icon today"><i class="fas fa-calendar-day"></i></div>
                        <div class="table-stat-content">
                            <div class="table-stat-number">${stats.today}</div>
                            <div class="table-stat-label">Today</div>
                        </div>
                    </div>
                    <div class="table-stat-card">
                        <div class="table-stat-icon week"><i class="fas fa-calendar-week"></i></div>
                        <div class="table-stat-content">
                            <div class="table-stat-number">${stats.thisWeek}</div>
                            <div class="table-stat-label">This Week</div>
                        </div>
                    </div>
                    <div class="table-stat-card">
                        <div class="table-stat-icon month"><i class="fas fa-calendar-alt"></i></div>
                        <div class="table-stat-content">
                            <div class="table-stat-number">${stats.thisMonth}</div>
                            <div class="table-stat-label">This Month</div>
                        </div>
                    </div>
                </div>
                
                <div class="table-header">
                    <div class="table-actions">
                        <button class="modern-btn modern-btn-primary" data-action="open-form-modal" data-permission="${this.getAddPermission(this.currentTable)}">
                            <i class="fas fa-plus"></i>
                            Add New
                        </button>
                        <button class="modern-btn modern-btn-secondary" data-action="refresh">
                            <i class="fas fa-sync"></i>
                            Refresh
                        </button>
                        <button class="modern-btn modern-btn-secondary" data-action="view-history">
                            <i class="fas fa-history"></i>
                            View History
                        </button>
                        <button class="modern-btn modern-btn-danger bulk-action-btn" data-action="bulk-delete" data-disabled="true" data-permission="${this.getDeletePermission(this.currentTable)}">
                            <i class="fas fa-trash"></i>
                            Delete Selected (<span class="selection-count">${this.selectedIds.size}</span>)
                        </button>
                        <button class="modern-btn modern-btn-success bulk-action-btn" data-action="bulk-activate" data-disabled="true">
                            <i class="fas fa-toggle-on"></i>
                            Activate
                        </button>
                        <button class="modern-btn modern-btn-warning bulk-action-btn" data-action="bulk-deactivate" data-disabled="true">
                            <i class="fas fa-toggle-off"></i>
                            Deactivate
                        </button>
                        <button class="modern-btn modern-btn-secondary" data-action="select-all-page">
                            <i class="fas fa-check-square"></i>
                            Select Page
                        </button>
                        <button class="modern-btn modern-btn-secondary bulk-action-btn" data-action="clear-selection" data-disabled="true">
                            <i class="fas fa-ban"></i>
                            Clear
                        </button>
                        <button class="modern-btn modern-btn-secondary bulk-action-btn" data-action="export-csv" data-disabled="true">
                            <i class="fas fa-file-export"></i>
                            Export CSV
                        </button>
                        <button class="modern-btn modern-btn-secondary" data-action="backfill">
                            <i class="fas fa-magic"></i>
                            Backfill Empty
                        </button>
                    </div>
                    <div class="table-search">
                        <input type="text" class="search-input" placeholder="Search..." value="${this.searchTerm}" autocomplete="off" spellcheck="false">
                        <select class="entries-select">
                            ${[5,10,25,50].map(n => `<option value="${n}">${n}/page</option>`).join('')}
                        </select>
                    </div>
                </div>
                <div class="table-container">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                ${tableConfig.columns.map(col => `<th>${col.label}</th>`).join('')}
                                <th><input type="checkbox" data-select-all></th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${paged.map(item => this.generateTableRow(item, tableConfig)).join('')}
                        </tbody>
                    </table>
                </div>
                <div class="table-footer">
                    <div class="page-info">Showing ${paged.length ? ((this.page-1)*this.perPage+1) : 0}–${Math.min(this.page*this.perPage,total)} of ${total}</div>
                    <div class="pagination-controls">
                        <button class="modern-btn modern-btn-secondary pagination-btn" data-action="change-page" data-page="${this.page-1}" data-prev="true">Prev</button>
                        <span class="page-num">${this.page}/${totalPages}</span>
                        <button class="modern-btn modern-btn-secondary pagination-btn" data-action="change-page" data-page="${this.page+1}" data-next="true">Next</button>
                    </div>
                </div>
            </div>
        `;
        
        // Preserve activities section if in profile modal context
        const isProfileModal = body.classList.contains('profile-modal-content');
        let preservedActivitiesSection = null;
        
        if (isProfileModal) {
            preservedActivitiesSection = body.querySelector('.profile-activities-section');
        }
        
        body.innerHTML = html;
        
        // Re-add preserved activities section after render
        if (isProfileModal && preservedActivitiesSection) {
            const tableContainer = body.querySelector('.modern-data-table');
            if (tableContainer) {
                tableContainer.insertAdjacentElement('afterend', preservedActivitiesSection);
            }
        }
        
        // Debug: Verify action buttons are in DOM
        setTimeout(() => {
            const actionButtons = body.querySelectorAll('.actions-cell button[data-action]');
            if (actionButtons.length === 0) {
                const actionsCells = body.querySelectorAll('.actions-cell');
            } else {
            }
        }, 100);
        
        // Set dropdown selected value (no inline logic in template)
        const entriesSelect = body.querySelector('.entries-select');
        if (entriesSelect) {
            entriesSelect.value = this.perPage;
            // Add scroll class to table container if perPage > 5
            const tableContainer = body.querySelector('.table-container');
            if (tableContainer) {
                if (this.perPage > 5) {
                    tableContainer.classList.add('scrollable-table');
                } else {
                    tableContainer.classList.remove('scrollable-table');
                }
            }
        }
        
        // Restore search input focus and cursor position if it was focused before render
        if (searchFocused) {
            const newSearchInput = body.querySelector('.search-input');
            if (newSearchInput) {
                // Use setTimeout to ensure DOM is ready
                setTimeout(() => {
                    newSearchInput.focus();
                    if (searchCursorPos >= 0 && searchCursorPos <= newSearchInput.value.length) {
                        newSearchInput.setSelectionRange(searchCursorPos, searchCursorPos);
                    }
                }, 0);
            }
        }
        
        // Set checkbox checked states (no inline logic in template)
        const checkboxes = body.querySelectorAll('input[type="checkbox"][data-id]');
        checkboxes.forEach(cb => {
            const id = parseInt(cb.getAttribute('data-id'));
            if (this.selectedIds.has(id)) {
                cb.checked = true;
            }
        });
        
        // Set pagination button states after rendering (no inline logic in template)
        const prevBtn = body.querySelector('[data-prev="true"]');
        const nextBtn = body.querySelector('[data-next="true"]');
        if (prevBtn) {
            if (this.page <= 1) {
                prevBtn.classList.add('disabled');
                prevBtn.setAttribute('data-disabled', 'true');
            } else {
                prevBtn.classList.remove('disabled');
                prevBtn.setAttribute('data-disabled', 'false');
            }
        }
        if (nextBtn) {
            const totalPages = Math.max(1, Math.ceil(this.data.length / this.perPage));
            if (this.page >= totalPages) {
                nextBtn.classList.add('disabled');
                nextBtn.setAttribute('data-disabled', 'true');
            } else {
                nextBtn.classList.remove('disabled');
                nextBtn.setAttribute('data-disabled', 'false');
            }
        }
        
        this.updateBulkButtonsState();
        
        // Re-apply permissions to newly rendered elements - wait a bit to ensure permissions are loaded
        setTimeout(() => {
            if (window.UserPermissions) {
                if (window.UserPermissions.loaded) {
                    window.UserPermissions.applyPermissions();
                } else {
                    // If permissions not loaded yet, wait for them to load
                    window.UserPermissions.load().then(() => {
                        window.UserPermissions.applyPermissions();
                    });
                }
            }
        }, 100);
    }
    
    // Get filtered data based on search
    getFilteredData() {
        if (!this.searchTerm || this.searchTerm.trim() === '') {
            return this.data;
        }
        
        const searchLower = this.searchTerm.toLowerCase().trim();
        
        return this.data.filter(item => {
            // Search through all values in the item
            for (const value of Object.values(item)) {
                if (value !== null && value !== undefined) {
                    const strValue = String(value).toLowerCase();
                    if (strValue.includes(searchLower)) {
                        return true;
                    }
                }
            }
            return false;
        });
    }

    // Pagination helpers
    getPagedData(list){
        const start = (this.page - 1) * this.perPage;
        return list.slice(start, start + this.perPage);
    }
    changePage(newPage){
        this.page = Math.max(1, newPage);
        this.renderTable();
    }
    changePerPage(val){
        this.perPage = parseInt(val,10) || 5;
        this.page = 1;
        this.renderTable();
        
        // Update table container scroll class based on entries count
        const modalBody = document.getElementById('modalBody');
        if (modalBody) {
            const tableContainer = modalBody.querySelector('.table-container');
            if (tableContainer) {
                if (this.perPage > 5) {
                    tableContainer.classList.add('scrollable-table');
                } else {
                    tableContainer.classList.remove('scrollable-table');
                }
            }
        }
    }
    
    // Backfill placeholders for missing key fields on the current table
    async backfillPlaceholders(){
        const ok = await this.confirmDialog('Backfill empty Name/Description values on this table?');
        if (!ok) return;
        const table = this.currentTable;
        const cfg = this.getTableConfig(table);
        const alias = this.getReverseAliasMap(table);
        const keyFields = cfg.columns.map(c => c.key).filter(k => ['name','description'].includes(k));
        let updated = 0;
        for (const item of this.data){
            const payload = {};
            for (const key of keyFields){
                const current = this.getFieldValue(item, key);
                if (current === null || current === undefined || String(current).trim() === ''){
                    payload[key] = key === 'name' ? `Unnamed ${item.id}` : '-';
                }
            }
            if (Object.keys(payload).length){
                try{
                    await this.apiCall('update', table, { id: item.id, data: payload });
                    updated++;
                }catch(e){
                    console.warn('Backfill update failed for id', item.id, e);
                }
            }
        }
                await this.refreshData();
                const stats = await this.loadTableStats(this.currentTable);
                this.renderTableWithStats(stats);
        this.showNotification(`Backfilled ${updated} item(s).`, 'success');
    }
    
    // Handle search with debouncing to prevent too many re-renders
    handleSearch(event) {
        if (!event || !event.target) {
            console.error('Invalid search event:', event);
            return;
        }
        
        const newSearchTerm = event.target.value || '';
        
        // Clear previous debounce timer
        if (this.searchDebounceTimer) {
            clearTimeout(this.searchDebounceTimer);
        }
        
        // Update search term immediately so input shows the typed value
        this.searchTerm = newSearchTerm;
        
        // Debounce the actual table re-render to prevent losing focus while typing
        this.searchDebounceTimer = setTimeout(() => {
            // Reset to first page when searching
            this.page = 1;
        this.renderTable();
            this.searchDebounceTimer = null;
        }, 300); // 300ms debounce delay - table updates after user stops typing
    }
    
    // Get field value from item using alias mapping
    getFieldValue(item, fieldKey) {
        // First try direct key
        if (item.hasOwnProperty(fieldKey)) {
            return item[fieldKey];
        }
        
        // Try case-insensitive match
        const lowerKey = fieldKey.toLowerCase();
        for (const key in item) {
            if (key.toLowerCase() === lowerKey) {
                return item[key];
            }
        }
        
        // Try alias mapping (reverse of what we do when saving)
        const aliasMap = this.getReverseAliasMap(this.currentTable);
        if (aliasMap[fieldKey]) {
            for (const dbCol of aliasMap[fieldKey]) {
                // First check exact match
                if (item.hasOwnProperty(dbCol)) {
                    return item[dbCol];
                }
                // Then try case-insensitive match
                for (const key in item) {
                    if (key.toLowerCase() === dbCol.toLowerCase()) {
                        return item[key];
                    }
                }
            }
        }
        
        return null;
    }
    
    // Get reverse alias map (form field -> possible DB columns)
    getReverseAliasMap(table) {
        const maps = {
            'visa_types': {
                'name': ['visa_name', 'name', 'title'],
                'description': ['visa_description', 'description', 'details'],
                'validity_days': ['validity_days', 'validity', 'days', 'processing_time'],
                'processing_fee': ['processing_fee', 'fee', 'fees'],
                'country_id': ['country_id', 'country'],
                'country_name': ['country_name'],
                'city': ['city'],
                'status': ['status', 'is_active']
            },
            'recruitment_countries': {
                'name': ['country_name', 'name', 'country'],
                'code': ['country_code', 'code'],
                'city': ['city'],
                'currency': ['currency', 'currency_code'],
                'flag_emoji': ['flag_emoji', 'flag'],
                'position': ['position'],
                'status': ['status', 'is_active']
            },
            'job_categories': {
                'name': ['category_name', 'name', 'category', 'title'],
                'description': ['category_description', 'description', 'details'],
                'min_salary': ['min_salary', 'min'],
                'max_salary': ['max_salary', 'max'],
                'country_id': ['country_id', 'country'],
                'city': ['city'],
                'status': ['status', 'is_active']
            },
            'office_managers': {
                'name': ['name', 'manager_name', 'full_name', 'office_manager_name'],
                'email': ['email', 'email_address'],
                'phone': ['phone', 'contact_number', 'phone_number'],
                'position': ['position', 'job_title'],
                'address': ['address'],
                'country_id': ['country_id', 'country'],
                'city': ['city'],
                'status': ['status', 'is_active']
            },
            'arrival_agencies': {
                'name': ['agency_name', 'name', 'agency'],
                'description': ['agency_description', 'description', 'details', 'contact_info'],
                'country_id': ['country_id', 'country'],
                'city': ['city'],
                'status': ['status', 'is_active']
            },
            'arrival_stations': {
                'name': ['name', 'station', 'station_name'],
                'description': ['description', 'details'],
                'country_id': ['country_id', 'country'],
                'city': ['city'],
                'status': ['status', 'is_active']
            },
            'worker_statuses': {
                'name': ['status_name', 'name', 'title'],
                'description': ['status_description', 'description', 'details'],
                'country_id': ['country_id', 'country'],
                'city': ['city'],
                'status': ['status', 'is_active']
            },
            'status_specifications': {
                'name': ['status_name', 'name', 'title'],
                'description': ['status_description', 'description', 'details'],
                'country_id': ['country_id', 'country'],
                'city': ['city'],
                'status': ['status', 'is_active']
            },
            'age_specifications': {
                'name': ['age_range', 'name', 'title'],
                'description': ['age_description', 'description', 'details'],
                'country_id': ['country_id', 'country'],
                'city': ['city'],
                'status': ['status', 'is_active'],
                'min_salary': ['min_age'],
                'max_salary': ['max_age']
            },
            'system_config': {
                'name': ['config_key', 'name', 'config_name', 'key'],
                'description': ['description', 'details', 'config_value', 'value'],
                'country_id': ['country_id', 'country'],
                'city': ['city'],
                'status': ['status', 'is_active']
            },
            'currencies': {
                'code': ['code', 'currency_code'],
                'name': ['name', 'currency_name'],
                'symbol': ['symbol', 'currency_symbol'],
                'display_order': ['display_order', 'order', 'sort_order'],
                'status': ['is_active', 'status']
            },
            'users': {
                'name': ['username', 'full_name', 'name', 'user_name'],
                'email': ['email', 'user_email', 'email_address'],
                'password': ['password'],
                'phone': ['phone', 'contact_number', 'phone_number'],
                'position': ['position', 'job_title'],
                'status': ['status', 'is_active'],
                'fingerprint_status': ['fingerprint_status', 'has_fingerprint']
            },
            'appearance_specifications': {
                'name': ['specification_name', 'name', 'appearance_name'],
                'description': ['description', 'specification_description'],
                'country_id': ['country_id', 'country'],
                'city': ['city'],
                'status': ['status', 'is_active']
            },
            'request_statuses': {
                'name': ['status_name', 'name', 'request_status_name'],
                'description': ['description', 'status_description'],
                'country_id': ['country_id', 'country'],
                'city': ['city'],
                'status': ['status', 'is_active']
            }
        };
        return maps[table] || {};
    }
    
    // Get permission name for edit action based on table
    getEditPermission(tableName) {
        const permissionMap = {
            'users': 'edit_user',
            'agents': 'edit_agent',
            'workers': 'edit_worker',
            'cases': 'edit_case'
        };
        return permissionMap[tableName] || 'manage_settings';
    }
    
    getCrudPermission(tableName, action) {
        const matrix = {
            users: { add: 'add_user', edit: 'edit_user', delete: 'delete_user' },
            agents: { add: 'add_agent', edit: 'edit_agent', delete: 'delete_agent' },
            subagents: { add: 'add_subagent', edit: 'edit_subagent', delete: 'delete_subagent' },
            workers: { add: 'add_worker', edit: 'edit_worker', delete: 'delete_worker' },
            cases: { add: 'add_case', edit: 'edit_case', delete: 'delete_case' },
            office_managers: { manage: 'manage_branches' },
            visa_types: { manage: 'manage_settings' },
            recruitment_countries: { manage: 'manage_recruitment_countries' },
            job_categories: { manage: 'manage_job_categories' },
            age_specifications: { manage: 'manage_recruitment_settings' },
            appearance_specifications: { manage: 'manage_recruitment_settings' },
            status_specifications: { manage: 'manage_recruitment_settings' },
            request_statuses: { manage: 'manage_recruitment_settings' },
            arrival_agencies: { manage: 'manage_recruitment_settings' },
            arrival_stations: { manage: 'manage_recruitment_settings' },
            worker_statuses: { manage: 'manage_positions' },
            system_config: { manage: 'manage_settings' }
        };
        const key = tableName || this.currentTable;
        const config = matrix[key];
        if (!config) {
            return 'manage_settings';
        }
        if (config[action]) {
            return config[action];
        }
        if (config.manage) {
            return config.manage;
        }
        return 'manage_settings';
    }
    
    getDeletePermission(tableName) {
        return this.getCrudPermission(tableName, 'delete');
    }
    
    getAddPermission(tableName) {
        return this.getCrudPermission(tableName, 'add');
    }
    
    // Generate table row
    generateTableRow(item, tableConfig) {
        // Debug first row only
        if (this.data.indexOf(item) === 0) {
            tableConfig.columns.forEach(col => {
                const value = this.getFieldValue(item, col.key);
                // Find which key it came from
                let foundKey = null;
                if (item.hasOwnProperty(col.key)) {
                    foundKey = col.key;
                } else {
                    const aliasMap = this.getReverseAliasMap(this.currentTable);
                    if (aliasMap[col.key]) {
                        for (const dbCol of aliasMap[col.key]) {
                            if (item.hasOwnProperty(dbCol)) {
                                foundKey = dbCol;
                                break;
                            }
                            // Case-insensitive
                            for (const key in item) {
                                if (key.toLowerCase() === dbCol.toLowerCase()) {
                                    foundKey = key;
                                    break;
                                }
                            }
                            if (foundKey) break;
                        }
                    }
                }
            });
        }
        
        const itemId = item.id || item.user_id || item.userId || '';
        
        // Debug: Log ID extraction for troubleshooting
        if (!itemId || itemId === '') {
        }
        
        const rowHtml = `
            <tr>
                ${tableConfig.columns.map(col => {
                    const value = this.getFieldValue(item, col.key);
                    return `<td><span class="cell-clip cell-${col.key}">${this.formatCellValue(value, col.type, col.maxLen, col.key, item)}</span></td>`;
                }).join('')}
                <td><input type="checkbox" data-id="${itemId}"></td>
                <td class="actions-cell">
                    <button class="modern-btn modern-btn-sm modern-btn-primary" data-action="edit-item" data-id="${itemId}" title="Edit" aria-label="Edit" data-permission="${this.getEditPermission(tableConfig.table)}">
                        <i class="fas fa-edit"></i>
                        <span class="btn-text">Edit</span>
                    </button>
                    <button class="modern-btn modern-btn-sm modern-btn-danger" data-action="delete-item" data-id="${itemId}" title="Delete" aria-label="Delete" data-permission="${this.getDeletePermission(tableConfig.table)}">
                        <i class="fas fa-trash"></i>
                        <span class="btn-text">Delete</span>
                    </button>
                </td>
            </tr>
        `;
        
        // Debug: Log the generated row HTML for the first item
        if (this.data.indexOf(item) === 0) {
        }
        
        return rowHtml;
    }
    
    renderFingerprintButton(item) {
        const statusValue = (this.getFieldValue(item, 'fingerprint_status') || '').toString().toLowerCase();
        const isRegistered = statusValue.includes('registered') && !statusValue.includes('not');
        const username = this.getFieldValue(item, 'name') || item.username || '';
        const buttonClass = isRegistered ? 'modern-btn-warning' : 'modern-btn-success';
        const icon = isRegistered ? 'fa-sync-alt' : 'fa-fingerprint';
        const label = isRegistered ? 'Re-register' : 'Register';
        
        return `
            <button type="button" class="modern-btn modern-btn-sm ${buttonClass}" 
                data-action="fingerprint-action" 
                data-id="${item.id || item.user_id}" 
                data-username="${username}" 
                data-status="${this.getFieldValue(item, 'fingerprint_status') || ''}">
                <i class="fas ${icon}"></i>
                <span class="btn-text">${label}</span>
            </button>
        `;
    }
    
    // Format cell value
    formatCellValue(value, type, maxLen, colKey = null, item = null) {
        // Handle special types that should always render (even with null value)
        if (type === 'permissions' || colKey === 'permissions') {
            const userId = (item && (item.user_id || item.id)) ? (item.user_id || item.id) : '';
            const username = item ? (item.username || item.name || this.getFieldValue(item, 'name') || '') : '';
            const hasCustomPermissions = item && item.permissions && item.permissions !== null && item.permissions !== '';
            
            return `
                <button type="button" class="modern-btn modern-btn-sm modern-btn-primary user-permissions-btn" 
                    data-action="manage-user-permissions"
                    data-user-id="${userId}"
                    data-username="${username}"
                    title="Manage User Permissions">
                    <i class="fas fa-key"></i>
                    <span class="btn-text">${hasCustomPermissions ? 'Edit' : 'Set'} Permissions</span>
                </button>
            `;
        }
        
        // Handle password type - show masked password or "Not Set" with toggle (always render)
        if (type === 'password' || colKey === 'password') {
            const userId = (item && (item.user_id || item.id)) ? (item.user_id || item.id) : '';
            let plainPassword = '';
            
            // Try to get plain password from password_plain field ONLY
            // Never show the hash - only show plain password if password_plain exists
            if (item && (item.password_plain || item.passwordPlain)) {
                try {
                    const decoded = atob(item.password_plain || item.passwordPlain);
                    // Only use if decoding was successful and result is not empty
                    if (decoded && decoded.trim() !== '') {
                        plainPassword = decoded;
                    }
                } catch (e) {
                    // Decoding failed - treat as no password
                    plainPassword = '';
                }
            }
            
            // Never show plain password - API no longer sends password_plain for security
            // If password_plain was sent (legacy), show masked with toggle; otherwise show "••••••••" (password set)
            if (!plainPassword || plainPassword.trim() === '') {
                // Password exists but not exposed - show masked, no reveal
                return '<span class="password-status"><i class="fas fa-lock"></i> <span class="password-text">••••••••</span></span>';
            }
            
            // Encode for safe storage in data attribute
            let encodedPassword = '';
            try {
                encodedPassword = btoa(unescape(encodeURIComponent(plainPassword)));
            } catch (err) {
                encodedPassword = '';
            }
            
            // Check if this password was previously visible (stored in sessionStorage or check container state)
            // For now, always start hidden - user can click to show
            const initialDisplay = '••••••••';
            const initialIcon = 'fa-eye';
            const initialVisible = 'false';
            
            // Show masked password with clickable toggle
            return `
                <span class="password-status password-toggle-container" 
                      data-user-id="${userId}" 
                      data-password-visible="${initialVisible}"
                      data-password-value="${encodedPassword}"
                      title="Click to toggle password visibility">
                    <i class="fas ${initialIcon} password-toggle-icon"></i>
                    <span class="password-text">${initialDisplay}</span>
                </span>
            `;
        }
        
        if (value === null || value === undefined || value === '') {
            if (type === 'status') {
                // For status, if null/undefined, check is_active if available
                return '<span class="status-badge inactive">Inactive</span>';
            }
            // Special handling for country_id - try to resolve from item data
            if (colKey === 'country_id' && item) {
                const countryName = this.resolveCountryName(item);
                if (countryName) return countryName;
            }
            return '-';
        }
        
        // Special handling for country_id column - resolve to country name
        if (colKey === 'country_id') {
            const countryName = this.resolveCountryName(item || { country_id: value, country: value });
            if (countryName && countryName !== '-') {
                const str = String(countryName);
                if (maxLen && str.length > maxLen) return str.slice(0, maxLen) + '…';
                return str;
            }
        }
        
        switch (type) {
            case 'date':
                return new Date(value).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            case 'datetime':
                try {
                    const d = new Date(value);
                    if (isNaN(d.getTime())) return value || '-';
                    return new Intl.DateTimeFormat('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }).format(d);
                } catch (e) {
                    return value || '-';
                }
            case 'currency':
                const num = parseFloat(value);
                if (isNaN(num)) return '-';
                return '$' + num.toFixed(2);
            case 'boolean':
                return value ? 'Yes' : 'No';
            case 'status': {
                // Handle both 'status' field (active/inactive) and 'is_active' field (1/0)
                const val = String(value).toLowerCase();
                const isActive = val === 'active' || val === '1' || val === 'true' || val === 'yes';
                const itemId = (item && (item.user_id || item.id)) ? (item.user_id || item.id) : '';
                const clickable = this.currentTable === 'users' && itemId;
                const badge = `<span class="status-badge ${isActive ? 'active' : 'inactive'}">${isActive ? 'Active' : 'Inactive'}</span>`;
                if (clickable) {
                    return `<span class="status-toggle-cell" data-action="toggle-status" data-id="${itemId}" data-current="${isActive ? 'active' : 'inactive'}" title="Click to toggle">${badge}</span>`;
                }
                return badge;
            }
            case 'fingerprint': {
                const val = String(value).toLowerCase();
                const isRegistered = val === 'registered' || val === '1' || val === 'true' || val === 'yes';
                const userId = (item && (item.user_id || item.id)) ? (item.user_id || item.id) : '';
                const username = item ? (item.username || item.name || this.getFieldValue(item, 'name') || '') : '';
                
                // Single button that handles both register and unregister
                const buttonClass = isRegistered ? 'modern-btn-danger' : 'modern-btn-success';
                const icon = isRegistered ? 'fa-times-circle' : 'fa-fingerprint';
                const label = isRegistered ? 'Unregister' : 'Register';
                const action = isRegistered ? 'fingerprint-unregister' : 'fingerprint-action';
                
                return `
                    <button type="button" class="modern-btn modern-btn-sm ${buttonClass} fingerprint-action-btn" 
                        data-action="${action}"
                        data-id="${userId}"
                        data-username="${username}"
                        data-status="${value}">
                        <i class="fas ${icon}"></i>
                        <span class="btn-text">${label}</span>
                    </button>
                `;
            }
            default:
                const str = String(value);
                if (maxLen && str.length > maxLen) return str.slice(0, maxLen) + '…';
                return str;
        }
    }
    
    // Resolve country ID to country name
    resolveCountryName(item) {
        if (!item) return null;
        
        // First, check if we already have a country name in the item
        const countryName = item.country_name || item.country;
        if (countryName && typeof countryName === 'string' && countryName.trim() !== '' && !/^\d+$/.test(countryName)) {
            return countryName;
        }
        
        // Get country_id value
        let countryId = item.country_id;
        
        // Convert to number if it's a string
        if (typeof countryId === 'string' && /^\d+$/.test(countryId)) {
            countryId = parseInt(countryId);
        }
        
        // If country_id is already a country name (string, not numeric), return it
        if (countryId && typeof countryId === 'string' && countryId.trim() !== '' && !/^\d+$/.test(countryId)) {
            this.countryNameCache.set(countryId, countryId);
            return countryId;
        }
        
        // If country_id is a valid numeric ID (not 0 or null), try to resolve from cache or DB
        if (countryId && countryId !== 0 && countryId !== '0' && countryId !== '') {
            // Check cache first
            if (this.countryNameCache.has(countryId)) {
                return this.countryNameCache.get(countryId);
            }
            
            // Try to find country name from recruitment_countries data
            // Check if we have recruitment_countries data in current data array (if we're viewing that table)
            if (this.currentTable === 'recruitment_countries' && this.data.length > 0) {
                const country = this.data.find(c => (c.id === countryId || c.id == countryId));
                if (country) {
                    const name = country.country_name || country.name;
                    if (name) {
                        this.countryNameCache.set(countryId, name);
                        return name;
                    }
                }
            }
        }
        
        // If country_id is 0, null, or empty, try to find country by city name
        const cityValue = item.city;
        if (cityValue && typeof cityValue === 'string' && cityValue.trim() !== '') {
            // Check cache first for city -> country mapping
            if (this.countryNameCache.has(cityValue)) {
                return this.countryNameCache.get(cityValue);
            }
            
            // Try to find in countriesCities dataset
            if (window.countriesCities && typeof window.countriesCities === 'object') {
                // Find country that has this city
                for (const [countryName, cities] of Object.entries(window.countriesCities)) {
                    if (Array.isArray(cities) && cities.includes(cityValue)) {
                        // Cache it for future lookups
                        this.countryNameCache.set(cityValue, countryName);
                        if (countryId !== null && countryId !== undefined) {
                            this.countryNameCache.set(countryId, countryName);
                        }
                        return countryName;
                    }
                }
            }
        }
        
        return null;
    }
    
    // Async method to populate country name cache from recruitment_countries table
    // This is optional and non-critical - errors are silently handled
    async populateCountryNameCache() {
        try {
            const response = await this.apiCall('get_all', 'recruitment_countries');
            if (response.success && Array.isArray(response.data)) {
                response.data.forEach(country => {
                    const id = country.id;
                    const name = country.country_name || country.name;
                    if (id && name) {
                        this.countryNameCache.set(id, name);
                        this.countryNameCache.set(String(id), name);
                    }
                });
                // Country name cache populated
            }
        } catch (e) {
            // Silently ignore permission errors - country cache is optional
            // Only log if it's not a permission error
            const errorMsg = e.message || String(e);
            if (!errorMsg.includes('permission') && !errorMsg.includes('Access denied')) {
            }
            // Re-throw silently - we don't want to propagate this error
            // The country cache is optional and the system works without it
        }
    }
    
    // Open form modal for create/edit
    async openFormModal(action, id = null) {
        this.currentAction = action;
        this.currentId = id;
        
        const modal = document.getElementById('formPopupModal');
        const title = document.getElementById('formPopupTitle');
        const body = document.getElementById('formPopupBody');
        const modalContent = modal?.querySelector('.modern-modal-content');
        
        if (!modal || !title || !body) return;
        
        // Validate currentTable is set
        if (!this.currentTable) {
            body.innerHTML = '<div class="error-state"><i class="fas fa-exclamation-triangle"></i><p>Error: No table specified. Please ensure the form system is properly initialized.</p></div>';
            modal.classList.remove('modal-hidden');
            modal.classList.add('show');
            return;
        }
        
        // Adjust modal for company info form
        if (this.isCompanyInfoMode && this.currentTable === 'system_config') {
            modal.classList.add('company-info-modal');
            title.textContent = 'Company Information';
        } else {
            modal.classList.remove('company-info-modal');
            title.textContent = action === 'create' ? `Add New ${this.getSettingTitle(this.currentTable)}` : `Edit ${this.getSettingTitle(this.currentTable)}`;
        }
        
        // Show loading state
        body.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        modal.classList.remove('modal-hidden');
        modal.classList.add('show');
        
        try {
        if (action === 'create') {
            this.renderCreateForm(body);
        } else {
                await this.renderEditForm(body, id);
        }
        } catch (error) {
            body.innerHTML = `<div class="error-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading form: ${error.message}</p></div>`;
        }
    }
    
    // Render create form
    renderCreateForm(container) {
        // Check if we're in company info mode
        if (this.isCompanyInfoMode && this.currentTable === 'system_config') {
            this.renderCompanyInfoForm(container);
            return;
        }
        
        let formConfig = this.getFormConfig(this.currentTable);
        
        // Remove status field from users form when on profile page
        if (this.currentTable === 'users' && window.location.pathname.includes('profile.php')) {
            formConfig = {
                ...formConfig,
                fields: formConfig.fields.filter(field => field.name !== 'status')
            };
        }
        
        let html = `
            <form>
                <div class="form-grid">
                    ${formConfig.fields.map(field => this.renderFormField(field)).join('')}
                </div>
                <div class="form-actions">
                    <button type="button" class="modern-btn modern-btn-secondary" data-action="close-form">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                    <button type="submit" class="modern-btn modern-btn-primary">
                        <i class="fas fa-save"></i>
                        Create
                    </button>
                </div>
            </form>
        `;
        
        container.innerHTML = html;
        
        // Populate country and city dropdowns after rendering (only if form has country_id field)
        setTimeout(async () => {
            const formConfig = this.getFormConfig(this.currentTable);
            const hasCountryField = formConfig.fields.some(field => field.name === 'country_id');
            
            if (hasCountryField) {
                // Populating country dropdown...
                await this.populateCountryDropdown();
                this.setupCityDropdownListener();
            }
            
            // Special handling for recruitment_countries - populate country name dropdown
            if (this.currentTable === 'recruitment_countries') {
                // Preload ALL countries and cities in background for fast autocomplete
                await this.preloadAllCountriesAndCities();
                
                await this.populateRecruitmentCountryDropdown();
                this.setupRecruitmentCountryListener();
                // Also setup city dropdown listener for recruitment countries form
                this.setupRecruitmentCityDropdownListener();
                // Populate currency dropdown
                await this.populateCurrencyDropdown();
            }
            
            // Populate currency dropdown for any form that has currency field with currencyDropdown flag
            const hasCurrencyField = formConfig.fields.some(field => field.name === 'currency' && field.currencyDropdown);
            if (hasCurrencyField && this.currentTable !== 'recruitment_countries') {
                await this.populateCurrencyDropdown();
            }
            
            // Populate copy_from_currency dropdown for currencies form
            if (this.currentTable === 'currencies') {
                await this.populateCopyFromCurrencyDropdown();
                this.setupCopyFromCurrencyListener();
                
                // Populate country dropdown for currencies form
                const hasCountryFieldInCurrencies = formConfig.fields.some(field => field.name === 'country_id');
                if (hasCountryFieldInCurrencies) {
                    await this.populateCountryDropdown();
                    this.setupCurrencyCountryListener();
                }
            }
            
            if (this.currentTable === 'visa_types') {
                this.initVisaSubtypeField('');
            }
        }, 500);
    }
    
    // Populate country dropdown from recruitment_countries table (primary) or control_countries (when in control panel users form)
    async populateCountryDropdown(maxRetries = 3) {
        // Check if current form configuration includes country_id field
        const formConfig = this.getFormConfig(this.currentTable);
        const hasCountryField = formConfig.fields.some(field => field.name === 'country_id');
        
        if (!hasCountryField) {
            // This form doesn't have a country field, no need to populate
            return;
        }
        
        const countrySelect = document.getElementById('country_id_select');
        if (!countrySelect) {
            if (maxRetries > 0) {
                // Try again after a delay
                setTimeout(() => this.populateCountryDropdown(maxRetries - 1), 300);
                return;
            } else {
                // Max retries reached, form might not have been rendered yet or doesn't exist
                // Country select element not found
                return;
            }
        }
        
        try {
            // Visa types: full world country list (static + API merge); cities use country name
            if (this.currentTable === 'visa_types') {
                await this.preloadAllCountriesAndCities();
                const pack = this._allCountriesAndCitiesData || await this.preloadAllCountriesAndCities();
                const countries = pack.countries || [];
                countrySelect.innerHTML = '<option value="">Select Country...</option>';
                countries.forEach(name => {
                    if (!name) return;
                    const option = document.createElement('option');
                    option.value = '__world__:' + encodeURIComponent(name);
                    option.textContent = name;
                    option.setAttribute('data-name', name);
                    option.setAttribute('data-world-country', '1');
                    countrySelect.appendChild(option);
                });
                return;
            }
            
            // CONTROL PANEL + USERS FORM: Use control_countries (same as Select Country page)
            const isControlPanel = (() => {
                const el = document.getElementById('app-config');
                if (el && el.getAttribute('data-control') === '1') return true;
                if (typeof window.location !== 'undefined') {
                    if (window.location.search && window.location.search.includes('control=1')) return true;
                    if (window.location.pathname && window.location.pathname.indexOf('/control/') !== -1) return true;
                }
                return false;
            })();
            
            if (isControlPanel && this.currentTable === 'users') {
                const baseUrl = document.documentElement.getAttribute('data-base-url') || (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '';
                const controlPath = document.getElementById('app-config')?.getAttribute('data-control-api-path') || (baseUrl ? baseUrl.replace(/\/$/, '') + '/api/control' : '/api/control');
                const url = controlPath.replace(/\/$/, '') + '/get-control-countries-for-users.php';
                const response = await fetch(url, { method: 'GET', credentials: 'same-origin' });
                const data = await response.json();
                countrySelect.innerHTML = '<option value="">Select Country...</option>';
                if (data.success && Array.isArray(data.countries) && data.countries.length > 0) {
                    data.countries.forEach(country => {
                        const option = document.createElement('option');
                        option.value = country.id;
                        option.textContent = country.name;
                        option.setAttribute('data-name', country.name);
                        countrySelect.appendChild(option);
                    });
                }
                return;
            }
            
            let countryNames = [];
            
            // PRIMARY: Try to load from recruitment_countries table first
            try {
                const response = await this.apiCall('get_all', 'recruitment_countries');
                if (response.success && Array.isArray(response.data) && response.data.length > 0) {
                    countryNames = response.data.map(item => {
                        const name = item.country_name || item.name || '';
                        return name.trim();
                    }).filter(name => name !== '' && name !== null);
                    
                    if (countryNames.length > 0) {
                        countryNames.sort();
                    }
                }
            } catch (dbError) {
                // Fallback to dataset
            }
            
            // If no countries found, keep dropdown empty
            if (countryNames.length === 0) {
                countrySelect.innerHTML = '<option value="">Select Country...</option>';
                return;
            }
            
            // Populate dropdown with countries - store ID in value, name in text
            countrySelect.innerHTML = '<option value="">Select Country...</option>';
            
            // If we loaded from database, we should have IDs
            let countriesWithIds = [];
            try {
                const response = await this.apiCall('get_all', 'recruitment_countries');
                if (response.success && Array.isArray(response.data) && response.data.length > 0) {
                    countriesWithIds = response.data.map(item => ({
                        id: item.id,
                        name: item.country_name || item.name || ''
                    })).filter(c => c.name !== '' && c.id != null);
                }
            } catch (e) {
                // Fallback to names only
            }
            
            if (countriesWithIds.length > 0) {
                // Sort by name
                countriesWithIds.sort((a, b) => a.name.localeCompare(b.name));
                countriesWithIds.forEach(country => {
                    const option = document.createElement('option');
                    option.value = country.id; // Store ID in value
                    option.textContent = country.name; // Display name
                    option.setAttribute('data-name', country.name); // Store name in data attribute for reference
                    countrySelect.appendChild(option);
                });
            } else if (countryNames.length > 0) {
                // Fallback: If no IDs available, use names but try to resolve to IDs later
                countryNames.forEach(countryName => {
                    const option = document.createElement('option');
                    option.value = countryName; // Temporary: use name as value
                    option.textContent = countryName;
                    countrySelect.appendChild(option);
                });
            } else {
                // No countries in System Settings - keep dropdown empty
                countrySelect.innerHTML = '<option value="">Select Country...</option>';
            }
        } catch (e) {
            console.error('Failed to populate countries:', e);
            // Retry on error
            setTimeout(() => this.populateCountryDropdown(), 1000);
        }
    }
    
    // Preload ALL countries and cities in background for fast autocomplete
    // Merges static list from countries-cities.js with API data so we always have a full list
    async preloadAllCountriesAndCities() {
        // Return cached data if already loaded
        if (this._allCountriesAndCitiesPreloaded) {
            return this._allCountriesAndCitiesData;
        }
        
        // Start with FULL static list (STATIC_COUNTRIES_CITIES is set by countries-cities.js and never overwritten)
        let mergedCountriesCities = {};
        const staticList = (typeof window !== 'undefined' && (window.STATIC_COUNTRIES_CITIES || window.countriesCities)) || {};
        if (staticList && typeof staticList === 'object') {
            for (const [country, cities] of Object.entries(staticList)) {
                if (country && Array.isArray(cities)) {
                    mergedCountriesCities[country] = [...cities];
                }
            }
        }
        
        try {
            const baseUrl = document.documentElement.getAttribute('data-base-url') || '';
            const url = `${baseUrl}/api/admin/get_countries_cities.php?action=all`;
            
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                
                // Merge API data with static list (API cities get added; existing countries get more cities)
                if (data.success && data.countriesCities && typeof data.countriesCities === 'object') {
                    for (const [country, cities] of Object.entries(data.countriesCities)) {
                        if (!country) continue;
                        const cityList = Array.isArray(cities) ? cities : [];
                        if (!mergedCountriesCities[country]) {
                            mergedCountriesCities[country] = [];
                        }
                        cityList.forEach(city => {
                            if (city && !mergedCountriesCities[country].includes(city)) {
                                mergedCountriesCities[country].push(city);
                            }
                        });
                    }
                }
            }
        } catch (error) {
            console.warn('API fetch for countries/cities failed, using static list only:', error.message);
        }
        
        // Build final lists from merged data
        const allCountries = Object.keys(mergedCountriesCities).filter(Boolean).sort();
        const allCities = [];
        const citiesByCountry = {};
        
        for (const [country, cities] of Object.entries(mergedCountriesCities)) {
            if (!country) continue;
            const list = Array.isArray(cities) ? cities : [];
            citiesByCountry[country] = list;
            list.forEach(city => {
                if (city && !allCities.includes(city)) {
                    allCities.push(city);
                }
            });
        }
        
        allCities.sort();
        
        // Store preloaded data
        this._allCountriesAndCitiesData = {
            countries: allCountries,
            cities: allCities,
            citiesByCountry: citiesByCountry,
            countriesCities: mergedCountriesCities
        };
        
        this._allCountriesAndCitiesPreloaded = true;
        this._countriesCache = mergedCountriesCities;
        window.countriesCities = mergedCountriesCities;
        
        console.log(`✅ Preloaded ${allCountries.length} countries and ${allCities.length} cities for autocomplete`);
        
        return this._allCountriesAndCitiesData;
    }
    
    // Load the countries/cities dataset from API (recruitment_countries table)
    async loadCountriesDataset() {
        // Check if already loaded in cache
        if (this._countriesCache && typeof this._countriesCache === 'object' && Object.keys(this._countriesCache).length > 0) {
            return this._countriesCache;
        }
        
        // Try to use preloaded data if available
        if (this._allCountriesAndCitiesPreloaded && this._allCountriesAndCitiesData) {
            return this._allCountriesAndCitiesData.countriesCities;
        }
        
        try {
            const baseUrl = document.documentElement.getAttribute('data-base-url') || '';
            const url = `${baseUrl}/api/admin/get_countries_cities.php?action=all`;
            
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.countriesCities) {
                // Cache the data
                this._countriesCache = data.countriesCities;
                // Also set global for backward compatibility
                window.countriesCities = data.countriesCities;
                return data.countriesCities;
            } else {
                console.warn('No countries data returned from API');
                return {};
            }
        } catch (error) {
            console.error('Failed to load countries from API:', error);
            // Return empty object on error
            return {};
        }
    }
    
    // Load cities for a specific country from API, with static list fallback
    async loadCitiesForCountry(countryName) {
        if (!countryName) return [];
        
        try {
            const baseUrl = document.documentElement.getAttribute('data-base-url') || (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '';
            const url = `${baseUrl}/api/admin/get_countries_cities.php?action=cities&country=${encodeURIComponent(countryName)}`;
            
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && Array.isArray(data.cities) && data.cities.length > 0) {
                return data.cities;
            }
            
            // Fallback: use static countries-cities list when API returns no cities
            const staticList = window.STATIC_COUNTRIES_CITIES || window.countriesCities || {};
            const key = Object.keys(staticList).find(c => c.toLowerCase() === countryName.toLowerCase());
            if (key && Array.isArray(staticList[key]) && staticList[key].length > 0) {
                return staticList[key].slice().sort();
            }
            return [];
        } catch (error) {
            console.error('Failed to load cities for country:', error);
            // Fallback on error too
            const staticList = window.STATIC_COUNTRIES_CITIES || window.countriesCities || {};
            const key = Object.keys(staticList).find(c => c.toLowerCase() === countryName.toLowerCase());
            if (key && Array.isArray(staticList[key]) && staticList[key].length > 0) {
                return staticList[key].slice().sort();
            }
            return [];
        }
    }
    
    // Setup listener for country change to populate cities
    setupCityDropdownListener() {
        const countrySelect = document.getElementById('country_id_select');
        const citySelect = document.getElementById('city_select');
        
        if (!countrySelect || !citySelect) return;
        
        countrySelect.addEventListener('change', async (e) => {
            const countryId = e.target.value;
            const opt = e.target.options[e.target.selectedIndex];
            const countryName = (opt && (opt.getAttribute('data-name') || opt.textContent || '')).trim();
            
            // Clear city dropdown
            citySelect.innerHTML = '<option value="">Select Country First</option>';
            
            if (!countryId || !countryName || countryName === 'Select Country...') return;
            
            // Load cities from API
            try {
                const cities = await this.loadCitiesForCountry(countryName);
                if (cities && cities.length > 0) {
                    cities.forEach(city => {
                        const option = document.createElement('option');
                        option.value = city;
                        option.textContent = city;
                        citySelect.appendChild(option);
                    });
                } else {
                    citySelect.innerHTML = '<option value="">No cities available for this country</option>';
                }
            } catch (e) {
                console.error('Failed to load cities:', e);
                citySelect.innerHTML = '<option value="">Error loading cities</option>';
            }
        });
    }
    
    getVisaSubtypeChoices(visaType) {
        if (!visaType || typeof visaType !== 'string') return [];
        const list = VISA_SUBTYPE_CHOICES_BY_TYPE[visaType.trim()];
        if (list && list.length) return list.slice();
        return ['General / Unspecified', 'Other — see Requirements'];
    }
    
    populateVisaSubtypeSelect(visaType, selectedValue) {
        const sel = document.getElementById('description_select');
        if (!sel) return;
        sel.innerHTML = '';
        const ph = document.createElement('option');
        ph.value = '';
        ph.textContent = visaType ? 'Select role or category...' : 'Select visa type first...';
        sel.appendChild(ph);
        if (!visaType) return;
        this.getVisaSubtypeChoices(visaType).forEach(text => {
            const o = document.createElement('option');
            o.value = text;
            o.textContent = text;
            sel.appendChild(o);
        });
        if (selectedValue && String(selectedValue).trim() !== '') {
            const sv = String(selectedValue).trim();
            const match = Array.from(sel.options).find(opt => opt.value === sv || opt.textContent === sv);
            if (match) {
                sel.value = match.value;
            } else {
                const o = document.createElement('option');
                o.value = sv;
                o.textContent = sv + ' (saved)';
                sel.appendChild(o);
                sel.value = sv;
            }
        }
    }
    
    setupVisaSubtypeListener() {
        if (this.currentTable !== 'visa_types') return;
        const nameSel = document.getElementById('name_select');
        if (!nameSel || nameSel.dataset.visaSubtypeBound === '1') return;
        nameSel.dataset.visaSubtypeBound = '1';
        nameSel.addEventListener('change', () => {
            this.populateVisaSubtypeSelect(nameSel.value, '');
        });
    }
    
    initVisaSubtypeField(savedDescription) {
        if (this.currentTable !== 'visa_types') return;
        this.setupVisaSubtypeListener();
        const nameSel = document.getElementById('name_select');
        if (!nameSel) return;
        this.populateVisaSubtypeSelect(nameSel.value || '', savedDescription || '');
    }
    
    // Populate country dropdown/datalist for recruitment_countries form
    // Uses API to get countries from recruitment_countries table
    async populateRecruitmentCountryDropdown() {
        const countryInput = document.getElementById('country_name_select');
        const countryDatalist = document.getElementById('country_datalist');
        
        if (!countryInput) return;
        
        try {
            // Load countries from API
            const dataset = await this.loadCountriesDataset();
            
            // Clear existing options in datalist
            if (countryDatalist) {
                countryDatalist.innerHTML = '<option value="">Select Country...</option>';
                
                if (dataset && Object.keys(dataset).length > 0) {
                    const countryNames = Object.keys(dataset).sort();
                    countryNames.forEach(countryName => {
                        const option = document.createElement('option');
                        option.value = countryName;
                        countryDatalist.appendChild(option);
                    });
                }
            }
            
            // Update placeholder text
            if (dataset && Object.keys(dataset).length > 0) {
                countryInput.placeholder = 'Type country name or select from list';
            } else {
                countryInput.placeholder = 'Type country name (no existing countries)';
            }
            
            // Recruitment countries dropdown populated
        } catch (e) {
            console.error('Failed to populate recruitment country dropdown:', e);
            if (countryDatalist) {
                countryDatalist.innerHTML = '<option value="">Error loading countries</option>';
            }
            if (countryInput) {
                countryInput.placeholder = 'Type country name';
            }
        }
    }
    
    // Setup listener for recruitment countries to auto-fill currency, code, and flag
    setupRecruitmentCountryListener() {
        const countryInput = document.getElementById('country_name_select');
        const countryDatalist = document.getElementById('country_datalist');
        if (!countryInput) return;
        
        const formEl = countryInput.closest('form');
        const qForm = (sel) => (formEl ? formEl.querySelector(sel) : null);
        
        // Create custom dropdown container
        const formGroup = countryInput.closest('.form-group');
        if (!formGroup) return;
        
        // Create dropdown element if it doesn't exist
        let customDropdown = document.getElementById('country_autocomplete_dropdown');
        if (!customDropdown) {
            customDropdown = document.createElement('div');
            customDropdown.id = 'country_autocomplete_dropdown';
            customDropdown.className = 'country-autocomplete-dropdown';
            formGroup.style.position = 'relative';
            formGroup.appendChild(customDropdown);
        }
        
        // Store all available countries for filtering (will be populated from preloaded data)
        let allCountries = [];
        let isProcessing = false; // Prevent multiple simultaneous processing
        let selectedIndex = -1; // For keyboard navigation
        
        // Load all countries from preloaded data
        const loadCountriesForAutocomplete = async () => {
            try {
                // Use preloaded data if available
                if (this._allCountriesAndCitiesPreloaded && this._allCountriesAndCitiesData) {
                    allCountries = this._allCountriesAndCitiesData.countries || [];
                } else {
                    // Fallback: load if not preloaded yet
                    const preloadedData = await this.preloadAllCountriesAndCities();
                    allCountries = preloadedData.countries || [];
                }
            } catch (e) {
                console.error('Failed to load countries for autocomplete:', e);
            }
        };
        
        // Load countries on initialization (from preloaded data)
        loadCountriesForAutocomplete();
        
        // Show dropdown with matching countries
        const showDropdown = (matches) => {
            if (!customDropdown || !customDropdown.isConnected) return;
            if (!countryInput || !countryInput.isConnected) return;
            
            if (matches.length === 0) {
                customDropdown.classList.remove('show');
                return;
            }
            
            customDropdown.innerHTML = '';
            customDropdown.style.display = 'block';
            customDropdown.classList.add('show');
            
            // Create scrollable container
            const scrollContainer = document.createElement('div');
            scrollContainer.className = 'autocomplete-scroll-container';
            
            matches.forEach((country, index) => {
                const option = document.createElement('div');
                option.className = 'autocomplete-option';
                option.dataset.index = index;
                option.textContent = country;
                
                option.addEventListener('click', () => {
                    selectCountry(country);
                });
                
                option.addEventListener('mouseenter', () => {
                    // Remove highlight from all options
                    customDropdown.querySelectorAll('.autocomplete-option').forEach(opt => {
                        opt.classList.remove('highlighted');
                    });
                    // Highlight current option
                    option.classList.add('highlighted');
                    selectedIndex = index;
                });
                
                scrollContainer.appendChild(option);
            });
            
            customDropdown.appendChild(scrollContainer);
            
            // Position dropdown below input
            const rect = countryInput.getBoundingClientRect();
            customDropdown.style.top = `${countryInput.offsetHeight + 4}px`;
            customDropdown.style.left = '0';
            customDropdown.style.width = `${countryInput.offsetWidth}px`;
        };
        
        // Hide dropdown
        const hideDropdown = () => {
            if (customDropdown) {
                customDropdown.classList.remove('show');
                customDropdown.style.display = 'none';
                selectedIndex = -1;
            }
        };
        
        // Select a country
        const selectCountry = async (countryName) => {
            countryInput.value = countryName;
            hideDropdown();
            await handleCountryChange(countryName, true);
        };
        
        // Handle country input with autocomplete after 3 characters
        let debounceTimer;
        let lastProcessedValue = '';
        
        const handleCountryChange = async (countryName, isExactMatch = false) => {
            // Prevent multiple simultaneous calls
            if (isProcessing && !isExactMatch) return;
            
            if (!countryName || countryName.trim() === '') {
                // Clear fields if country is empty (scoped to this form — global querySelector was filling wrong modals)
                const codeField = qForm('input[name="code"]');
                const currencyField = qForm('select[name="currency"]') || qForm('input[name="currency"]');
                const flagField = qForm('input[name="flag_emoji"]');
                const cityInputEl = qForm('#city_select') || document.getElementById('city_select');
                
                if (codeField) codeField.value = '';
                if (currencyField) currencyField.value = '';
                if (flagField) flagField.value = '';
                if (cityInputEl) {
                    cityInputEl.value = '';
                    cityInputEl.placeholder = 'Select country first';
                }
                if (typeof this.updateCityAutocompleteList === 'function') {
                    this.updateCityAutocompleteList('');
                }
                lastProcessedValue = '';
                isProcessing = false;
                hideDropdown();
                return;
            }
            
            countryName = countryName.trim();
            
            // If we've already processed this exact value, skip to avoid duplicate API calls
            if (lastProcessedValue === countryName && !isExactMatch) {
                isProcessing = false;
                return;
            }
            
            // Ensure countries are loaded from preloaded data
            if (allCountries.length === 0) {
                await loadCountriesForAutocomplete();
                if (!countryInput.isConnected) { isProcessing = false; return; }
            }
            
            // Normalize obvious typos / aliases when user picked datalist or blurred (exact path)
            if (isExactMatch && allCountries.length > 0) {
                const canon = this.resolveRecruitmentCountryName(countryName, allCountries);
                if (canon && canon !== countryName && allCountries.some(c => c.toLowerCase() === canon.toLowerCase())) {
                    countryName = canon;
                    countryInput.value = canon;
                }
            }
            
            // Check if input matches any country (case-insensitive, starting with input)
            const inputLower = countryName.toLowerCase();
            let matchedCountry = null;
            let matches = [];
            
            // If user typed at least 3 characters and it's not an exact match (selection), find matching countries
            if (countryName.length >= 3 && !isExactMatch) {
                matches = allCountries.filter(c => c.toLowerCase().startsWith(inputLower));
                // Typo-friendly: e.g. "sauadi" → show "Saudi Arabia" (first-word fuzzy)
                if (matches.length === 0 && inputLower.length >= 4) {
                    const fuzzy = allCountries.filter(c => {
                        const fw = c.toLowerCase().split(/\s+/)[0];
                        return fw.length >= 4 && recruitmentLevenshtein(inputLower, fw) <= 2;
                    });
                    if (fuzzy.length > 0) {
                        matches = fuzzy.slice(0, 12);
                    }
                }
                
                if (matches.length > 0) {
                    showDropdown(matches);
                } else {
                    hideDropdown();
                }
                
                matchedCountry = allCountries.find(c => c.toLowerCase() === inputLower);
                
                if (!matchedCountry && inputLower.length >= 4) {
                    matchedCountry = allCountries.find(c => {
                        const fw = c.toLowerCase().split(/\s+/)[0];
                        return fw.length >= 4 && recruitmentLevenshtein(inputLower, fw) <= 2;
                    });
                }
                
                if (!matchedCountry && matches.length === 1) {
                    matchedCountry = matches[0];
                }
            } else {
                hideDropdown();
                
                if (isExactMatch) {
                    matchedCountry = allCountries.find(c => c.toLowerCase() === inputLower);
                    if (!matchedCountry) {
                        const canon = this.resolveRecruitmentCountryName(countryName, allCountries);
                        matchedCountry = allCountries.find(c => c.toLowerCase() === String(canon).toLowerCase()) || null;
                    }
                }
            }
            
            // Only proceed if we have a valid country match or exact input
            if (!matchedCountry && countryName.length < 3) {
                // Clear city field if less than 3 characters
                const cityInput = qForm('#city_select') || document.getElementById('city_select');
                if (cityInput) {
                    cityInput.value = '';
                    cityInput.placeholder = 'Type at least 3 letters to see cities';
                }
                isProcessing = false;
                return;
            }
            
            // Use matched country or the input if it's a valid country name
            const finalCountryName = matchedCountry || countryName;
            
            // Only process if it's an exact match or user selected from dropdown
            if (!isExactMatch && !matchedCountry) {
                isProcessing = false;
                return;
            }
            
            isProcessing = true;
            
            try {
                // Get currency and flag from mapping
                const countryData = this.getCountryData(finalCountryName);
                
                // Auto-fill code (first 3 letters of country name, uppercase)
                const codeField = qForm('input[name="code"]');
                if (codeField) {
                    const code = countryData.code || finalCountryName.substring(0, 3).toUpperCase();
                    codeField.value = code;
                }
                
                // Auto-select currency from dropdown
                const currencyField = qForm('select[name="currency"]') || qForm('input[name="currency"]');
                if (currencyField && countryData.currency) {
                    // If it's a select dropdown, find and select the matching option
                    if (currencyField.tagName === 'SELECT') {
                        const currencyCode = countryData.currency.toUpperCase();
                        let option = Array.from(currencyField.options).find(opt => 
                            opt.value && opt.value.toUpperCase() === currencyCode
                        );
                        if (!option) {
                            option = Array.from(currencyField.options).find(opt => 
                                opt.textContent && opt.textContent.toUpperCase().includes(currencyCode)
                            );
                        }
                        if (option) {
                            currencyField.value = option.value;
                        } else {
                            // Option not in list (e.g. from getCountryData only): add it so value displays correctly
                            const newOpt = document.createElement('option');
                            newOpt.value = currencyCode;
                            newOpt.textContent = currencyCode;
                            currencyField.appendChild(newOpt);
                            currencyField.value = currencyCode;
                        }
                    } else {
                        // Fallback for text input (shouldn't happen but just in case)
                        currencyField.value = countryData.currency;
                    }
                }
                
                // Auto-fill flag emoji
                const flagField = qForm('input[name="flag_emoji"]');
                if (flagField && countryData.flag) {
                    flagField.value = countryData.flag;
                }
                
                // Populate city dropdown for recruitment_countries form (only if we have a match or valid country)
                if (matchedCountry || countryName.length >= 3) {
                    const cityInputEl = qForm('#city_select') || document.getElementById('city_select');
                    if (cityInputEl) {
                        cityInputEl.value = ''; // Clear city so user can select from dropdown
                    }
                    await this.populateRecruitmentCityDropdown(finalCountryName);
                    if (!countryInput.isConnected) return;
                    lastProcessedValue = finalCountryName;
                    hideDropdown(); // Hide dropdown after selection
                }
            } catch (error) {
                console.error('Error processing country change:', error);
            } finally {
                isProcessing = false;
            }
        };
        
        // Use 'input' event for text input (fires on typing)
        countryInput.addEventListener('input', (e) => {
            const inputValue = e.target.value.trim();
            selectedIndex = -1; // Reset selection
            
            // Clear previous timer
            clearTimeout(debounceTimer);
            
            // If user typed at least 3 characters, show dropdown and process
            if (inputValue.length >= 3) {
                handleCountryChange(inputValue);
            } else if (inputValue.length === 0) {
                // Clear fields immediately if input is empty
                handleCountryChange('');
            } else {
                // For 1-2 characters, hide dropdown and wait
                hideDropdown();
                debounceTimer = setTimeout(() => {
                    if (inputValue.length < 3) {
                        handleCountryChange(inputValue);
                    }
                }, 300);
            }
        });
        
        // Handle keyboard navigation
        countryInput.addEventListener('keydown', (e) => {
            const options = customDropdown.querySelectorAll('.autocomplete-option');
            if (options.length === 0) return;
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, options.length - 1);
                options[selectedIndex]?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                options.forEach((opt, idx) => {
                    opt.classList.toggle('highlighted', idx === selectedIndex);
                });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, -1);
                options.forEach((opt, idx) => {
                    opt.classList.toggle('highlighted', idx === selectedIndex);
                });
            } else if (e.key === 'Enter' && selectedIndex >= 0) {
                e.preventDefault();
                const selectedOption = options[selectedIndex];
                if (selectedOption) {
                    selectCountry(selectedOption.textContent);
                }
            } else if (e.key === 'Escape') {
                hideDropdown();
            }
        });
        
        // Handle focus event to reload countries if needed
        countryInput.addEventListener('focus', async () => {
            // Ensure countries are loaded from preloaded data
            if (allCountries.length === 0) {
                await loadCountriesForAutocomplete();
            }
            // Show dropdown if input has 3+ characters
            if (countryInput.value.trim().length >= 3) {
                const inputValue = countryInput.value.trim();
                const inputLower = inputValue.toLowerCase();
                let matches = allCountries.filter(c => c.toLowerCase().startsWith(inputLower));
                if (matches.length === 0 && inputLower.length >= 4) {
                    const fuzzy = allCountries.filter(c => {
                        const fw = c.toLowerCase().split(/\s+/)[0];
                        return fw.length >= 4 && recruitmentLevenshtein(inputLower, fw) <= 2;
                    });
                    if (fuzzy.length > 0) matches = fuzzy.slice(0, 12);
                }
                if (matches.length > 0) {
                    showDropdown(matches);
                }
            }
        });
        
        // On blur: resolve typos to canonical country name so save matches the list
        countryInput.addEventListener('blur', () => {
            clearTimeout(debounceTimer);
            setTimeout(async () => {
                if (!countryInput.isConnected) return;
                const raw = countryInput.value.trim();
                if (raw.length < 3) return;
                if (allCountries.length === 0) await loadCountriesForAutocomplete();
                const resolved = this.resolveRecruitmentCountryName(raw, allCountries);
                if (resolved && resolved !== raw && allCountries.some(c => c.toLowerCase() === resolved.toLowerCase())) {
                    countryInput.value = resolved;
                    await handleCountryChange(resolved, true);
                }
            }, 220);
        });
        
        // Hide dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!formGroup.contains(e.target) && !customDropdown.contains(e.target)) {
                hideDropdown();
            }
        });
        
        // Also handle 'change' event for datalist selection (when user selects from dropdown)
        countryInput.addEventListener('change', (e) => {
            clearTimeout(debounceTimer);
            handleCountryChange(e.target.value, true);
        });
    }
    
    // Setup city dropdown listener for recruitment_countries form
    setupRecruitmentCityDropdownListener() {
        const cityInput = document.getElementById('city_select');
        if (!cityInput) return;
        
        // Create custom dropdown container for city
        const formGroup = cityInput.closest('.form-group');
        if (!formGroup) return;
        
        // Create dropdown element if it doesn't exist
        let cityDropdown = document.getElementById('city_autocomplete_dropdown');
        if (!cityDropdown) {
            cityDropdown = document.createElement('div');
            cityDropdown.id = 'city_autocomplete_dropdown';
            cityDropdown.className = 'country-autocomplete-dropdown';
            formGroup.style.position = 'relative';
            formGroup.appendChild(cityDropdown);
        }
        
        // Cities for the SELECTED country only (populated when user selects a country)
        let allCities = [];
        let isProcessingCity = false;
        let selectedCityIndex = -1;
        let currentCountryForCities = null; // Track which country the city list is for
        
        // Load cities for a specific country from preloaded data (called when country is selected)
        const loadCitiesForSelectedCountry = async (countryName) => {
            try {
                if (!countryName || !countryName.trim()) {
                    allCities = [];
                    currentCountryForCities = null;
                    return;
                }
                countryName = countryName.trim();
                if (this._allCountriesAndCitiesPreloaded && this._allCountriesAndCitiesData) {
                    const citiesByCountry = this._allCountriesAndCitiesData.citiesByCountry || {};
                    // Match country case-insensitively
                    const key = Object.keys(citiesByCountry).find(c => c.toLowerCase() === countryName.toLowerCase());
                    allCities = key ? (citiesByCountry[key] || []).slice().sort() : [];
                    currentCountryForCities = key || countryName;
                } else {
                    const preloadedData = await this.preloadAllCountriesAndCities();
                    const citiesByCountry = preloadedData.citiesByCountry || {};
                    const key = Object.keys(citiesByCountry).find(c => c.toLowerCase() === countryName.toLowerCase());
                    allCities = key ? (citiesByCountry[key] || []).slice().sort() : [];
                    currentCountryForCities = key || countryName;
                }
            } catch (e) {
                console.error('Failed to load cities for country:', e);
                allCities = [];
                currentCountryForCities = null;
            }
        };
        
        // Fallback: load all cities (used only when no country selected yet)
        const loadAllCitiesForAutocomplete = async () => {
            try {
                if (this._allCountriesAndCitiesPreloaded && this._allCountriesAndCitiesData) {
                    allCities = this._allCountriesAndCitiesData.cities || [];
                } else {
                    const preloadedData = await this.preloadAllCountriesAndCities();
                    allCities = preloadedData.cities || [];
                }
            } catch (e) {
                console.error('Failed to load cities for autocomplete:', e);
            }
        };
        
        // Show dropdown with matching cities
        const showCityDropdown = (matches) => {
            if (!cityDropdown || !cityDropdown.isConnected) return;
            if (!cityInput || !cityInput.isConnected) return;
            
            if (matches.length === 0) {
                cityDropdown.classList.remove('show');
                return;
            }
            
            cityDropdown.innerHTML = '';
            cityDropdown.style.display = 'block';
            cityDropdown.classList.add('show');
            
            // Create scrollable container
            const scrollContainer = document.createElement('div');
            scrollContainer.className = 'autocomplete-scroll-container';
            
            matches.forEach((city, index) => {
                const option = document.createElement('div');
                option.className = 'autocomplete-option';
                option.dataset.index = index;
                option.textContent = city;
                
                option.addEventListener('click', () => {
                    selectCity(city);
                });
                
                option.addEventListener('mouseenter', () => {
                    // Remove highlight from all options
                    cityDropdown.querySelectorAll('.autocomplete-option').forEach(opt => {
                        opt.classList.remove('highlighted');
                    });
                    // Highlight current option
                    option.classList.add('highlighted');
                    selectedCityIndex = index;
                });
                
                scrollContainer.appendChild(option);
            });
            
            cityDropdown.appendChild(scrollContainer);
            
            // Position dropdown below input
            const rect = cityInput.getBoundingClientRect();
            cityDropdown.style.top = `${cityInput.offsetHeight + 4}px`;
            cityDropdown.style.left = '0';
            cityDropdown.style.width = `${cityInput.offsetWidth}px`;
        };
        
        // Hide dropdown
        const hideCityDropdown = () => {
            if (cityDropdown) {
                cityDropdown.classList.remove('show');
                cityDropdown.style.display = 'none';
                selectedCityIndex = -1;
            }
        };
        
        // Select a city
        const selectCity = (cityName) => {
            cityInput.value = cityName;
            hideCityDropdown();
        };
        
        // Update cities list when country is selected - show only that country's cities in dropdown
        const updateCitiesList = async (countryName) => {
            if (!cityInput || !cityInput.isConnected) return;
            try {
                if (countryName && countryName.trim()) {
                    await loadCitiesForSelectedCountry(countryName.trim());
                    cityInput.placeholder = allCities.length > 0
                        ? 'Type city name or select from list'
                        : 'Type city name (no cities in list for this country)';
                } else {
                    allCities = [];
                    currentCountryForCities = null;
                    cityInput.placeholder = 'Select country first';
                }
            } catch (e) {
                console.error('Failed to load cities:', e);
                allCities = [];
                currentCountryForCities = null;
            }
        };
        
        // Store update function to be called when country changes
        this.updateCityAutocompleteList = updateCitiesList;
        
        // Handle city input with autocomplete (filter by typed text when 3+ chars, or show all cities for selected country)
        let cityDebounceTimer;
        
        const handleCityChange = async (cityName) => {
            if (isProcessingCity) return;
            
            if (!cityName || cityName.trim() === '') {
                hideCityDropdown();
                return;
            }
            
            cityName = cityName.trim();
            
            // If user typed at least 3 characters, filter and show matching cities
            if (cityName.length >= 3 && allCities.length > 0) {
                const inputLower = cityName.toLowerCase();
                const matches = allCities.filter(c => c.toLowerCase().startsWith(inputLower));
                
                if (matches.length > 0) {
                    showCityDropdown(matches);
                } else {
                    hideCityDropdown();
                }
            } else {
                hideCityDropdown();
            }
        };
        
        // Use 'input' event for text input (fires on typing)
        cityInput.addEventListener('input', (e) => {
            const inputValue = e.target.value.trim();
            selectedCityIndex = -1; // Reset selection
            
            // Clear previous timer
            clearTimeout(cityDebounceTimer);
            
            // If user typed at least 3 characters, show dropdown and process
            if (inputValue.length >= 3) {
                handleCityChange(inputValue);
            } else if (inputValue.length === 0) {
                // Hide dropdown if input is empty
                hideCityDropdown();
            } else {
                // For 1-2 characters, hide dropdown and wait
                hideCityDropdown();
                cityDebounceTimer = setTimeout(() => {
                    if (inputValue.length < 3) {
                        handleCityChange(inputValue);
                    }
                }, 300);
            }
        });
        
        // Handle keyboard navigation
        cityInput.addEventListener('keydown', (e) => {
            const options = cityDropdown.querySelectorAll('.autocomplete-option');
            if (options.length === 0) return;
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedCityIndex = Math.min(selectedCityIndex + 1, options.length - 1);
                options[selectedCityIndex]?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                options.forEach((opt, idx) => {
                    opt.classList.toggle('highlighted', idx === selectedCityIndex);
                });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedCityIndex = Math.max(selectedCityIndex - 1, -1);
                options.forEach((opt, idx) => {
                    opt.classList.toggle('highlighted', idx === selectedCityIndex);
                });
            } else if (e.key === 'Enter' && selectedCityIndex >= 0) {
                e.preventDefault();
                const selectedOption = options[selectedCityIndex];
                if (selectedOption) {
                    selectCity(selectedOption.textContent);
                }
            } else if (e.key === 'Escape') {
                hideCityDropdown();
            }
        });
        
        // Handle focus: if a country is selected, show city dropdown with that country's cities so user can select
        cityInput.addEventListener('focus', async () => {
            const countryInputEl = document.getElementById('country_name_select');
            const selectedCountry = countryInputEl ? countryInputEl.value.trim() : '';
            
            // If country is selected but we don't have cities for it yet, load them
            if (selectedCountry && allCities.length === 0) {
                await loadCitiesForSelectedCountry(selectedCountry);
                if (!cityInput.isConnected) return;
            }
            
            // If we have cities (for the selected country), show dropdown so user can select
            if (allCities.length > 0) {
                const inputValue = cityInput.value.trim();
                if (inputValue.length >= 3) {
                    const inputLower = inputValue.toLowerCase();
                    const matches = allCities.filter(c => c.toLowerCase().startsWith(inputLower));
                    if (matches.length > 0) {
                        showCityDropdown(matches);
                    } else {
                        showCityDropdown(allCities);
                    }
                } else {
                    showCityDropdown(allCities); // Show all cities for selected country
                }
            }
        });
        
        // Hide dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!formGroup.contains(e.target) && !cityDropdown.contains(e.target)) {
                hideCityDropdown();
            }
        });
        
        // Also handle 'change' event for datalist selection
        cityInput.addEventListener('change', (e) => {
            clearTimeout(cityDebounceTimer);
            handleCityChange(e.target.value);
        });
    }
    
    // Populate city dropdown/datalist for recruitment_countries form
    async populateRecruitmentCityDropdown(countryName) {
        const cityInput = document.getElementById('city_select');
        const cityDatalist = document.getElementById('city_datalist');
        
        if (!cityInput || !countryName) return;
        
        try {
            // Load cities from API
            const cities = await this.loadCitiesForCountry(countryName);
            
            // Update cities list for autocomplete if function exists
            if (typeof this.updateCityAutocompleteList === 'function') {
                await this.updateCityAutocompleteList(countryName);
            }
            
            // Clear existing options in datalist
            if (cityDatalist) {
                cityDatalist.innerHTML = '<option value="">Select City...</option>';
                
                if (cities && cities.length > 0) {
                    cities.forEach(city => {
                        const option = document.createElement('option');
                        option.value = city;
                        cityDatalist.appendChild(option);
                    });
                } else {
                    // Add a placeholder option
                    const placeholderOption = document.createElement('option');
                    placeholderOption.value = '';
                    placeholderOption.textContent = 'No cities available. Type a new city name.';
                    cityDatalist.appendChild(placeholderOption);
                }
            }
            
            // Update placeholder text
            if (cities && cities.length > 0) {
                cityInput.placeholder = 'Type city name or select from list';
            } else {
                cityInput.placeholder = 'Type city name (no existing cities for this country)';
            }
        } catch (e) {
            console.error('Failed to populate cities for recruitment countries:', e);
            if (cityDatalist) {
                cityDatalist.innerHTML = '<option value="">Error loading cities</option>';
            }
            if (cityInput) {
                cityInput.placeholder = 'Type city name';
            }
        }
    }
    
    // Populate currency dropdown from currencies table
    async populateCurrencyDropdown(maxRetries = 3) {
        const currencySelect = document.getElementById('currency_select');
        if (!currencySelect) {
            if (maxRetries > 0) {
                // Try again after a delay
                setTimeout(() => this.populateCurrencyDropdown(maxRetries - 1), 300);
                return;
            } else {
                // Currency select element not found
                return;
            }
        }
        
        try {
            // Try to use currencyUtils if available
            if (window.currencyUtils && typeof window.currencyUtils.populateCurrencySelect === 'function') {
                await window.currencyUtils.populateCurrencySelect(currencySelect);
                return;
            }
            
            // Fallback: fetch currencies directly from API
            const apiBase = getApiBaseModernForms();
            const response = await fetch(apiBase + '/settings/currencies-api.php' + getControlSuffixModernForms());
            if (!response.ok) {
                throw new Error('Failed to fetch currencies');
            }
            
            const data = await response.json();
            if (data.success && Array.isArray(data.currencies)) {
                currencySelect.innerHTML = '<option value="">Select Currency...</option>';
                data.currencies.forEach(currency => {
                    const option = document.createElement('option');
                    option.value = currency.code;
                    option.textContent = currency.label || `${currency.code} - ${currency.name}`;
                    currencySelect.appendChild(option);
                });
            } else {
                currencySelect.innerHTML = '<option value="">No currencies available. Please add currencies in System Settings.</option>';
            }
        } catch (e) {
            console.error('Failed to populate currency dropdown:', e);
            currencySelect.innerHTML = '<option value="">Error loading currencies</option>';
        }
    }
    
    // Populate copy_from_currency dropdown (shows all currencies including inactive)
    async populateCopyFromCurrencyDropdown(maxRetries = 3) {
        const copyFromSelect = document.getElementById('copy_from_currency_select');
        if (!copyFromSelect) {
            if (maxRetries > 0) {
                // Try again after a delay
                setTimeout(() => this.populateCopyFromCurrencyDropdown(maxRetries - 1), 300);
                return;
            } else {
                // Copy from currency select element not found
                return;
            }
        }
        
        try {
            // Fetch all currencies (including inactive) for copying
            const apiBase = getApiBaseModernForms();
            const response = await fetch(apiBase + '/settings/currencies-api.php?all=true' + getControlSuffixModernForms());
            if (!response.ok) {
                throw new Error('Failed to fetch currencies');
            }
            
            const data = await response.json();
            if (data.success && Array.isArray(data.currencies)) {
                copyFromSelect.innerHTML = '<option value="">Select Currency to Copy From (Optional)...</option>';
                data.currencies.forEach(currency => {
                    const option = document.createElement('option');
                    option.value = currency.code;
                    option.textContent = currency.label || `${currency.code} - ${currency.name}`;
                    option.setAttribute('data-name', currency.name);
                    option.setAttribute('data-symbol', currency.symbol || '');
                    copyFromSelect.appendChild(option);
                });
            } else {
                copyFromSelect.innerHTML = '<option value="">No currencies available</option>';
            }
        } catch (e) {
            console.error('Failed to populate copy from currency dropdown:', e);
            copyFromSelect.innerHTML = '<option value="">Error loading currencies</option>';
        }
    }
    
    // Setup listener for copy_from_currency dropdown to auto-fill form fields
    setupCopyFromCurrencyListener() {
        const copyFromSelect = document.getElementById('copy_from_currency_select');
        if (!copyFromSelect) return;
        
        copyFromSelect.addEventListener('change', (e) => {
            const selectedCode = e.target.value;
            if (!selectedCode) return;
            
            const selectedOption = copyFromSelect.options[copyFromSelect.selectedIndex];
            if (!selectedOption) return;
            
            const currencyName = selectedOption.getAttribute('data-name') || '';
            const currencySymbol = selectedOption.getAttribute('data-symbol') || '';
            
            // Auto-fill currency code (but don't overwrite if already filled)
            const codeField = document.querySelector('input[name="code"]');
            if (codeField && !codeField.value.trim()) {
                codeField.value = selectedCode;
            }
            
            // Auto-fill currency name
            const nameField = document.querySelector('input[name="name"]');
            if (nameField && !nameField.value.trim()) {
                nameField.value = currencyName;
            }
            
            // Auto-fill currency symbol
            const symbolField = document.querySelector('input[name="symbol"]');
            if (symbolField && !symbolField.value.trim()) {
                symbolField.value = currencySymbol;
            }
        });
    }
    
    // Setup listener for country dropdown in currencies form to auto-fill currency fields
    setupCurrencyCountryListener() {
        const countrySelect = document.getElementById('country_id_select');
        if (!countrySelect) return;
        
        countrySelect.addEventListener('change', async (e) => {
            const selectedCountryId = e.target.value;
            console.log('Country selected:', selectedCountryId);
            if (!selectedCountryId) {
                // Clear fields if no country selected
                const codeField = document.querySelector('input[name="code"]');
                const nameField = document.querySelector('input[name="name"]');
                const symbolField = document.querySelector('input[name="symbol"]');
                if (codeField) codeField.value = '';
                if (nameField) nameField.value = '';
                if (symbolField) symbolField.value = '';
                return;
            }
            
            try {
                // Get country name from the selected option
                const selectedOption = countrySelect.options[countrySelect.selectedIndex];
                let countryName = selectedOption ? selectedOption.textContent.trim() : '';
                
                // Also try getting from data attribute if available
                if (!countryName && selectedOption) {
                    countryName = selectedOption.getAttribute('data-name') || 
                                  selectedOption.getAttribute('data-country-name') || 
                                  '';
                }
                
                // Remove any extra whitespace and normalize
                countryName = countryName.trim();
                
                console.log('Extracted country name:', countryName);
                
                // Get currency data from country mapping (primary source)
                const currencyData = this.getCountryData(countryName);
                let currencyCode = currencyData && currencyData.currency ? currencyData.currency : '';
                
                console.log('Currency data found:', currencyData);
                console.log('Currency code:', currencyCode);
                
                // Debug: log what we found
                if (!currencyCode && countryName) {
                    console.warn('Country not found in mapping or no currency:', countryName);
                }
                
                // Try to get currency from database if country ID is numeric and exists
                // Only if we don't have currency from mapping
                if (!currencyCode && /^\d+$/.test(selectedCountryId)) {
                    try {
                        const response = await this.apiCall('get_by_id', 'recruitment_countries', { id: selectedCountryId });
                        if (response.success && response.data && response.data.currency) {
                            currencyCode = response.data.currency;
                        }
                    } catch (dbError) {
                        // Silently ignore - we'll use mapping data
                    }
                }
                
                let currencyName = '';
                let currencySymbol = '';
                
                // If we have currency code, try to get currency details from currencies table
                if (currencyCode) {
                    try {
                        const currenciesResponse = await this.apiCall('get_all', 'currencies');
                        if (currenciesResponse.success && Array.isArray(currenciesResponse.data)) {
                            const existingCurrency = currenciesResponse.data.find(c => 
                                (c.code && c.code.toUpperCase() === currencyCode.toUpperCase()) ||
                                (c.currency_code && c.currency_code.toUpperCase() === currencyCode.toUpperCase())
                            );
                            if (existingCurrency) {
                                currencyName = existingCurrency.name || existingCurrency.currency_name || '';
                                currencySymbol = existingCurrency.symbol || existingCurrency.currency_symbol || '';
                            }
                        }
                    } catch (e) {
                        // If fetching fails, use defaults from mapping
                        console.warn('Could not fetch currency details:', e);
                    }
                }
                
                // If currency name/symbol not found, use defaults from currency code
                if (!currencyName && currencyCode) {
                    // Common currency names mapping (comprehensive list)
                    const currencyNames = {
                        'SAR': 'Saudi Riyal', 'USD': 'US Dollar', 'EUR': 'Euro', 'GBP': 'British Pound',
                        'AED': 'UAE Dirham', 'KWD': 'Kuwaiti Dinar', 'QAR': 'Qatari Riyal', 'BHD': 'Bahraini Dinar',
                        'OMR': 'Omani Rial', 'EGP': 'Egyptian Pound', 'JOD': 'Jordanian Dinar', 'LBP': 'Lebanese Pound',
                        'ILS': 'Israeli Shekel', 'CAD': 'Canadian Dollar', 'MXN': 'Mexican Peso', 'BRL': 'Brazilian Real',
                        'ARS': 'Argentine Peso', 'CLP': 'Chilean Peso', 'COP': 'Colombian Peso', 'PEN': 'Peruvian Sol',
                        'CHF': 'Swiss Franc', 'PLN': 'Polish Zloty', 'CZK': 'Czech Koruna', 'DKK': 'Danish Krone',
                        'NOK': 'Norwegian Krone', 'SEK': 'Swedish Krona', 'JPY': 'Japanese Yen', 'CNY': 'Chinese Yuan',
                        'KRW': 'South Korean Won', 'INR': 'Indian Rupee', 'IDR': 'Indonesian Rupiah', 'MYR': 'Malaysian Ringgit',
                        'THB': 'Thai Baht', 'PHP': 'Philippine Peso', 'VND': 'Vietnamese Dong', 'SGD': 'Singapore Dollar',
                        'BDT': 'Bangladeshi Taka', 'PKR': 'Pakistani Rupee', 'LKR': 'Sri Lankan Rupee', 'ZAR': 'South African Rand',
                        'NGN': 'Nigerian Naira', 'KES': 'Kenyan Shilling', 'ETB': 'Ethiopian Birr', 'GHS': 'Ghanaian Cedi',
                        'MAD': 'Moroccan Dirham', 'DZD': 'Algerian Dinar', 'TND': 'Tunisian Dinar', 'TRY': 'Turkish Lira',
                        'AFN': 'Afghan Afghani', 'AOA': 'Angolan Kwanza', 'AUD': 'Australian Dollar', 'AZN': 'Azerbaijani Manat',
                        'BGN': 'Bulgarian Lev', 'BYN': 'Belarusian Ruble', 'GEL': 'Georgian Lari', 'HUF': 'Hungarian Forint',
                        'ISK': 'Icelandic Krona', 'KGS': 'Kyrgyzstani Som', 'KZT': 'Kazakhstani Tenge', 'MDL': 'Moldovan Leu',
                        'MKD': 'Macedonian Denar', 'MZN': 'Mozambican Metical', 'MGA': 'Malagasy Ariary', 'NZD': 'New Zealand Dollar',
                        'RON': 'Romanian Leu', 'RSD': 'Serbian Dinar', 'RUB': 'Russian Ruble', 'UAH': 'Ukrainian Hryvnia',
                        'UGX': 'Ugandan Shilling', 'UZS': 'Uzbekistani Som', 'XAF': 'Central African CFA Franc',
                        'XOF': 'West African CFA Franc', 'ZMW': 'Zambian Kwacha', 'ZWL': 'Zimbabwean Dollar'
                    };
                    currencyName = currencyNames[currencyCode.toUpperCase()] || '';
                    console.log('Currency name from mapping:', currencyName);
                }
                
                console.log('Final currency name to set:', currencyName);
                
                if (!currencySymbol && currencyCode) {
                    // Common currency symbols mapping
                    const currencySymbols = {
                        'SAR': '﷼', 'USD': '$', 'EUR': '€', 'GBP': '£', 'AED': 'د.إ', 'KWD': 'د.ك',
                        'QAR': 'ر.ق', 'BHD': 'د.ب', 'OMR': 'ر.ع.', 'EGP': '£', 'JOD': 'د.ا', 'LBP': 'ل.ل',
                        'ILS': '₪', 'CAD': 'C$', 'MXN': '$', 'BRL': 'R$', 'ARS': '$', 'CLP': '$', 'COP': '$',
                        'PEN': 'S/', 'CHF': 'CHF', 'PLN': 'zł', 'CZK': 'Kč', 'DKK': 'kr', 'NOK': 'kr', 'SEK': 'kr',
                        'JPY': '¥', 'CNY': '¥', 'KRW': '₩', 'INR': '₹', 'IDR': 'Rp', 'MYR': 'RM', 'THB': '฿',
                        'PHP': '₱', 'VND': '₫', 'SGD': 'S$', 'BDT': '৳', 'PKR': '₨', 'LKR': 'Rs', 'ZAR': 'R',
                        'NGN': '₦', 'KES': 'KSh', 'ETB': 'Br', 'GHS': '₵', 'MAD': 'د.م.', 'DZD': 'د.ج', 'TND': 'د.ت',
                        'TRY': '₺'
                    };
                    currencySymbol = currencySymbols[currencyCode.toUpperCase()] || '';
                }
                
                // Auto-fill currency code (always update when country is selected)
                const codeField = document.querySelector('input[name="code"]');
                if (codeField && currencyCode) {
                    codeField.value = currencyCode.toUpperCase();
                }
                
                // Auto-fill currency name (always update when country is selected)
                const nameField = document.querySelector('input[name="name"]');
                console.log('Name field found:', nameField, 'Currency name to set:', currencyName);
                if (nameField) {
                    if (currencyName) {
                        nameField.value = currencyName;
                        console.log('✅ Currency name set to:', currencyName);
                    } else {
                        console.warn('⚠️ Currency name is empty for currency code:', currencyCode);
                    }
                } else {
                    console.error('❌ Currency name field (input[name="name"]) not found!');
                }
                
                // Auto-fill currency symbol (always update when country is selected)
                const symbolField = document.querySelector('input[name="symbol"]');
                if (symbolField && currencySymbol) {
                    symbolField.value = currencySymbol;
                }
                
                // Set default display order if empty (use 0 as default)
                const displayOrderField = document.querySelector('input[name="display_order"]');
                if (displayOrderField && !displayOrderField.value.trim()) {
                    displayOrderField.value = '0';
                }
                
                // Set default status to 'active' if not already set
                const statusField = document.querySelector('select[name="status"]');
                if (statusField && !statusField.value) {
                    statusField.value = 'active';
                }
                
            } catch (error) {
                console.error('Error fetching country currency data:', error);
            }
        });
    }
    
    // Get country data (currency, flag, code) - comprehensive mapping for all countries
    getCountryData(countryName) {
        const countryMap = {
            // Middle East & North Africa
            'Saudi Arabia': { code: 'SAU', currency: 'SAR', flag: '🇸🇦' },
            'United Arab Emirates': { code: 'UAE', currency: 'AED', flag: '🇦🇪' },
            'Kuwait': { code: 'KWT', currency: 'KWD', flag: '🇰🇼' },
            'Qatar': { code: 'QAT', currency: 'QAR', flag: '🇶🇦' },
            'Bahrain': { code: 'BHR', currency: 'BHD', flag: '🇧🇭' },
            'Oman': { code: 'OMN', currency: 'OMR', flag: '🇴🇲' },
            'Egypt': { code: 'EGY', currency: 'EGP', flag: '🇪🇬' },
            'Jordan': { code: 'JOR', currency: 'JOD', flag: '🇯🇴' },
            'Lebanon': { code: 'LBN', currency: 'LBP', flag: '🇱🇧' },
            'Israel': { code: 'ISR', currency: 'ILS', flag: '🇮🇱' },
            'Palestine': { code: 'PSE', currency: 'ILS', flag: '🇵🇸' },
            
            // North & South America
            'United States': { code: 'USA', currency: 'USD', flag: '🇺🇸' },
            'Canada': { code: 'CAN', currency: 'CAD', flag: '🇨🇦' },
            'Mexico': { code: 'MEX', currency: 'MXN', flag: '🇲🇽' },
            'Brazil': { code: 'BRA', currency: 'BRL', flag: '🇧🇷' },
            'Argentina': { code: 'ARG', currency: 'ARS', flag: '🇦🇷' },
            'Chile': { code: 'CHL', currency: 'CLP', flag: '🇨🇱' },
            'Colombia': { code: 'COL', currency: 'COP', flag: '🇨🇴' },
            'Peru': { code: 'PER', currency: 'PEN', flag: '🇵🇪' },
            
            // Europe
            'United Kingdom': { code: 'GBR', currency: 'GBP', flag: '🇬🇧' },
            'France': { code: 'FRA', currency: 'EUR', flag: '🇫🇷' },
            'Germany': { code: 'DEU', currency: 'EUR', flag: '🇩🇪' },
            'Italy': { code: 'ITA', currency: 'EUR', flag: '🇮🇹' },
            'Spain': { code: 'ESP', currency: 'EUR', flag: '🇪🇸' },
            'Netherlands': { code: 'NLD', currency: 'EUR', flag: '🇳🇱' },
            'Belgium': { code: 'BEL', currency: 'EUR', flag: '🇧🇪' },
            'Switzerland': { code: 'CHE', currency: 'CHF', flag: '🇨🇭' },
            'Austria': { code: 'AUT', currency: 'EUR', flag: '🇦🇹' },
            'Poland': { code: 'POL', currency: 'PLN', flag: '🇵🇱' },
            'Portugal': { code: 'PRT', currency: 'EUR', flag: '🇵🇹' },
            'Greece': { code: 'GRC', currency: 'EUR', flag: '🇬🇷' },
            'Albania': { code: 'ALB', currency: 'ALL', flag: '🇦🇱' },
            'Armenia': { code: 'ARM', currency: 'AMD', flag: '🇦🇲' },
            'Azerbaijan': { code: 'AZE', currency: 'AZN', flag: '🇦🇿' },
            'Belarus': { code: 'BLR', currency: 'BYN', flag: '🇧🇾' },
            'Bulgaria': { code: 'BGR', currency: 'BGN', flag: '🇧🇬' },
            'Croatia': { code: 'HRV', currency: 'EUR', flag: '🇭🇷' },
            'Czech Republic': { code: 'CZE', currency: 'CZK', flag: '🇨🇿' },
            'Denmark': { code: 'DNK', currency: 'DKK', flag: '🇩🇰' },
            'Estonia': { code: 'EST', currency: 'EUR', flag: '🇪🇪' },
            'Finland': { code: 'FIN', currency: 'EUR', flag: '🇫🇮' },
            'Georgia': { code: 'GEO', currency: 'GEL', flag: '🇬🇪' },
            'Hungary': { code: 'HUN', currency: 'HUF', flag: '🇭🇺' },
            'Iceland': { code: 'ISL', currency: 'ISK', flag: '🇮🇸' },
            'Ireland': { code: 'IRL', currency: 'EUR', flag: '🇮🇪' },
            'Kazakhstan': { code: 'KAZ', currency: 'KZT', flag: '🇰🇿' },
            'Kyrgyzstan': { code: 'KGZ', currency: 'KGS', flag: '🇰🇬' },
            'Latvia': { code: 'LVA', currency: 'EUR', flag: '🇱🇻' },
            'Lithuania': { code: 'LTU', currency: 'EUR', flag: '🇱🇹' },
            'Moldova': { code: 'MDA', currency: 'MDL', flag: '🇲🇩' },
            'North Macedonia': { code: 'MKD', currency: 'MKD', flag: '🇲🇰' },
            'Norway': { code: 'NOR', currency: 'NOK', flag: '🇳🇴' },
            'Romania': { code: 'ROU', currency: 'RON', flag: '🇷🇴' },
            'Russia': { code: 'RUS', currency: 'RUB', flag: '🇷🇺' },
            'Serbia': { code: 'SRB', currency: 'RSD', flag: '🇷🇸' },
            'Slovakia': { code: 'SVK', currency: 'EUR', flag: '🇸🇰' },
            'Slovenia': { code: 'SVN', currency: 'EUR', flag: '🇸🇮' },
            'Sweden': { code: 'SWE', currency: 'SEK', flag: '🇸🇪' },
            'Ukraine': { code: 'UKR', currency: 'UAH', flag: '🇺🇦' },
            'Uzbekistan': { code: 'UZB', currency: 'UZS', flag: '🇺🇿' },
            'Turkey': { code: 'TUR', currency: 'TRY', flag: '🇹🇷' },
            
            // Asia
            'China': { code: 'CHN', currency: 'CNY', flag: '🇨🇳' },
            'Japan': { code: 'JPN', currency: 'JPY', flag: '🇯🇵' },
            'South Korea': { code: 'KOR', currency: 'KRW', flag: '🇰🇷' },
            'India': { code: 'IND', currency: 'INR', flag: '🇮🇳' },
            'Indonesia': { code: 'IDN', currency: 'IDR', flag: '🇮🇩' },
            'Malaysia': { code: 'MYS', currency: 'MYR', flag: '🇲🇾' },
            'Thailand': { code: 'THA', currency: 'THB', flag: '🇹🇭' },
            'Philippines': { code: 'PHL', currency: 'PHP', flag: '🇵🇭' },
            'Vietnam': { code: 'VNM', currency: 'VND', flag: '🇻🇳' },
            'Singapore': { code: 'SGP', currency: 'SGD', flag: '🇸🇬' },
            'Bangladesh': { code: 'BGD', currency: 'BDT', flag: '🇧🇩' },
            'Pakistan': { code: 'PAK', currency: 'PKR', flag: '🇵🇰' },
            'Afghanistan': { code: 'AFG', currency: 'AFN', flag: '🇦🇫' },
            'Sri Lanka': { code: 'LKA', currency: 'LKR', flag: '🇱🇰' },
            
            // Africa
            'South Africa': { code: 'ZAF', currency: 'ZAR', flag: '🇿🇦' },
            'Nigeria': { code: 'NGA', currency: 'NGN', flag: '🇳🇬' },
            'Kenya': { code: 'KEN', currency: 'KES', flag: '🇰🇪' },
            'Ethiopia': { code: 'ETH', currency: 'ETB', flag: '🇪🇹' },
            'Ghana': { code: 'GHA', currency: 'GHS', flag: '🇬🇭' },
            'Morocco': { code: 'MAR', currency: 'MAD', flag: '🇲🇦' },
            'Algeria': { code: 'DZA', currency: 'DZD', flag: '🇩🇿' },
            'Tunisia': { code: 'TUN', currency: 'TND', flag: '🇹🇳' },
            'Libya': { code: 'LBY', currency: 'LYD', flag: '🇱🇾' },
            'Sudan': { code: 'SDN', currency: 'SDG', flag: '🇸🇩' },
            'Tanzania': { code: 'TZA', currency: 'TZS', flag: '🇹🇿' },
            'Uganda': { code: 'UGA', currency: 'UGX', flag: '🇺🇬' },
            'Zambia': { code: 'ZMB', currency: 'ZMW', flag: '🇿🇲' },
            'Zimbabwe': { code: 'ZWE', currency: 'ZWL', flag: '🇿🇼' },
            'Mozambique': { code: 'MOZ', currency: 'MZN', flag: '🇲🇿' },
            'Madagascar': { code: 'MDG', currency: 'MGA', flag: '🇲🇬' },
            'Cameroon': { code: 'CMR', currency: 'XAF', flag: '🇨🇲' },
            'Ivory Coast': { code: 'CIV', currency: 'XOF', flag: '🇨🇮' },
            'Senegal': { code: 'SEN', currency: 'XOF', flag: '🇸🇳' },
            'Mali': { code: 'MLI', currency: 'XOF', flag: '🇲🇱' },
            'Burkina Faso': { code: 'BFA', currency: 'XOF', flag: '🇧🇫' },
            'Niger': { code: 'NER', currency: 'XOF', flag: '🇳🇪' },
            'Chad': { code: 'TCD', currency: 'XAF', flag: '🇹🇩' },
            'Angola': { code: 'AGO', currency: 'AOA', flag: '🇦🇴' },
            
            // Oceania
            'Australia': { code: 'AUS', currency: 'AUD', flag: '🇦🇺' },
            'New Zealand': { code: 'NZL', currency: 'NZD', flag: '🇳🇿' },
            'Papua New Guinea': { code: 'PNG', currency: 'PGK', flag: '🇵🇬' },
            'Fiji': { code: 'FJI', currency: 'FJD', flag: '🇫🇯' },
            'Samoa': { code: 'WSM', currency: 'WST', flag: '🇼🇸' },
            'Tonga': { code: 'TON', currency: 'TOP', flag: '🇹🇴' }
        };
        
        if (countryMap[countryName]) return countryMap[countryName];
        // Case-insensitive fallback (e.g. DB or API returns "sri lanka" instead of "Sri Lanka")
        const exact = countryName && typeof countryName === 'string' ? countryName.trim() : '';
        if (exact) {
            const key = Object.keys(countryMap).find(k => k.toLowerCase() === exact.toLowerCase());
            if (key) return countryMap[key];
        }
        return { code: (countryName && typeof countryName === 'string' ? countryName.substring(0, 3) : 'XXX').toUpperCase(), currency: '', flag: '' };
    }
    
    /**
     * Resolve user-typed country to canonical list name (typos, aliases). allCountries from preload.
     */
    resolveRecruitmentCountryName(input, allCountries) {
        if (!input || typeof input !== 'string') return '';
        let t = input.trim();
        if (!t) return '';
        const lower = t.toLowerCase();
        if (RECRUITMENT_COUNTRY_INPUT_ALIASES[lower]) return RECRUITMENT_COUNTRY_INPUT_ALIASES[lower];
        const list = Array.isArray(allCountries) ? allCountries : [];
        const exact = list.find(c => c.toLowerCase() === lower);
        if (exact) return exact;
        const starts = list.filter(c => c.toLowerCase().startsWith(lower));
        if (starts.length === 1) return starts[0];
        if (lower.length >= 4) {
            for (const c of list) {
                const fw = c.toLowerCase().split(/\s+/)[0];
                if (fw.length >= 4 && recruitmentLevenshtein(lower, fw) <= 2) return c;
            }
            const contains = list.filter(c => c.toLowerCase().includes(lower));
            if (contains.length === 1) return contains[0];
        }
        return t;
    }
    
    // Render edit form
    async renderEditForm(container, id) {
        // Check if we're in company info mode
        if (this.isCompanyInfoMode && this.currentTable === 'system_config') {
            await this.renderCompanyInfoForm(container, true);
            return;
        }
        
        try {
            const response = await this.apiCall('get_by_id', this.currentTable, { id });
            if (!response.success) {
                container.innerHTML = `<div class="error-state"><i class="fas fa-exclamation-triangle"></i><p>Failed to load record: ${response.message}</p></div>`;
                return;
            }
            
            const item = response.data;
            // Store item for status normalization
            this.currentItem = item;
            let formConfig = this.getFormConfig(this.currentTable);
            
            // Remove status field from users form when on profile page or dashboard profile modal
            const isProfilePage = window.location.pathname.includes('profile.php');
            const isDashboardPage = window.location.pathname.includes('dashboard.php');
            const isProfileModal = document.getElementById('modalTitle')?.textContent?.includes('My Profile') || 
                                  document.getElementById('formPopupTitle')?.textContent?.includes('Profile') ||
                                  document.getElementById('formPopupTitle')?.textContent?.includes('Edit Profile');
            
            if (this.currentTable === 'users' && (isProfilePage || (isDashboardPage && isProfileModal))) {
                formConfig = {
                    ...formConfig,
                    fields: formConfig.fields.filter(field => field.name !== 'status')
                };
            }
            
            // Get created/updated timestamps - use consistent en-US format (e.g. "Feb 11, 2026, 9:57:18 PM")
            const formatDateTime = (dateValue) => {
                if (!dateValue) return null;
                try {
                    const d = new Date(dateValue);
                    if (isNaN(d.getTime())) return null;
                    return new Intl.DateTimeFormat('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: true
                    }).format(d);
                } catch (e) {
                    return null;
                }
            };
            const createdAt = formatDateTime(item.created_at);
            const updatedAt = formatDateTime(item.updated_at);
            
            let html = `
                <form>
                    <input type="hidden" name="id" value="${id}">
                    <div class="form-grid">
                        ${formConfig.fields.map(field => {
                            let value = this.getFieldValue(item, field.name);
                            // For password field in edit mode: NEVER show stored password (security - API doesn't return it)
                            // Always show empty to avoid browser autofill showing old password
                            if (field.name === 'password' && this.currentAction === 'edit') {
                                value = ''; // Always empty - user enters new password or leaves blank to keep current
                                const fieldConfig = { ...field, required: false, placeholder: 'Enter new password (leave blank to keep current)', autocomplete: 'new-password' };
                                return this.renderFormField(fieldConfig, value);
                            }
                            return this.renderFormField(field, value);
                        }).join('')}
                        ${updatedAt ? `
                            <div class="form-group">
                                <label class="form-label-with-icon">
                                    <i class="fas fa-calendar-check"></i>
                                    <span>Updated</span>
                                </label>
                                <div class="form-readonly-value">${updatedAt}</div>
                            </div>
                        ` : ''}
                        ${createdAt ? `
                            <div class="form-group">
                                <label class="form-label-with-icon">
                                    <i class="fas fa-calendar-plus"></i>
                                    <span>Created</span>
                                </label>
                                <div class="form-readonly-value">${createdAt}</div>
                            </div>
                        ` : ''}
                    </div>
                    <div class="form-actions">
                        <button type="button" class="modern-btn modern-btn-secondary" data-action="close-form">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                        <button type="submit" class="modern-btn modern-btn-primary">
                            <i class="fas fa-save"></i>
                            Update
                        </button>
                    </div>
                </form>
            `;
            
            container.innerHTML = html;
            
            // Set selected option for select fields after rendering (no inline selected attribute)
            formConfig.fields.forEach(field => {
                if (field.type === 'select' && field.options) {
                    const selectEl = container.querySelector(`#${field.name}_select`);
                    if (selectEl) {
                        const fieldValue = this.getFieldValue(item, field.name);
                        if (fieldValue !== null && fieldValue !== undefined && fieldValue !== '') {
                            // Normalize status values
                            let normalizedValue = fieldValue;
                            if (field.name === 'status') {
                                if (fieldValue === 1 || fieldValue === '1' || fieldValue === true) {
                                    normalizedValue = 'active';
                                } else if (fieldValue === 0 || fieldValue === '0' || fieldValue === false) {
                                    normalizedValue = 'inactive';
                                }
                            }
                            const matchingOption = Array.from(selectEl.options).find(opt => 
                                String(opt.value).toLowerCase() === String(normalizedValue).toLowerCase() ||
                                (field.name === 'status' && (
                                    (normalizedValue === 'active' && (opt.value === 'active' || opt.value === 1 || opt.value === '1')) ||
                                    (normalizedValue === 'inactive' && (opt.value === 'inactive' || opt.value === 0 || opt.value === '0'))
                                ))
                            );
                            if (matchingOption) {
                                selectEl.value = matchingOption.value;
                            } else if (field.name === 'status' && field.options.length > 0) {
                                // Default to first option for status if no match found
                                selectEl.value = field.options[0].value;
                            }
                        } else if (field.name === 'status' && field.options.length > 0) {
                            // Default to first option for status if value is empty
                            selectEl.value = field.options[0].value;
                        }
                    }
                }
            });
            
            // Populate country and city dropdowns IMMEDIATELY (no delay for edit forms)
            // Use requestAnimationFrame for immediate execution after DOM update
            requestAnimationFrame(async () => {
                const formConfig = this.getFormConfig(this.currentTable);
                const hasCountryField = formConfig.fields.some(field => field.name === 'country_id');
                
                // Special handling for recruitment_countries - populate country name dropdown
                if (this.currentTable === 'recruitment_countries') {
                    // Preload ALL countries and cities in background for fast autocomplete
                    await this.preloadAllCountriesAndCities();
                    
                    await this.populateRecruitmentCountryDropdown();
                    this.setupRecruitmentCountryListener();
                    // Also setup city dropdown listener for recruitment countries form
                    this.setupRecruitmentCityDropdownListener();
                    
                    // Populate currency dropdown for recruitment_countries
                    await this.populateCurrencyDropdown();
                    
                    // If editing, set the selected country name and auto-fill related fields
                    const countryNameInput = document.getElementById('country_name_select');
                    if (countryNameInput) {
                        const countryValue = this.getFieldValue(item, 'name') || this.getFieldValue(item, 'country_name');
                        if (countryValue) {
                            // For text input, just set the value directly
                            if (countryNameInput.tagName === 'INPUT') {
                                countryNameInput.value = countryValue;
                                // Set code and flag from getCountryData so they show correctly (fixes DB storing "LK" in flag_emoji)
                                const countryData = this.getCountryData(countryValue);
                                const editForm = countryNameInput.closest('form');
                                const codeField = editForm ? editForm.querySelector('input[name="code"]') : null;
                                const flagField = editForm ? editForm.querySelector('input[name="flag_emoji"]') : null;
                                if (codeField && countryData.code) codeField.value = countryData.code;
                                if (flagField && countryData.flag) flagField.value = countryData.flag;
                                // Trigger the change handler to auto-fill currency and city list
                                countryNameInput.dispatchEvent(new Event('change'));
                                
                                // Set city value immediately after country is set
                                const cityValue = this.getFieldValue(item, 'city');
                                if (cityValue) {
                                    await this.populateRecruitmentCityDropdown(countryValue);
                                    const citySelect = document.getElementById('city_select');
                                    if (citySelect) {
                                        // For recruitment_countries, citySelect is an input field
                                        if (this.currentTable === 'recruitment_countries' && citySelect.tagName === 'INPUT') {
                                            citySelect.value = cityValue;
                                        } else if (citySelect.options) {
                                            // For other tables with select dropdown
                                            const cityOption = Array.from(citySelect.options).find(opt => 
                                                opt.value === cityValue || opt.textContent.toLowerCase() === String(cityValue).toLowerCase()
                                            );
                                            if (cityOption) {
                                                citySelect.value = cityOption.value;
                                            }
                                        }
                                    }
                                }
                            } else {
                                // Fallback for select dropdown (shouldn't happen but just in case)
                                const matchingOption = Array.from(countryNameInput.options).find(opt => 
                                    opt.value === countryValue || opt.textContent.toLowerCase() === String(countryValue).toLowerCase()
                                );
                                if (matchingOption) {
                                    countryNameInput.value = matchingOption.value;
                                    countryNameInput.dispatchEvent(new Event('change'));
                                    
                                    // Set city value immediately after country is set
                                    const cityValue = this.getFieldValue(item, 'city');
                                    if (cityValue) {
                                        await this.populateRecruitmentCityDropdown(countryValue);
                                        const citySelect = document.getElementById('city_select');
                                        if (citySelect) {
                                            if (citySelect.tagName === 'INPUT') {
                                                citySelect.value = cityValue;
                                            } else if (citySelect.options) {
                                                const cityOption = Array.from(citySelect.options).find(opt => 
                                                    opt.value === cityValue || opt.textContent.toLowerCase() === String(cityValue).toLowerCase()
                                                );
                                                if (cityOption) {
                                                    citySelect.value = cityOption.value;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    // Set currency value after currency dropdown is populated
                    const currencyValue = this.getFieldValue(item, 'currency');
                    if (currencyValue) {
                        const currencySelect = document.getElementById('currency_select');
                        if (currencySelect) {
                            // Normalize currency value - extract code if format is "CODE - Name"
                            let normalizedCurrency = currencyValue;
                            if (typeof normalizedCurrency === 'string' && normalizedCurrency.includes(' - ')) {
                                normalizedCurrency = normalizedCurrency.split(' - ')[0].trim();
                            }
                            normalizedCurrency = normalizedCurrency.toUpperCase().trim();
                            
                            // Try to find matching option
                            const currencyOption = Array.from(currencySelect.options).find(opt => 
                                opt.value.toUpperCase() === normalizedCurrency || 
                                opt.textContent.toUpperCase().includes(normalizedCurrency)
                            );
                            if (currencyOption) {
                                currencySelect.value = currencyOption.value;
                            } else {
                                // If exact match not found, set the value anyway (might be populated later)
                                currencySelect.value = normalizedCurrency;
                            }
                        }
                    }
                }
                
                // Populate currency dropdown for any form that has currency field with currencyDropdown flag
                const hasCurrencyField = formConfig.fields.some(field => field.name === 'currency' && field.currencyDropdown);
                if (hasCurrencyField && this.currentTable !== 'recruitment_countries') {
                    await this.populateCurrencyDropdown();
                    
                    // Set currency value if editing
                    const currencyValue = this.getFieldValue(item, 'currency');
                    if (currencyValue) {
                        const currencySelect = document.getElementById('currency_select');
                        if (currencySelect) {
                            // Normalize currency value - extract code if format is "CODE - Name"
                            let normalizedCurrency = currencyValue;
                            if (typeof normalizedCurrency === 'string' && normalizedCurrency.includes(' - ')) {
                                normalizedCurrency = normalizedCurrency.split(' - ')[0].trim();
                            }
                            normalizedCurrency = normalizedCurrency.toUpperCase().trim();
                            
                            // Try to find matching option
                            const currencyOption = Array.from(currencySelect.options).find(opt => 
                                opt.value.toUpperCase() === normalizedCurrency || 
                                opt.textContent.toUpperCase().includes(normalizedCurrency)
                            );
                            if (currencyOption) {
                                currencySelect.value = currencyOption.value;
                            } else {
                                // If exact match not found, set the value anyway (might be populated later)
                                currencySelect.value = normalizedCurrency;
                            }
                        }
                    }
                }
                
                if (hasCountryField) {
                    // Populate country dropdown first
                    await this.populateCountryDropdown();
                    this.setupCityDropdownListener();
                    
                    // No delay - proceed immediately to set values
                    // If editing, set the selected country and city values
                    const countrySelect = document.getElementById('country_id_select');
                    const citySelect = document.getElementById('city_select');
                    
                    if (countrySelect) {
                        // Try multiple ways to get the country value
                        // IMPORTANT: Handle 0 as a valid value (not falsy)
                        let countryValue = this.getFieldValue(item, 'country_id');
                        if (countryValue === null || countryValue === undefined || countryValue === '') {
                            countryValue = this.getFieldValue(item, 'country_name') || 
                                          this.getFieldValue(item, 'country');
                        }
                        
                        // Handle 0 as a special case - might mean "no country" or invalid ID
                        if (countryValue !== null && countryValue !== undefined && countryValue !== '') {
                            // If country_id is 0 or null, try to find country by city name
                            if (countryValue === 0 || countryValue === '0' || countryValue === null) {
                                const cityValue = this.getFieldValue(item, 'city');
                                if (cityValue && window.countriesCities) {
                                    // Find country that has this city
                                    for (const [countryName, cities] of Object.entries(window.countriesCities)) {
                                        if (Array.isArray(cities) && cities.includes(cityValue)) {
                                            countryValue = countryName;
                                            break;
                                        }
                                    }
                                }
                            }
                            
                            if (countryValue !== 0 && countryValue !== '0' && countryValue !== null) {
                                // Try to find matching country by:
                                // 1. Exact value match (name or ID)
                                // 2. Case-insensitive text match
                                // 3. If countryValue is numeric (ID), we might need to look up the country name
                                let matchingOption = Array.from(countrySelect.options).find(opt => {
                                    const dn = opt.getAttribute('data-name');
                                    return opt.value == countryValue ||
                                        opt.value === String(countryValue) ||
                                        (dn && dn.toLowerCase() === String(countryValue).toLowerCase()) ||
                                        opt.textContent.toLowerCase() === String(countryValue).toLowerCase() ||
                                        opt.value.toLowerCase() === String(countryValue).toLowerCase();
                                });
                                
                                // If countryValue looks like a numeric ID, try to get country name from recruitment_countries
                                if (!matchingOption && !isNaN(countryValue) && countryValue != '' && countryValue != 0) {
                                    try {
                                        const countryResponse = await this.apiCall('get_by_id', 'recruitment_countries', { id: countryValue });
                                        if (countryResponse.success && countryResponse.data) {
                                            const countryName = countryResponse.data.country_name || countryResponse.data.name;
                                            if (countryName) {
                                                // No delay - try immediately with the country name
                                                matchingOption = Array.from(countrySelect.options).find(opt => 
                                                    opt.value === countryName || 
                                                    opt.value === String(countryName) ||
                                                    opt.textContent.toLowerCase() === String(countryName).toLowerCase() ||
                                                    opt.value.toLowerCase() === String(countryName).toLowerCase()
                                                );
                                                if (matchingOption) {
                                                    countryValue = countryName; // Update for city loading
                                                }
                                            }
                                        }
                                    } catch (e) {
                                        // Could not fetch country name
                                    }
                                }
                                
                                if (matchingOption) {
                                    countrySelect.value = matchingOption.value;
                                    countrySelect.dispatchEvent(new Event('change'));
                                    
                                    // Set city value immediately after country change event fires
                                    const cityValue = this.getFieldValue(item, 'city');
                                    if (cityValue && citySelect) {
                                        // For recruitment_countries, citySelect is an input field, so set value directly
                                        if (this.currentTable === 'recruitment_countries' && citySelect.tagName === 'INPUT') {
                                            // Wait a bit for datalist to populate, then set the value
                                            await new Promise(resolve => setTimeout(resolve, 300));
                                            citySelect.value = cityValue;
                                        } else if (citySelect.options) {
                                            // For other tables with select dropdown, use the original logic
                                            // Use a promise-based wait that checks if cities are loaded
                                            await new Promise(resolve => {
                                                const checkCities = () => {
                                                    // Check if cities have been populated (more than just "Select Country First")
                                                    if (citySelect.options.length > 1) {
                                                        resolve();
                                                    } else {
                                                        // Check every 50ms, max 1 second
                                                        setTimeout(checkCities, 50);
                                                    }
                                                };
                                                // Start checking immediately
                                                checkCities();
                                                // Max timeout
                                                setTimeout(resolve, 1000);
                                            });
                                            
                                            const cityOption = Array.from(citySelect.options).find(opt => 
                                                opt.value === cityValue || 
                                                opt.value === String(cityValue) ||
                                                opt.textContent.toLowerCase() === String(cityValue).toLowerCase()
                                            );
                                            if (cityOption) {
                                                citySelect.value = cityOption.value;
                                            } else {
                                                // If city not found, add it as an option
                                                const newOption = document.createElement('option');
                                                newOption.value = cityValue;
                                                newOption.textContent = cityValue;
                                                newOption.selected = true;
                                                citySelect.appendChild(newOption);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                
                if (this.currentTable === 'visa_types') {
                    const descSaved = this.getFieldValue(item, 'description') || '';
                    this.initVisaSubtypeField(descSaved);
                }
                
                // Clear the stored item after form is rendered
                this.currentItem = null;
            }, 200);
        } catch (error) {
            container.innerHTML = `<div class="error-state"><i class="fas fa-exclamation-triangle"></i><p>Failed to load record: ${error.message}</p></div>`;
            this.currentItem = null;
        }
    }
    
    // Render company info form
    async renderCompanyInfoForm(container, isEdit = false) {
        const formConfig = this.getFormConfig(this.currentTable);
        const companyFields = formConfig.companyInfoFields || [];
        
        // Load existing company info if editing
        let existingData = {};
        if (isEdit) {
            try {
                const response = await this.apiCall('get_all', 'system_config');
                if (response.success && Array.isArray(response.data)) {
                    response.data.forEach(item => {
                        const key = item.config_key || item.name;
                        if (key && key.startsWith('company_')) {
                            existingData[key] = item.config_value || item.value || '';
                        }
                    });
                }
            } catch (error) {
                console.error('Error loading company info:', error);
            }
        }
        
        // Group fields by section
        const sections = {
            basic: { title: 'Basic Information', fields: [] },
            address: { title: 'Address Information', fields: [] },
            contact: { title: 'Contact Information', fields: [] },
            legal: { title: 'Legal & Tax Information', fields: [] }
        };
        
        companyFields.forEach(field => {
            const section = field.section || 'basic';
            if (sections[section]) {
                sections[section].fields.push(field);
            }
        });
        
        let html = `
            <form id="companyInfoForm">
                <div class="company-info-form">
        `;
        
        // Render each section
        Object.keys(sections).forEach(sectionKey => {
            const section = sections[sectionKey];
            if (section.fields.length === 0) return;
            
            html += `
                <div class="form-section">
                    <h3 class="form-section-title">${section.title}</h3>
                    <div class="form-grid">
            `;
            
            section.fields.forEach(field => {
                const value = existingData[field.name] || field.value || '';
                html += this.renderFormField(field, value);
            });
            
            html += `
                    </div>
                </div>
            `;
        });
        
        html += `
                </div>
                <div class="form-actions">
                    <button type="button" class="modern-btn modern-btn-secondary" data-action="close-form">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                    <button type="submit" class="modern-btn modern-btn-primary">
                        <i class="fas fa-save"></i>
                        Save Company Information
                    </button>
                </div>
            </form>
        `;
        
        container.innerHTML = html;
    }
    
    // Render form field
    renderFormField(field, value = '') {
        const required = field.required ? 'required' : '';
        const requiredMark = field.required ? '<span class="required">*</span>' : '';
        
        let input = '';
        
        // Special handling for country_id — empty select filled after render (recruitment IDs, control countries, or visa_types world list)
        if (field.name === 'country_id' && (field.relation || this.currentTable === 'visa_types')) {
            input = `<select name="${field.name}" id="country_id_select" ${required}><option value="">Select Country...</option></select>`;
            // Will be populated after form renders
        }
        // Special handling for recruitment_countries country name - text input with datalist to allow adding new countries
        else if (field.countryDropdown && this.currentTable === 'recruitment_countries') {
            input = `<input type="text" name="${field.name}" id="country_name_select" list="country_datalist" ${required} placeholder="Type country name or select from list" autocomplete="off">
                     <datalist id="country_datalist">
                         <option value="">Select Country...</option>
                     </datalist>`;
            // Will be populated after form renders
        }
        // Special handling for city - dropdown for other tables, text input with datalist for recruitment_countries
        else if (field.name === 'city') {
            if (this.currentTable === 'recruitment_countries') {
                // For recruitment_countries, use text input with datalist to allow adding new cities
                input = `<input type="text" name="city" id="city_select" list="city_datalist" ${required} placeholder="Type city name or select from list" autocomplete="off">
                         <datalist id="city_datalist">
                             <option value="">Select Country First</option>
                         </datalist>`;
            } else {
                // For other tables, use dropdown
                input = `<select name="city" id="city_select" ${required}><option value="">Select Country First</option></select>`;
            }
        }
        // Special handling for currency - dropdown populated from currencies table
        else if (field.name === 'currency' && field.currencyDropdown) {
            input = `<select name="${field.name}" id="currency_select" ${required}><option value="">Select Currency...</option></select>`;
            // Will be populated after form renders
        }
        // Special handling for copy_from_currency - dropdown for copying currency data
        else if (field.name === 'copy_from_currency' && field.currencyDropdown && field.copySource) {
            input = `<select name="${field.name}" id="copy_from_currency_select" ${required}><option value="">Select Currency to Copy From (Optional)...</option></select>`;
            // Will be populated after form renders
        }
        else if (this.currentTable === 'visa_types' && field.name === 'description') {
            input = `<select name="description" id="description_select" ${required}><option value="">Select visa type first...</option></select>`;
        }
        else {
            switch (field.type) {
                case 'textarea':
                    input = `<textarea name="${field.name}" ${required} placeholder="${field.placeholder}">${value}</textarea>`;
                    break;
                case 'select':
                    if (field.options) {
                        // Normalize status values for comparison
                        let normalizedValue = value;
                        // Handle both 'status' and 'is_active' fields
                        if (field.name === 'status') {
                            // For create forms (when value is empty or undefined), default to 'active'
                            if (!value || value === '' || value === null || value === undefined) {
                                normalizedValue = 'active'; // Default for new records
                            } else {
                                // Check if we actually have is_active in the item (for visa_types, recruitment_countries, etc.)
                                const item = this.currentItem || {};
                                const isActive = item.is_active !== undefined ? item.is_active : null;
                                const statusValue = item.status !== undefined ? item.status : null;
                                
                                // Priority: use is_active if available (for tables that use is_active), otherwise use status
                                let actualValue = isActive !== null ? isActive : (statusValue !== null ? statusValue : value);
                                
                                // Convert is_active (1/0) or status to active/inactive
                                // Handle numeric values first
                                if (actualValue === 1 || actualValue === '1' || actualValue === true) {
                                    normalizedValue = 'active';
                                } else if (actualValue === 0 || actualValue === '0' || actualValue === false) {
                                    normalizedValue = 'inactive';
                                } else if (typeof actualValue === 'string') {
                                    const lowerValue = actualValue.toLowerCase();
                                    if (lowerValue === 'active' || lowerValue === '1' || lowerValue === 'true') {
                                        normalizedValue = 'active';
                                    } else if (lowerValue === 'inactive' || lowerValue === '0' || lowerValue === 'false') {
                                        normalizedValue = 'inactive';
                                    }
                                }
                            }
                            
                            // Status normalized
                        }
                        
                        const options = field.options.map(opt => {
                            const isSelected = opt.value == normalizedValue || 
                                             String(opt.value).toLowerCase() === String(normalizedValue).toLowerCase() ||
                                             (field.name === 'status' && (
                                                 (normalizedValue === 'active' && (opt.value === 'active' || opt.value === 1 || opt.value === '1')) ||
                                                 (normalizedValue === 'inactive' && (opt.value === 'inactive' || opt.value === 0 || opt.value === '0'))
                                             ));
                            return `<option value="${opt.value}">${opt.label}</option>`;
                        }).join('');
                        
                        // For status fields, don't show "Select..." option - default to first option if no value
                        const defaultOption = field.name === 'status' && (!normalizedValue || normalizedValue === '') ? `<option value="${field.options[0].value}">${field.options[0].label}</option>` : '';
                        const placeholderOption = field.name === 'status' ? '' : '<option value="">Select...</option>';
                        const selectIdAttr = ` id="${field.name}_select"`;
                        input = `<select name="${field.name}"${selectIdAttr} ${required}>${placeholderOption}${defaultOption}${options}</select>`;
                    } else {
                        input = `<select name="${field.name}" id="${field.name}_select" ${required}><option value="">Select...</option></select>`;
                    }
                    break;
                case 'file':
                    const acceptAttr = field.accept ? ` accept="${field.accept}"` : '';
                    input = `<input type="file" name="${field.name}" id="${field.name}_file"${acceptAttr} ${required}>`;
                    break;
                default:
                    if (field.type === 'password') {
                        // Password field with show/hide toggle; autocomplete=new-password prevents browser autofill of old password
                        const fieldId = `password_${field.name}_${Date.now()}`;
                        const autocomplete = field.autocomplete || 'new-password';
                        input = `
                            <div class="password-input-wrapper">
                                <input type="password" name="${field.name}" id="${fieldId}" value="${value || ''}" ${required} placeholder="${field.placeholder}" autocomplete="${autocomplete}">
                                <button type="button" class="password-toggle-btn" data-target="${fieldId}" title="Show/Hide password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        `;
                    } else {
                    input = `<input type="${field.type}" name="${field.name}" value="${value || ''}" ${required} placeholder="${field.placeholder}">`;
                    }
            }
        }
        
        // Get icon for field
        const iconMap = {
            'name': 'user-circle',
            'username': 'user',
            'email': 'envelope',
            'password': 'lock',
            'phone': 'phone',
            'position': 'briefcase',
            'description': 'tags',
            'country_id': 'globe',
            'city': 'map-marker-alt',
            'status': 'toggle-on',
            'code': 'tag',
            'currency': 'dollar-sign',
            'flag_emoji': 'flag',
            'fees': 'dollar-sign',
            'processing_time': 'clock',
            'requirements': 'file-alt'
        };
        const icon = iconMap[field.name.toLowerCase()] || 'circle';
        
        return `
            <div class="form-group">
                <label for="${field.name}" class="form-label-with-icon">
                    <i class="fas fa-${icon}"></i>
                    <span>${field.label} ${requiredMark}</span>
                </label>
                ${input}
                ${field.help ? '<small class="form-help"></small>' : ''}
            </div>
        `;
    }
    
    // Handle form submit
    async handleFormSubmit(event, setting) {
        event.preventDefault();
        
        const form = event.target;
        
        // Handle company info form submission
        if (this.isCompanyInfoMode && this.currentTable === 'system_config' && form.id === 'companyInfoForm') {
            await this.handleCompanyInfoSubmit(event);
            return;
        }
        
        const formData = new FormData(form);
        let data = Object.fromEntries(formData.entries());
        
        // Recruitment countries: fix wrong modal targets + typos (e.g. "sauadi" → Saudi Arabia) before stripping empty keys
        if (this.currentTable === 'recruitment_countries') {
            try {
                await this.preloadAllCountriesAndCities();
                const countries = (this._allCountriesAndCitiesData && this._allCountriesAndCitiesData.countries) || [];
                const rawName = (data.name && String(data.name).trim()) || '';
                if (rawName) {
                    const resolved = this.resolveRecruitmentCountryName(rawName, countries);
                    if (resolved) {
                        const cnInput = form.querySelector('#country_name_select') || document.getElementById('country_name_select');
                        if (cnInput) cnInput.value = resolved;
                        data.name = resolved;
                        const cd = this.getCountryData(resolved);
                        if (cd.code) data.code = cd.code;
                        if (cd.currency) data.currency = cd.currency;
                        if (cd.flag) data.flag_emoji = cd.flag;
                        const codeInp = form.querySelector('input[name="code"]');
                        const curSel = form.querySelector('select[name="currency"]');
                        const flagInp = form.querySelector('input[name="flag_emoji"]');
                        if (codeInp && cd.code) codeInp.value = cd.code;
                        if (curSel && cd.currency) {
                            const cur = cd.currency.toUpperCase();
                            const opt = Array.from(curSel.options).find(o => o.value && o.value.toUpperCase() === cur);
                            if (opt) curSel.value = opt.value;
                        }
                        if (flagInp && cd.flag) flagInp.value = cd.flag;
                    }
                }
            } catch (e) {
                console.warn('Recruitment country submit resolve:', e);
            }
        }
        
        // Ensure currentAction and currentId are preserved (critical for edit vs create)
        // If form has hidden id field, use it to determine edit mode
        const formId = form.querySelector('input[type="hidden"][name="id"]')?.value || 
                      form.querySelector('input[name="id"]')?.value ||
                      this.currentId;
        
        // If we have an ID but currentAction is not 'edit', fix it
        if (formId && this.currentAction !== 'edit') {
            this.currentAction = 'edit';
            this.currentId = parseInt(formId) || this.currentId;
        }
        
        // Remove copy_from_currency field - it's only used for copying data, not a database field
        if (data.copy_from_currency) {
            delete data.copy_from_currency;
        }
        
        // Remove empty values and action/table fields (but keep status field for normalization)
        Object.keys(data).forEach(key => {
            if (key === 'action' || key === 'table' || key === 'id') {
                delete data[key];
            }
            // Don't delete status field if it's empty - we'll set a default later
            // For password in edit mode: if empty or only whitespace, remove it to keep current password
            if (key === 'password' && this.currentAction === 'edit') {
                if (!data[key] || String(data[key]).trim() === '') {
                    delete data[key]; // Remove empty password to keep current
                }
            } else if (data[key] === '' && key !== 'status') {
                delete data[key];
            }
        });

        // Minimal client-side validation based on table
        const table = this.currentTable;
        const isControl = (typeof isControlPanelContext === 'function' && isControlPanelContext()) || (window.location && window.location.search && window.location.search.includes('control=1'));
        const requiredByTable = {
            'users': isControl ? (this.currentAction === 'create' ? ['name', 'password'] : ['name']) : (this.currentAction === 'create' ? ['name', 'email', 'password'] : ['name', 'email']),
            'visa_types': ['name'],
            'recruitment_countries': ['name', 'code'],
            'office_managers': ['name', 'email'],
            'worker_statuses': ['name'],
            'system_config': ['name'],
            'age_specifications': ['name'],
            'status_specifications': ['name'],
            'arrival_agencies': ['name'],
            'arrival_stations': ['name'],
            'appearance_specifications': ['name'],
            'request_statuses': ['name']
        };
        const alias = this.getReverseAliasMap(table);
        const missing = [];
        (requiredByTable[table] || []).forEach(field => {
            // Check direct
            if (data[field] && String(data[field]).trim() !== '') return;
            // Check aliases
            const candidates = (alias[field] || []);
            const found = candidates.some(k => data[k] && String(data[k]).trim() !== '');
            if (!found) missing.push(field);
        });
        if (missing.length) {
            this.showNotification(`Please fill required field(s): ${missing.join(', ')}`, 'error');
            return;
        }
        
        // Ensure status field has a value (default to 'active' if empty)
        if (data.hasOwnProperty('status') && (data.status === '' || data.status === null || data.status === undefined)) {
            data.status = 'active'; // Default status
        }
        
        // Country field: visa_types uses full world list (__world__:name → country_name); other tables resolve name → recruitment id
        const countrySelectEl = document.getElementById('country_id_select');
        if (countrySelectEl && this.currentTable === 'visa_types') {
            const idx = countrySelectEl.selectedIndex;
            const selectedOption = idx >= 0 ? countrySelectEl.options[idx] : null;
            if (!countrySelectEl.value || idx <= 0) {
                data.country_id = null;
                data.country_name = null;
            } else if (selectedOption) {
                const optionValue = selectedOption.value;
                if (typeof optionValue === 'string' && optionValue.indexOf('__world__:') === 0) {
                    let cn = optionValue.slice('__world__:'.length);
                    try {
                        cn = decodeURIComponent(cn);
                    } catch (e) {
                        // keep raw
                    }
                    data.country_name = cn;
                    data.country_id = null;
                } else if (/^\d+$/.test(optionValue)) {
                    data.country_id = parseInt(optionValue, 10);
                    delete data.country_name;
                }
            }
        } else if (data.country_id) {
            const countrySelect = document.getElementById('country_id_select');
            if (countrySelect && countrySelect.selectedIndex >= 0) {
                const selectedOption = countrySelect.options[countrySelect.selectedIndex];
                if (selectedOption) {
                    const optionValue = selectedOption.value;
                    
                    if (/^\d+$/.test(optionValue)) {
                        data.country_id = parseInt(optionValue, 10);
                    } else {
                        const countryName = optionValue || selectedOption.textContent.trim();
                        if (countryName && countryName !== 'Select Country...') {
                            try {
                                const response = await this.apiCall('get_all', 'recruitment_countries');
                                if (response.success && Array.isArray(response.data)) {
                                    const country = response.data.find(item => {
                                        const name = item.country_name || item.name || '';
                                        return name.toLowerCase() === countryName.toLowerCase();
                                    });
                                    if (country && country.id) {
                                        data.country_id = parseInt(country.id, 10) || null;
                                    } else {
                                        data.country_id = null;
                                    }
                                }
                            } catch (e) {
                                data.country_id = null;
                            }
                        }
                    }
                }
            }
        }
        
        // Sanitize/normalize payload
        data = this.sanitizePayload(this.currentTable, data);

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        submitBtn.classList.add('disabled');
        submitBtn.setAttribute('data-disabled', 'true');
        
        try {
            let response;
            if (this.currentAction === 'create') {
                response = await this.apiCall('create', this.currentTable, { data });
            } else {
                if (!this.currentId) {
                    this.showNotification('Error: Missing record ID. Cannot update.', 'error');
                    return;
                }
                response = await this.apiCall('update', this.currentTable, { id: this.currentId, data });
            }
            
            if (response.success) {
                // If create response includes the created record, add it immediately
                if (this.currentAction === 'create' && response.created) {
                    this.data.unshift(response.created);
                    // Immediately re-render table to show new user with password
                    this.renderTable();
                }
                
                // If update, refresh the specific item in our data array
                if (this.currentAction === 'edit' && response.updated) {
                    const idx = this.data.findIndex(item => (item.id || item.user_id) == this.currentId);
                    
                    if (idx >= 0) {
                        // Merge updated data to preserve any fields that might not be in response
                        this.data[idx] = { ...this.data[idx], ...response.updated };
                        // Immediately re-render table to show updated password
                        this.renderTable();
                    } else {
                        // If not found, add it (shouldn't happen, but handle gracefully)
                        this.data.push(response.updated);
                        this.renderTable();
                    }
                }
                
                // Show notification
                this.showNotification(response.message || 'Saved successfully', 'success');
                
                // Also show modern alert for success
                if (window.SystemSettingsAlert) {
                    await window.SystemSettingsAlert.success(
                        response.message || 'Record saved successfully!',
                        'Success'
                    );
                }
                
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory) {
                    await window.unifiedHistory.refreshIfOpen();
                }
                
                // Close form without confirmation since save was successful
                this.closeFormModal(true);
                
                // Check if we're on profile page - if so, reload the page to show updated data
                if (window.location.pathname.includes('profile.php')) {
                    // Small delay to show success message, then reload
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                    return;
                }
                
                // Ensure main modal stays open and visible (for system settings pages)
                const mainModal = document.getElementById('mainModal');
                if (mainModal && !mainModal.classList.contains('show')) {
                    mainModal.classList.remove('modal-hidden');
                    mainModal.classList.add('show');
                }
                
                // Force full refresh to ensure consistency (for system settings pages)
                await this.refreshData();
                
                // Check if we're in profile modal context (dashboard profile modal)
                const profileModal = document.getElementById('mainModal');
                const modalBody = profileModal?.querySelector('#modalBody') || profileModal?.querySelector('.modal-body');
                const isProfileModal = profileModal && modalBody && modalBody.classList.contains('profile-modal-content');
                
                if (isProfileModal && this.currentTable === 'users') {
                    // Re-filter to show only current user in profile modal
                    const profileCard = document.querySelector('.system-card[data-action="open-profile-modal"]');
                    const currentUserId = profileCard ? parseInt(profileCard.getAttribute('data-user-id')) : null;
                    
                    if (currentUserId && this.data && Array.isArray(this.data)) {
                        // Filter data to show only current user
                        this.data = this.data.filter(user => user.user_id == currentUserId);
                        
                        // Check for duplicates and remove them
                        const seen = new Set();
                        this.data = this.data.filter(user => {
                            if (seen.has(user.user_id)) {
                                return false;
                            }
                            seen.add(user.user_id);
                            return true;
                        });
                        
                        // Recalculate stats for filtered data
                        const filteredStats = {
                            total: this.data.length,
                            active: this.data.filter(user => {
                                const status = user.status || user.is_active;
                                return status === 'active' || status === 1 || status === '1';
                            }).length,
                            inactive: this.data.filter(user => {
                                const status = user.status || user.is_active;
                                return status === 'inactive' || status === 0 || status === '0';
                            }).length,
                            today: 0,
                            thisWeek: 0,
                            thisMonth: 0
                        };
                        
                        this.currentTableStats = filteredStats;
                        this.renderTableWithStats(filteredStats);
                        return; // Don't continue with normal refresh
                    }
                }
                
                // Reload stats and re-render (for system settings pages)
                const stats = await this.loadTableStats(this.currentTable);
                this.renderTableWithStats(stats);
            } else {
                console.error('Save failed:', response);
                this.showNotification(response.message || 'Unknown error occurred', 'error');
                
                // Also show modern alert for error
                if (window.SystemSettingsAlert) {
                    await window.SystemSettingsAlert.error(
                        response.message || 'Failed to save record. Please try again.',
                        'Error'
                    );
                }
            }
        } catch (error) {
            this.showNotification('Network error: ' + error.message, 'error');
        } finally {
            submitBtn.innerHTML = originalText;
            submitBtn.classList.remove('disabled');
            submitBtn.setAttribute('data-disabled', 'false');
        }
    }

    // Sanitize/normalize outgoing payload for create/update
    sanitizePayload(table, data){
        const cleaned = {};
        const numericKeys = new Set(['validity_days','processing_fee','min_salary','max_salary','min_age','max_age','country_id']);
        Object.keys(data).forEach(k => {
            let v = data[k];
            if (typeof v === 'string') v = v.trim();
            // Don't drop status field - normalize it instead
            if (k === 'status') {
                if (v === '' || v === null || v === undefined) {
                    v = 'active'; // Default to active if empty
                } else {
                    const val = String(v).toLowerCase();
                    v = (val === 'active' || val === '1' || val === 'true' || val === 'yes') ? 'active' : 'inactive';
                }
                cleaned[k] = v;
                return; // Skip the empty check for status
            }
            // Special handling for country_id - allow null/empty but validate if present
            if (k === 'country_id') {
                if (v === '' || v === undefined || v === null || v === 0 || v === '0') {
                    cleaned[k] = null; // Set to null instead of dropping
                    return;
                }
                const num = parseInt(v);
                if (!isNaN(num) && num > 0) {
                    cleaned[k] = num;
                } else {
                    cleaned[k] = null; // Invalid country_id - set to null
                }
                return;
            }
            if (k === 'country_name') {
                if (v === '' || v === undefined || v === null) {
                    cleaned[k] = null;
                } else {
                    cleaned[k] = typeof v === 'string' ? v.trim() : v;
                }
                return;
            }
            
            if (v === '' || v === undefined || v === null) return; // drop empties
            if (numericKeys.has(k)) {
                const num = Number(v);
                if (!isNaN(num)) v = num; else return; // drop invalid number
            }
            cleaned[k] = v;
        });
        
        // Ensure status is always included (default to 'active' if not present)
        if (!cleaned.hasOwnProperty('status') && data.hasOwnProperty('status')) {
            cleaned.status = 'active';
        }
        
        return cleaned;
    }
    
    // Handle company info form submission
    async handleCompanyInfoSubmit(event) {
        const form = event.target;
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        submitBtn.classList.add('disabled');
        submitBtn.setAttribute('data-disabled', 'true');
        
        try {
            const formConfig = this.getFormConfig(this.currentTable);
            const companyFields = formConfig.companyInfoFields || [];
            const savedFields = [];
            const errors = [];
            
            // Save each field as a separate entry in system_config
            for (const field of companyFields) {
                const value = formData.get(field.name);
                
                // Skip file fields for now (logo upload needs special handling)
                if (field.type === 'file') {
                    continue;
                }
                
                // Prepare data for this config entry
                const configData = {
                    config_key: field.name,
                    config_value: value || '',
                    name: field.label,
                    description: field.label,
                    status: 'active'
                };
                
                // Check if this config already exists
                try {
                    const checkResponse = await this.apiCall('get_all', 'system_config');
                    let existingId = null;
                    
                    if (checkResponse.success && Array.isArray(checkResponse.data)) {
                        const existing = checkResponse.data.find(item => 
                            (item.config_key === field.name) || (item.name === field.name)
                        );
                        if (existing) {
                            existingId = existing.id;
                        }
                    }
                    
                    // Create or update the config entry
                    if (existingId) {
                        const updateResponse = await this.apiCall('update', 'system_config', {
                            id: existingId,
                            data: configData
                        });
                        if (updateResponse.success) {
                            savedFields.push(field.name);
                        } else {
                            errors.push(`${field.label}: ${updateResponse.message || 'Update failed'}`);
                        }
                    } else {
                        const createResponse = await this.apiCall('create', 'system_config', {
                            data: configData
                        });
                        if (createResponse.success) {
                            savedFields.push(field.name);
                        } else {
                            errors.push(`${field.label}: ${createResponse.message || 'Create failed'}`);
                        }
                    }
                } catch (error) {
                    errors.push(`${field.label}: ${error.message}`);
                }
            }
            
            if (errors.length > 0) {
                this.showNotification(`Some fields failed to save: ${errors.join(', ')}`, 'error');
            } else {
                this.showNotification('Company information saved successfully!', 'success');
                
                if (window.SystemSettingsAlert) {
                    await window.SystemSettingsAlert.success(
                        'Company information saved successfully!',
                        'Success'
                    );
                }
                
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory) {
                    await window.unifiedHistory.refreshIfOpen();
                }
                
                // Close form and refresh data
                this.closeFormModal(true);
                await this.refreshData();
            }
        } catch (error) {
            console.error('Error saving company info:', error);
            this.showNotification(`Error saving company information: ${error.message}`, 'error');
        } finally {
            submitBtn.innerHTML = originalText;
            submitBtn.classList.remove('disabled');
            submitBtn.removeAttribute('data-disabled');
        }
    }
    
    // Open company info form
    openCompanyInfoForm() {
        this.isCompanyInfoMode = true;
        this.currentTable = 'system_config';
        this.currentAction = 'edit';
        this.openFormModal('edit', null);
    }
    
    // Open edit form
    openEditForm(id) {
        this.openFormModal('edit', id);
    }
    
    // Delete item
    async deleteItem(id) {
        if (!id || isNaN(id)) {
            this.showNotification('Error: Invalid item ID', 'error');
            return;
        }
        
        const ok = await this.confirmDialog('Delete this item?');
        if (!ok) return;
        
        try {
            // For users table, ensure we pass the correct ID format
            const payload = { id: id };
            
            
            const response = await this.apiCall('delete', this.currentTable, payload);
            if (response.success) {
                this.showNotification(response.message || 'Item deleted successfully', 'success');
                
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory) {
                    await window.unifiedHistory.refreshIfOpen();
                }
                
                await this.refreshData();
                const stats = await this.loadTableStats(this.currentTable);
                this.renderTableWithStats(stats);
            } else {
                this.showNotification(response.message || 'Failed to delete item', 'error');
            }
        } catch (error) {
            console.error('Delete error:', error);
            this.showNotification('Failed to delete: ' + (error.message || 'Unknown error'), 'error');
        }
    }
    
    // Bulk selection helpers
    toggleSelectAll(checkbox){
        const idsOnPage = this.getPagedData(this.getFilteredData()).map(r => r.id || r.user_id);
        idsOnPage.forEach(id => {
            if (checkbox.checked) this.selectedIds.add(id); else this.selectedIds.delete(id);
        });
        this.renderTable();
        this.updateBulkButtonsState();
    }
    toggleRow(id, el){
        if (el.checked) this.selectedIds.add(id); else this.selectedIds.delete(id);
        this.updateBulkButtonsState();
    }
    async bulkDelete(){
        if (!this.selectedIds.size) {
            this.showNotification('No items selected', 'info');
            return;
        }
        const ok = await this.confirmDialog(`Delete ${this.selectedIds.size} selected item(s)?`);
        if (!ok) return;
        const ids = Array.from(this.selectedIds);
        try{
            let successCount = 0;
            let failCount = 0;
            for (const id of ids){
                if (!id || isNaN(id)) {
                    console.error('Invalid ID in bulk delete:', id);
                    failCount++;
                    continue;
                }
                try {
                    const response = await this.apiCall('delete', this.currentTable, { id });
                    if (response && response.success) {
                    successCount++;
                    } else {
                        console.error(`Failed to delete id ${id}:`, response?.message || 'Unknown error');
                        failCount++;
                    }
                } catch (err) {
                    console.error(`Failed to delete id ${id}:`, err);
                    failCount++;
                }
            }
            this.selectedIds.clear();
                await this.refreshData();
                const stats = await this.loadTableStats(this.currentTable);
                this.renderTableWithStats(stats);
            this.updateBulkButtonsState();
            if (successCount > 0) {
                this.showNotification(`Deleted ${successCount} record(s)${failCount > 0 ? ` (${failCount} failed)` : ''}`, 'success');
            } else {
                this.showNotification(`Failed to delete ${failCount} record(s)`, 'error');
            }
        }catch(err){
            console.error('Bulk delete error:', err);
            this.showNotification('Bulk delete failed: ' + (err.message || 'Unknown error'), 'error');
        }
    }
    selectAllOnPage(){
        const idsOnPage = this.getPagedData(this.getFilteredData()).map(r => r.id || r.user_id);
        idsOnPage.forEach(id => this.selectedIds.add(id));
        this.renderTable();
        this.updateBulkButtonsState();
    }
    clearSelection(){
        this.selectedIds.clear();
        this.renderTable();
        this.updateBulkButtonsState();
    }
    exportSelectedCSV(){
        if (!this.selectedIds.size) return;
        const cfg = this.getTableConfig(this.currentTable);
        const headers = cfg.columns.map(c => c.label);
        const rows = this.data.filter(r => this.selectedIds.has(r.id || r.user_id)).map(r => cfg.columns.map(c => String(r[c.key] ?? '').replaceAll('"','""')));
        const csv = [headers.join(','), ...rows.map(cols => cols.map(v => `"${v}"`).join(','))].join('\n');
        const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = `${this.currentTable}_selected.csv`; a.click();
        URL.revokeObjectURL(url);
    }

    async bulkSetStatus(status){
        if (!this.selectedIds.size) return;
        const ok = await this.confirmDialog(`Set ${this.selectedIds.size} selected item(s) to ${status}?`);
        if (!ok) return;
        const ids = Array.from(this.selectedIds);
        try{
            let successCount = 0;
            let failCount = 0;
            for (const id of ids){
                try {
                    // Check if table uses is_active or status field
                    // Try to get the record first to see what fields it has
                    const item = this.data.find(r => (r.id === id || r.user_id == id));
                    let statusData = {};
                    
                    // If item has is_active field, update that too
                    if (item && item.hasOwnProperty('is_active')) {
                        statusData.is_active = (status === 'active') ? 1 : 0;
                    }
                    
                    // Always include status field
                    statusData.status = status;
                    
                    await this.apiCall('update', this.currentTable, { id, data: statusData });
                    successCount++;
                } catch (err) {
                    console.error(`Failed to update id ${id}:`, err);
                    failCount++;
                }
            }
            
            this.selectedIds.clear();
            
            // Refresh history if UnifiedHistory modal is open
            if (window.unifiedHistory) {
                await window.unifiedHistory.refreshIfOpen();
            }
            
            await this.refreshData();
            const stats = await this.loadTableStats(this.currentTable);
            this.renderTableWithStats(stats);
            this.updateBulkButtonsState();
            
            if (successCount > 0) {
                this.showNotification(`Updated ${successCount} item(s) to ${status}${failCount > 0 ? ` (${failCount} failed)` : ''}`, 'success');
            } else {
                this.showNotification(`Failed to update ${failCount} item(s)`, 'error');
            }
            this.updateBulkButtonsState();
        }catch(err){
            this.showNotification('Bulk status update failed: ' + err.message, 'error');
        }
    }

    // Modern confirm dialog
    confirmDialog(message){
        return new Promise(resolve => {
            const overlay = document.createElement('div');
            overlay.className = 'confirm-overlay';
            overlay.innerHTML = `
                <div class="confirm-box">
                    <div class="confirm-header">Confirm</div>
                    <div class="confirm-body">${message}</div>
                    <div class="confirm-actions">
                        <button class="confirm-btn confirm-btn-secondary" data-action="cancel">Cancel</button>
                        <button class="confirm-btn confirm-btn-primary" data-action="ok">Confirm</button>
                    </div>
                </div>`;
            const prevent = (e)=>{ e.preventDefault(); };
            const keyPrevent = (e)=>{
                const keys = ['ArrowUp','ArrowDown','PageUp','PageDown','Home','End',' '];
                if (keys.includes(e.key)) e.preventDefault();
            };
            const onClose = (val)=>{ 
                document.removeEventListener('wheel', prevent, { passive:false });
                document.removeEventListener('touchmove', prevent, { passive:false });
                document.removeEventListener('keydown', keyPrevent, false);
                document.documentElement.classList.remove('no-scroll');
                document.body.classList.remove('no-scroll'); 
                overlay.remove(); resolve(val); };
            overlay.addEventListener('click', (e)=>{ if(e.target===overlay) onClose(false); });
            overlay.querySelector('[data-action="cancel"]').addEventListener('click', ()=> onClose(false));
            overlay.querySelector('[data-action="ok"]').addEventListener('click', ()=> onClose(true));
            document.body.appendChild(overlay);
            document.documentElement.classList.add('no-scroll');
            document.body.classList.add('no-scroll');
            document.addEventListener('wheel', prevent, { passive:false });
            document.addEventListener('touchmove', prevent, { passive:false });
            document.addEventListener('keydown', keyPrevent, false);
        });
    }

    // Enable/disable bulk buttons based on selection size
    updateBulkButtonsState(){
        const toolbar = document.querySelector('.table-actions');
        if (!toolbar) return;
        const count = this.selectedIds.size;
        const delBtn = toolbar.querySelector('.modern-btn-danger');
        if (delBtn) {
            const countSpan = delBtn.querySelector('.selection-count');
            if (countSpan) {
                countSpan.textContent = count;
            }
            if (count === 0) {
                delBtn.classList.add('disabled');
                delBtn.setAttribute('data-disabled', 'true');
            } else {
                delBtn.classList.remove('disabled');
                delBtn.setAttribute('data-disabled', 'false');
            }
        }
        const bulkBtns = toolbar.querySelectorAll('.bulk-action-btn');
        bulkBtns.forEach(btn => {
            if (count === 0) {
                btn.classList.add('disabled');
                btn.setAttribute('data-disabled', 'true');
            } else {
                btn.classList.remove('disabled');
                btn.setAttribute('data-disabled', 'false');
            }
        });
        
        // Update pagination buttons
        const tableContainer = toolbar.closest('.modern-data-table');
        if (tableContainer) {
            const paginationBtns = tableContainer.querySelectorAll('.pagination-btn');
            paginationBtns.forEach(btn => {
                const isDisabled = btn.getAttribute('data-disabled') === 'true' || btn.getAttribute('data-disabled') === true;
                if (isDisabled) {
                    btn.classList.add('disabled');
                } else {
                    btn.classList.remove('disabled');
                }
            });
        }
    }
    
    // Refresh data
    async refreshData() {
        await this.loadData();
        // Also refresh stats
        try {
            this.currentTableStats = await this.loadTableStats(this.currentTable);
        } catch (error) {
            console.error('Failed to refresh stats:', error);
        }
    }
    
    async handleFingerprintAction(userId, username, statusText) {
        if (!userId) {
            this.showNotification('Invalid user selected', 'error');
            return;
        }
        const isRegistered = (statusText || '').toLowerCase().includes('registered') && !(statusText || '').toLowerCase().includes('not');
        
        // Open fingerprint registration modal/form for both new registration and update
        // Check if function exists (from system-settings.js) or use window reference
        if (typeof window.openFingerprintRegistrationModal === 'function') {
            window.openFingerprintRegistrationModal(userId, username, isRegistered);
        } else if (typeof openFingerprintRegistrationModal === 'function') {
            openFingerprintRegistrationModal(userId, username, isRegistered);
        } else {
            this.showNotification('Fingerprint registration modal is not available. Please ensure system-settings.js is loaded.', 'error');
        }
    }
    
    async handleFingerprintUnregister(userId, username) {
        if (!userId) {
            this.showNotification('Invalid user selected', 'error');
            return;
        }
        
        const confirmMessage = `
            <div class="confirm-dialog-content">
                <i class="fas fa-exclamation-triangle confirm-dialog-icon warning"></i>
                <p class="confirm-dialog-title warning">Unregister Fingerprint</p>
                <p>Are you sure you want to unregister the fingerprint for <strong>${username || 'this user'}</strong>?</p>
                <p class="confirm-dialog-text">⚠️ This action cannot be undone. The user will need to register again to use fingerprint authentication.</p>
            </div>
        `;
        const confirmUnreg = await this.confirmDialog(confirmMessage);
        if (!confirmUnreg) {
            return;
        }
        
        try {
            this.showNotification(`🗑️ Removing fingerprint registration for ${username || 'user'}...`, 'info');
            await this.unregisterFingerprintTemplate(userId, username || '');
            
            // Small delay to ensure database transaction is fully committed
            await new Promise(resolve => setTimeout(resolve, 150));
            
            // Force refresh data - reload fresh data from API
            
            // Populate country name cache before loading data (non-blocking - don't await to avoid blocking on permission errors)
            // This is optional and errors are handled internally, so we don't need to wait for it
            this.populateCountryNameCache().catch(() => {
                // Silently ignore - country cache is optional and not critical for fingerprint refresh
            });
            
            // Load fresh data directly
            const response = await this.apiCall('get_all', this.currentTable);
            if (response.success) {
                this.data = response.data || [];
            } else {
                throw new Error(response.message || 'Failed to refresh data');
            }
            
            // Check if we're in profile modal context (dashboard profile modal)
            const profileModal = document.getElementById('mainModal');
            const modalBody = profileModal?.querySelector('#modalBody') || profileModal?.querySelector('.modal-body');
            const isProfileModal = profileModal && modalBody && modalBody.classList.contains('profile-modal-content');
            
            if (isProfileModal && this.currentTable === 'users') {
                // Re-filter to show only current user in profile modal
                const profileCard = document.querySelector('.system-card[data-action="open-profile-modal"]');
                const currentUserId = profileCard ? parseInt(profileCard.getAttribute('data-user-id')) : null;
                
                if (currentUserId && this.data && Array.isArray(this.data)) {
                    // Filter data to show only current user
                    this.data = this.data.filter(user => user.user_id == currentUserId);
                    
                    // Check for duplicates and remove them
                    const seen = new Set();
                    this.data = this.data.filter(user => {
                        if (seen.has(user.user_id)) {
                            return false;
                        }
                        seen.add(user.user_id);
                        return true;
                    });
                    
                    // Recalculate stats for filtered data
                    const filteredStats = {
                        total: this.data.length,
                        active: this.data.filter(user => {
                            const status = user.status || user.is_active;
                            return status === 'active' || status === 1 || status === '1';
                        }).length,
                        inactive: this.data.filter(user => {
                            const status = user.status || user.is_active;
                            return status === 'inactive' || status === 0 || status === '0';
                        }).length,
                        today: 0,
                        thisWeek: 0,
                        thisMonth: 0
                    };
                    
                    this.currentTableStats = filteredStats;
                    this.renderTableWithStats(filteredStats);
                } else {
                    // Fallback to normal refresh if filtering fails
                    const latestStats = await this.loadTableStats(this.currentTable);
                    this.renderTableWithStats(latestStats);
                }
            } else {
                // Load fresh stats and render normally (for system settings pages)
                const latestStats = await this.loadTableStats(this.currentTable);
                this.renderTableWithStats(latestStats);
            }
            
            this.showNotification(`✅ Fingerprint unregistered successfully! ${username || 'User'} can no longer use fingerprint authentication. They will need to register again to use this feature.`, 'success', 6000);
        } catch (error) {
            console.error('❌ Fingerprint unregister failed:', error);
            const errorMessage = `❌ Failed to unregister fingerprint for ${username || 'user'}: ${error.message || 'Unknown error'}`;
            this.showNotification(errorMessage, 'error', 7000);
        }
    }
    
    async registerFingerprintTemplate(userId, username) {
        // Use real WebAuthn fingerprint scanning for registration
        if (!window.PublicKeyCredential) {
            throw new Error('Your browser does not support fingerprint authentication. Please use a modern browser (Chrome, Edge, or Firefox).');
        }
        
        // Check if Windows Hello is available
        try {
            const isAvailable = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
            if (!isAvailable) {
                throw new Error('Windows Hello is not available. Please set up Windows Hello in Windows Settings > Accounts > Sign-in options > Windows Hello Fingerprint.');
            }
        } catch (checkError) {
            if (checkError.message.includes('Windows Hello')) {
                throw checkError;
            }
            console.warn('Could not check Windows Hello availability:', checkError);
        }
        
        try {
            // Step 1: Get registration challenge from server
            const apiBase = getApiBaseModernForms();
            const startResponse = await fetch(apiBase + '/webauthn/register_start.php' + getControlSuffixModernForms(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ userId: userId, username: username }),
                credentials: 'same-origin'
            });
            
            const startData = await startResponse.json();
            
            if (!startData.publicKey) {
                throw new Error(startData.message || 'Failed to start fingerprint registration');
            }
            
            // Step 2: Convert challenge from base64 to ArrayBuffer
            const challenge = Uint8Array.from(atob(startData.publicKey.challenge), c => c.charCodeAt(0));
            const userIdBuffer = Uint8Array.from(atob(startData.publicKey.user.id), c => c.charCodeAt(0));
            
            // Step 3: Request credential creation with real fingerprint scan
            const publicKeyCredentialCreationOptions = {
                challenge: challenge,
                rp: startData.publicKey.rp,
                user: {
                    id: userIdBuffer,
                    name: startData.publicKey.user.name,
                    displayName: startData.publicKey.user.displayName
                },
                pubKeyCredParams: startData.publicKey.pubKeyCredParams,
                timeout: 120000,
                attestation: 'none',
                authenticatorSelection: {
                    userVerification: 'required',
                    authenticatorAttachment: 'platform'
                }
            };
            
            // Step 4: Request fingerprint scan from hardware
            const credential = await navigator.credentials.create({
                publicKey: publicKeyCredentialCreationOptions
            });
            
            if (!credential) {
                throw new Error('Fingerprint scan was cancelled or failed');
            }
            
            // Step 5: Convert credential to sendable format
            const credentialId = btoa(String.fromCharCode(...new Uint8Array(credential.rawId)));
            const attestationObject = btoa(String.fromCharCode(...new Uint8Array(credential.response.attestationObject)));
            const publicKey = attestationObject;
            
            // Step 6: Send credential to server for storage in database
            const finishResponse = await fetch(apiBase + '/webauthn/register_finish.php' + getControlSuffixModernForms(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    credentialId: credentialId,
                    publicKey: publicKey,
                    attestationObject: attestationObject
                }),
                credentials: 'same-origin'
            });
            
            if (!finishResponse.ok) {
                const text = await finishResponse.text();
                console.error('❌ Fingerprint registration HTTP error:', finishResponse.status, text);
                throw new Error(`Server error (${finishResponse.status}) while registering fingerprint`);
            }
            
            const finishData = await finishResponse.json();
            if (!finishData.success) {
                console.error('❌ Fingerprint registration error payload:', finishData);
                throw new Error(finishData.message || 'Fingerprint registration failed');
            }
        } catch (error) {
            // Check if user cancelled - NotAllowedError with "timed out" or "not allowed" usually means cancellation
            const errorMsg = (error.message || '').toLowerCase();
            const isCancellation = error.name === 'NotAllowedError' && (
                errorMsg.includes('cancel') ||
                errorMsg.includes('timed out') ||
                errorMsg.includes('timeout') ||
                errorMsg.includes('not allowed') ||
                errorMsg.includes('the operation either timed out or was not allowed')
            );
            
            // If user cancelled, throw a special error that can be detected by the caller (don't log as error)
            if (isCancellation) {
                const cancelError = new Error('Fingerprint registration was cancelled by user');
                cancelError.name = 'CancellationError';
                cancelError.isCancellation = true;
                throw cancelError;
            }
            
            // Only log actual errors (not cancellations)
            console.error('❌ Fingerprint registration error:', error);
            
            let errorMessage = 'Failed to register fingerprint';
            
            if (error.name === 'NotAllowedError') {
                errorMessage = 'Windows Hello is not set up. Please set up Windows Hello first.';
            } else if (error.name === 'InvalidStateError') {
                errorMessage = 'Fingerprint is already registered. Please unregister first.';
            } else if (error.name === 'NotSupportedError') {
                errorMessage = 'Fingerprint authentication is not supported on this device.';
            } else if (error.message) {
                errorMessage = error.message;
            }
            
            throw new Error(errorMessage);
        }
    }
    
    generateMockFingerprintTemplate(username) {
        // Generate consistent mock fingerprint data based on username
        const normalized = (username || '').toLowerCase().trim();
        const seed = normalized ? `fingerprint_template_${normalized}` : 'fingerprint_template_unknown';
        if (typeof TextEncoder !== 'undefined') {
            const encoder = new TextEncoder();
            const bytes = encoder.encode(seed);
            let binary = '';
            bytes.forEach(byte => {
                binary += String.fromCharCode(byte);
            });
            return btoa(binary);
        }
        try {
            return btoa(unescape(encodeURIComponent(seed)));
        } catch (error) {
            return btoa(seed);
        }
    }
    
    async unregisterFingerprintTemplate(userId, username) {
        const formData = new FormData();
        formData.append('userId', userId);
        formData.append('username', username);
        
        const apiBase = getApiBaseModernForms();
        const response = await fetch(apiBase + '/biometric/unregister_fingerprint_admin.php' + getControlSuffixModernForms(), {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            const text = await response.text();
            console.error('❌ Fingerprint unregister API HTTP error:', response.status, text);
            throw new Error(`Server error (${response.status}) while unregistering fingerprint`);
        }
        
        const data = await response.json();
        if (!data.success) {
            console.error('❌ Fingerprint unregister API error payload:', data);
            throw new Error(data.message || 'Fingerprint unregister failed');
        }
    }
    
    generateMockFingerprintTemplate(username) {
        const normalized = (username || '').toLowerCase().trim();
        // Use only the base seed without timestamp/random for consistent matching
        // This ensures the same username always generates the same fingerprint
        const seed = normalized ? `fingerprint_template_${normalized}` : 'fingerprint_template_unknown';
        if (typeof TextEncoder !== 'undefined') {
            const encoder = new TextEncoder();
            const bytes = encoder.encode(seed);
            let binary = '';
            bytes.forEach(byte => {
                binary += String.fromCharCode(byte);
            });
            return btoa(binary);
        }
        try {
            return btoa(unescape(encodeURIComponent(seed)));
        } catch (error) {
            return btoa(seed);
        }
    }
    
    // Close form modal
    async closeFormModal(skipConfirmation = false) {
        // Check if SystemSettingsAlert is available and show confirmation (unless skipConfirmation is true)
        if (!skipConfirmation && window.SystemSettingsAlert) {
            const result = await window.SystemSettingsAlert.show({
                title: 'Close Form',
                message: 'Are you sure you want to close this form? Any unsaved changes will be lost.',
                type: 'warning',
                confirmText: 'Close',
                cancelText: 'Cancel'
            });
            
            if (result.action !== 'confirm') {
                return; // User cancelled
            }
        }
        
        // Reset company info mode flag
        this.isCompanyInfoMode = false;
        
        const modal = document.getElementById('formPopupModal');
        if (modal) {
            modal.classList.add('modal-hidden');
            modal.classList.remove('show');
        }
    }
    
    // Open history modal (table-specific or system-wide)
    async openHistoryModal(tableName = null) {
        const modal = document.getElementById('historyModal');
        const title = document.getElementById('historyModalTitle');
        const body = document.getElementById('historyModalBody');
        
        if (!modal || !title || !body) return;
        
        const displayTable = tableName || this.currentTable;
        if (displayTable) {
            title.textContent = `${this.getSettingTitle(displayTable)} - Activity History`;
        } else {
            title.textContent = 'System Activity History';
        }
        
        body.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i> Loading history...</div>';
        
        modal.classList.remove('modal-hidden');
        modal.classList.add('show');
        
        try {
            const history = await this.loadHistory(displayTable);
            this.renderHistory(history, !displayTable); // Show table name if system-wide
        } catch (error) {
            // Show error but don't break the UI
            body.innerHTML = `<div class="error-state"><i class="fas fa-exclamation-triangle"></i><p>Failed to load history: ${error.message}</p><p class="error-hint">History tracking may not be initialized yet.</p></div>`;
        }
    }
    
    // Open system-wide history modal
    async openSystemHistory() {
        await this.openHistoryModal(null);
    }
    
    // Close history modal
    closeHistoryModal() {
        const modal = document.getElementById('historyModal');
        if (modal) {
            modal.classList.add('modal-hidden');
            modal.classList.remove('show');
        }
    }
    
    // Load history from API
    async loadHistory(tableName = null, limit = 100) {
        try {
            const apiBase = getApiBaseModernForms();
            const ctrl = getControlSuffixModernForms();
            let url;
            if (tableName) {
                url = `${apiBase}/core/global-history-api.php?action=get_history&table=${encodeURIComponent(tableName)}&limit=${limit}${ctrl}`;
            } else {
                url = `${apiBase}/core/global-history-api.php?action=get_history&limit=${limit}${ctrl}`;
            }
            
            const response = await fetch(url, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });
            
            // Check if response is OK
            if (!response.ok) {
                console.error('❌ History API response not OK:', response.status, response.statusText);
                // Fallback to settings history API if global fails
                if (tableName && response.status === 404) {
                    url = `${apiBase}/settings/history-api.php?action=get_history&table=${encodeURIComponent(tableName)}&limit=${limit}${ctrl}`;
                    const fallbackResponse = await fetch(url, {
                        method: 'GET',
                        headers: { 'Content-Type': 'application/json' }
                    });
                    if (fallbackResponse.ok) {
                        const fallbackText = await fallbackResponse.text();
                        const fallbackData = JSON.parse(fallbackText);
                        if (fallbackData.success) {
                            return fallbackData.data || [];
                        }
                    }
                }
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Read response as text first to check for JSON
            const text = await response.text();
            
            if (!text.trim()) {
                return []; // Empty response, return empty array
            }
            
            try {
                const data = JSON.parse(text);
                
                if (data.success) {
                    const history = data.data || [];
                    return history;
                }
                console.error('❌ History API returned success=false:', data.message);
                throw new Error(data.message || 'Failed to load history');
            } catch (parseError) {
                console.error('❌ JSON parse error:', parseError);
                console.error('Response text:', text.substring(0, 500));
                throw new Error('Server returned invalid JSON. Response: ' + text.substring(0, 100));
            }
        } catch (error) {
            console.error('❌ History load error:', error);
            // Return empty array instead of throwing - history is optional
            return [];
        }
    }
    
    // Format JSON data for display
    formatJSONData(data) {
        if (!data) return '<em class="text-muted">No data</em>';
        if (typeof data === 'string') {
            try {
                data = JSON.parse(data);
            } catch (e) {
                return `<code>${this.escapeHtml(String(data))}</code>`;
            }
        }
        return '<pre class="history-json">' + this.escapeHtml(JSON.stringify(data, null, 2)) + '</pre>';
    }
    
    // Escape HTML to prevent XSS
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Format date with relative time
    formatDate(dateStr) {
        const date = new Date(dateStr);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        let relative = '';
        if (diffMins < 1) relative = 'Just now';
        else if (diffMins < 60) relative = `${diffMins}m ago`;
        else if (diffHours < 24) relative = `${diffHours}h ago`;
        else if (diffDays < 7) relative = `${diffDays}d ago`;
        else relative = date.toLocaleDateString();
        
        const absolute = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        return { relative, absolute };
    }
    
    // Render history list with enhanced details
    renderHistory(history, showTableName = false) {
        const body = document.getElementById('historyModalBody');
        if (!body) return;
        
        if (!history || history.length === 0) {
            body.innerHTML = '<div class="empty-state"><i class="fas fa-history"></i><p>No history records found</p></div>';
            return;
        }
        
        let html = '<div class="history-list">';
        
        history.forEach((item, index) => {
            const actionIcon = item.action === 'create' ? 'plus-circle' : item.action === 'update' ? 'edit' : 'trash';
            const actionLabel = item.action.charAt(0).toUpperCase() + item.action.slice(1);
            const dateInfo = this.formatDate(item.created_at);
            
            // Get module/table name for display
            const moduleName = item.module || 'general';
            const tableDisplay = showTableName && item.table_name ? 
                `<span class="history-record-id">${item.table_name}</span>` : '';
            
            // Parse JSON data
            let oldData = null;
            let newData = null;
            let changedFields = null;
            
            try {
                if (item.old_data) oldData = typeof item.old_data === 'string' ? JSON.parse(item.old_data) : item.old_data;
                if (item.new_data) newData = typeof item.new_data === 'string' ? JSON.parse(item.new_data) : item.new_data;
                if (item.changed_fields) changedFields = typeof item.changed_fields === 'string' ? JSON.parse(item.changed_fields) : item.changed_fields;
            } catch (e) {
                console.warn('Failed to parse history JSON:', e);
            }
            
            const itemId = `history-item-${index}`;
            const actionClass = item.action === 'create' ? 'history-create' : item.action === 'update' ? 'history-update' : 'history-delete';
            
            // Extract key fields from data for quick display
            let keyFields = {
                name: null,
                position: null,
                email: null,
                phone: null,
                status: null,
                city: null,
                country: null,
                created_at: null,
                updated_at: null
            };
            
            // Try to get from new_data first, then old_data
            const dataSource = newData || oldData;
            if (dataSource) {
                keyFields.name = dataSource.name || dataSource.office_manager_name || dataSource.manager_name || null;
                keyFields.position = dataSource.position || dataSource.manager_position || null;
                keyFields.email = dataSource.email || null;
                keyFields.phone = dataSource.phone || dataSource.phone_number || null;
                keyFields.status = dataSource.status || dataSource.is_active !== undefined ? (dataSource.is_active ? 'Active' : 'Inactive') : null;
                keyFields.city = dataSource.city || null;
                keyFields.country = dataSource.country || dataSource.country_name || null;
                keyFields.created_at = dataSource.created_at || null;
                keyFields.updated_at = dataSource.updated_at || null;
            }
            
            // Build key info display - Name, Position, and Date/Time
            let keyInfoHtml = '';
            if (keyFields.name || keyFields.position || keyFields.updated_at || keyFields.created_at) {
                keyInfoHtml = '<div class="history-key-info">';
                if (keyFields.name) {
                    keyInfoHtml += `<div class="history-key-field"><i class="fas fa-user-circle"></i> <strong>Name:</strong> <span>${this.escapeHtml(String(keyFields.name))}</span></div>`;
                }
                if (keyFields.position) {
                    keyInfoHtml += `<div class="history-key-field"><i class="fas fa-briefcase"></i> <strong>Position:</strong> <span>${this.escapeHtml(String(keyFields.position))}</span></div>`;
                }
                // Show updated_at if available, otherwise created_at
                const dateTimeField = keyFields.updated_at || keyFields.created_at;
                if (dateTimeField) {
                    const dateTime = new Date(dateTimeField);
                    const dateLabel = keyFields.updated_at ? 'Updated' : 'Created';
                    const dateIcon = keyFields.updated_at ? 'calendar-check' : 'calendar-plus';
                    keyInfoHtml += `<div class="history-key-field"><i class="fas fa-${dateIcon}"></i> <strong>${dateLabel}:</strong> <span>${dateTime.toLocaleDateString()} ${dateTime.toLocaleTimeString()}</span></div>`;
                }
                keyInfoHtml += '</div>';
            }
            
            // Changed fields summary
            let changesSummary = '';
            if (item.action === 'update' && changedFields && Object.keys(changedFields).length > 0) {
                const fields = Object.keys(changedFields);
                changesSummary = `<div class="history-changes-summary"><strong>Changed Fields (${fields.length}):</strong> ${fields.slice(0, 3).join(', ')}${fields.length > 3 ? ` +${fields.length - 3} more` : ''}</div>`;
            }
            
            // User agent parsing
            let userAgentInfo = '';
            if (item.user_agent) {
                const ua = item.user_agent;
                let browser = 'Unknown';
                let os = 'Unknown';
                
                if (ua.includes('Chrome')) browser = 'Chrome';
                else if (ua.includes('Firefox')) browser = 'Firefox';
                else if (ua.includes('Safari')) browser = 'Safari';
                else if (ua.includes('Edge')) browser = 'Edge';
                
                if (ua.includes('Windows')) os = 'Windows';
                else if (ua.includes('Mac')) os = 'macOS';
                else if (ua.includes('Linux')) os = 'Linux';
                else if (ua.includes('Android')) os = 'Android';
                else if (ua.includes('iOS')) os = 'iOS';
                
                userAgentInfo = `<span><i class="fas fa-desktop"></i> ${browser} on ${os}</span>`;
            }
            
            html += `
                <div class="history-item" data-item-id="${itemId}">
                    <div class="history-icon ${actionClass}">
                        <i class="fas fa-${actionIcon}"></i>
                    </div>
                    <div class="history-content">
                        <div class="history-header">
                            <div class="history-header-left">
                                <span class="history-action ${actionClass}">${actionLabel}</span>
                            </div>
                            <div class="history-header-right">
                                ${tableDisplay}
                                ${showTableName && moduleName !== 'general' ? `<span class="history-record-id">${moduleName}</span>` : ''}
                                <span class="history-record-id">#${item.record_id}</span>
                                <span class="history-date" title="${dateInfo.absolute}">${dateInfo.relative}</span>
                                <button class="history-toggle-details" data-toggle="${itemId}" title="Toggle details">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                        </div>
                        ${keyInfoHtml}
                        <div class="history-meta">
                            ${item.user_name ? `<span><i class="fas fa-user"></i> ${item.user_name}</span>` : '<span><i class="fas fa-user"></i> System</span>'}
                            ${item.user_id ? `<span><i class="fas fa-id-badge"></i> User ID: ${item.user_id}</span>` : ''}
                            ${item.ip_address ? `<span><i class="fas fa-network-wired"></i> ${item.ip_address}</span>` : ''}
                            ${userAgentInfo}
                        </div>
                        ${changesSummary}
                        
                        <!-- Expandable Details Section -->
                        <div class="history-details d-none" id="${itemId}-details">
                            ${item.action === 'update' && changedFields && Object.keys(changedFields).length > 0 ? `
                                <div class="history-section">
                                    <div class="history-section-header">
                                        <i class="fas fa-exchange-alt"></i> Changed Fields (${Object.keys(changedFields).length})
                                    </div>
                                    <div class="history-section-body">
                                        <table class="history-fields-table">
                                            <thead>
                                                <tr>
                                                    <th>Field</th>
                                                    <th>Old Value</th>
                                                    <th>New Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${Object.keys(changedFields).map(field => {
                                                    const change = changedFields[field];
                                                    const oldVal = change.old === null ? '<em>null</em>' : this.escapeHtml(String(change.old));
                                                    const newVal = change.new === null ? '<em>null</em>' : this.escapeHtml(String(change.new));
                                                    return `<tr>
                                                        <td><strong>${this.escapeHtml(field)}</strong></td>
                                                        <td class="history-old-value">${oldVal}</td>
                                                        <td class="history-new-value">${newVal}</td>
                                                    </tr>`;
                                                }).join('')}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            ` : ''}
                            
                            ${oldData ? `
                                <div class="history-section">
                                    <div class="history-section-header">
                                        <i class="fas fa-arrow-left"></i> Previous Data
                                    </div>
                                    <div class="history-section-body">
                                        ${this.formatJSONData(oldData)}
                                    </div>
                                </div>
                            ` : ''}
                            
                            ${newData ? `
                                <div class="history-section">
                                    <div class="history-section-header">
                                        <i class="fas fa-arrow-right"></i> ${item.action === 'create' ? 'Created Data' : 'Updated Data'}
                                    </div>
                                    <div class="history-section-body">
                                        ${this.formatJSONData(newData)}
                                    </div>
                                </div>
                            ` : ''}
                            
                            ${item.user_agent ? `
                                <div class="history-section">
                                    <div class="history-section-header">
                                        <i class="fas fa-info-circle"></i> Technical Details
                                    </div>
                                    <div class="history-section-body">
                                        <div class="history-tech-details">
                                            <div><strong>User Agent:</strong> <code>${this.escapeHtml(item.user_agent)}</code></div>
                                            ${item.ip_address ? `<div><strong>IP Address:</strong> ${item.ip_address}</div>` : ''}
                                            <div><strong>Timestamp:</strong> ${dateInfo.absolute}</div>
                                        </div>
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        body.innerHTML = html;
        
        // Bind toggle buttons
        body.querySelectorAll('.history-toggle-details').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const toggleId = btn.getAttribute('data-toggle');
                const details = document.getElementById(`${toggleId}-details`);
                const icon = btn.querySelector('i');
                
                if (details.classList.contains('hidden')) {
                    details.classList.remove('hidden');
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                } else {
                    details.classList.add('hidden');
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                }
            });
        });
    }
    
    // Close all modals
    closeAllModals() {
        document.querySelectorAll('.modern-modal').forEach(modal => {
            modal.classList.add('modal-hidden');
            modal.classList.remove('show');
        });
    }
    
    // API call
    async apiCall(action, table, data = {}) {
        // Ensure action and table are clean strings (declare outside try so accessible in catch)
        const cleanAction = String(action).trim();
        const cleanTable = String(table).trim();
        
        try {
            
            // Build payload - ensure action and table are NEVER overwritten
            const payload = {};
            
            // First add all data fields
            for (const key in data) {
                if (data.hasOwnProperty(key) && key !== 'action' && key !== 'table') {
                    payload[key] = data[key];
                }
            }
            
            // Then set action and table last (always wins)
            payload.action = cleanAction;
            payload.table = cleanTable;
            
            const response = await fetch(getSettingsApiPathModernForms(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache',
                },
                cache: 'no-store',
                credentials: 'include',
                body: JSON.stringify(payload)
            });
            
            const text = await response.text();
            
            // Try to parse as JSON
            let json;
            try {
                json = JSON.parse(text);
            } catch (parseError) {
                // Not valid JSON
                const preview = text.trim() || '(empty response)';
                console.error('Non-JSON response received:', preview.substring(0, 500));
                console.error('Response length:', text.length);
                console.error('Response status:', response.status);
                throw new Error(`Server returned invalid JSON. Response: ${preview.substring(0, 100)}`);
            }
            
            // Check for error response FIRST (before checking response.ok)
            if (json.success === false) {
                const errorMessage = json.message || 'Request failed';
                const isPermissionError = errorMessage.includes('permission') || errorMessage.includes('Access denied');
                
                // Don't log permission errors as errors - they're expected when user lacks permissions
                if (!isPermissionError) {
                    console.error('❌ API error response:', errorMessage);
                    console.error('📋 Full error response:', JSON.stringify(json, null, 2));
                }
                
                // Create error with permission flag
                const error = new Error(errorMessage);
                error.isPermissionError = isPermissionError;
                throw error;
            }
            
            if (!response.ok) {
                throw new Error(json.message || `HTTP error! status: ${response.status}`);
            }
            
            return json;
        } catch (error) {
            const errorMessage = error.message || String(error);
            const isPermissionError = errorMessage.includes('permission') || errorMessage.includes('Access denied') || error.isPermissionError;
            
            // Mark permission errors so they can be handled gracefully
            if (isPermissionError && !error.isPermissionError) {
                error.isPermissionError = true;
            }
            
            // Don't log permission errors - they're expected
            if (!isPermissionError) {
                console.error('API call failed:', error);
            }
            
            throw error;
        }
    }
    
    // Show notification
    showNotification(message, type = 'info', duration = null) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type} show`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation' : 'info'}-circle"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        // Use custom duration or default based on type
        const displayDuration = duration !== null ? duration : (type === 'success' ? 5000 : type === 'error' ? 6000 : 3000);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, displayDuration);
    }
    
    // Show error
    showError(message) {
        const body = document.getElementById('modalBody');
        if (body) {
            body.innerHTML = `
                <div class="error-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Error</h3>
                    <p>${message}</p>
                </div>
            `;
        }
    }
    
    // Load stats
    async loadStats() {
        const stats = [
            { id: 'totalUsers', table: 'users' },
            { id: 'usersCount', table: 'users' },
            { id: 'totalAgents', table: 'agents' },
            { id: 'totalWorkers', table: 'workers' },
            { id: 'totalCases', table: 'cases' }
        ];
        
        for (const stat of stats) {
            try {
                const response = await this.apiCall('get_stats', stat.table);
                if (response.success) {
                    const element = document.getElementById(stat.id);
                    if (element) {
                        element.textContent = response.data.total;
                    }
                } else {
                    // Set to 0 if table doesn't exist
                    const element = document.getElementById(stat.id);
                    if (element) {
                        element.textContent = '0';
                    }
                }
            } catch (error) {
                console.error(`Failed to load stats for ${stat.table}:`, error);
                // Set to 0 on error
                const element = document.getElementById(stat.id);
                if (element) {
                    element.textContent = '0';
                }
            }
        }
        
        // Load system history stats
        await this.loadHistoryStats();
    }
    
    // Load history statistics (from global history API - sum of all modules)
    async loadHistoryStats() {
        try {
            // Try global history API first (for entire system)
            const url = getApiBaseModernForms() + '/core/global-history-api.php?action=get_stats' + getControlSuffixModernForms();
            const response = await fetch(url, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const text = await response.text();
            if (!text.trim()) {
                const element = document.getElementById('totalHistories');
                const badgeElement = document.getElementById('systemHistoryCountBadge');
                if (element) element.textContent = '0';
                if (badgeElement) badgeElement.textContent = '0';
                return;
            }
            
            const data = JSON.parse(text);
            if (data.success) {
                const total = data.data?.total || 0;
                const element = document.getElementById('totalHistories');
                const badgeElement = document.getElementById('systemHistoryCountBadge');
                if (element) {
                    element.textContent = total;
                }
                if (badgeElement) {
                    badgeElement.textContent = total;
                }
            } else {
                const element = document.getElementById('totalHistories');
                const badgeElement = document.getElementById('systemHistoryCountBadge');
                if (element) element.textContent = '0';
                if (badgeElement) badgeElement.textContent = '0';
            }
        } catch (error) {
            console.error('Failed to load history stats:', error);
            const element = document.getElementById('totalHistories');
            const badgeElement = document.getElementById('systemHistoryCountBadge');
            if (element) element.textContent = '0';
            if (badgeElement) badgeElement.textContent = '0';
        }
    }
    
    // Get setting title
    getSettingTitle(setting) {
        const titles = {
            'office_managers': 'Office Manager',
            'visa_types': 'Visa Types',
            'recruitment_countries': 'Recruitment Countries',
            'job_categories': 'Job Categories',
            'age_specifications': 'Age Specifications',
            'appearance_specifications': 'Appearance Specifications',
            'status_specifications': 'Status Specifications',
            'request_statuses': 'Request Statuses',
            'arrival_agencies': 'Arrival Agencies',
            'arrival_stations': 'Arrival Stations',
            'worker_statuses': 'Worker Statuses',
            'system_config': 'System Configuration',
            'users': 'Users',
            'currencies': 'Currency'
        };
        return titles[setting] || 'Settings';
    }
    
    // Get table configuration
    getTableConfig(setting) {
        const configs = {
            'office_managers': {
                columns: [
                    { key: 'name', label: 'Name', type: 'text', maxLen: 16, maxWidth: 100 },
                    { key: 'email', label: 'Email', type: 'text', maxLen: 20, maxWidth: 140 },
                    { key: 'phone', label: 'Phone', type: 'text', maxLen: 14, maxWidth: 90 },
                    { key: 'position', label: 'Position', type: 'text', maxLen: 16, maxWidth: 100 },
                    { key: 'country_id', label: 'Country', type: 'text', maxLen: 18, maxWidth: 100 },
                    { key: 'city', label: 'City', type: 'text', maxLen: 16, maxWidth: 100 },
                    { key: 'status', label: 'Status', type: 'status', maxWidth: 80 }
                ]
            },
            'visa_types': {
                columns: [
                    { key: 'name', label: 'Visa Type', type: 'text', maxLen: 28, maxWidth: 140 },
                    { key: 'description', label: 'Role / detail', type: 'text', maxLen: 28, maxWidth: 160 },
                    { key: 'validity_days', label: 'Validity (Days)', type: 'number', maxLen: 5, maxWidth: 80 },
                    { key: 'processing_fee', label: 'Fee', type: 'currency', maxLen: 8, maxWidth: 85 },
                    { key: 'country_id', label: 'Country', type: 'text', maxLen: 18, maxWidth: 100 },
                    { key: 'city', label: 'City', type: 'text', maxLen: 16, maxWidth: 100 },
                    { key: 'status', label: 'Status', type: 'status', maxWidth: 80 }
                ]
            },
            'recruitment_countries': {
                columns: [
                    { key: 'name', label: 'Country', type: 'text', maxLen: 18, maxWidth: 110 },
                    { key: 'code', label: 'Code', type: 'text', maxLen: 5, maxWidth: 60 },
                    { key: 'city', label: 'City', type: 'text', maxLen: 16, maxWidth: 100 },
                    { key: 'currency', label: 'Currency', type: 'text', maxLen: 8, maxWidth: 75 },
                    { key: 'flag_emoji', label: 'Flag', type: 'text', maxLen: 4, maxWidth: 50 },
                    { key: 'status', label: 'Status', type: 'status', maxWidth: 80 }
                ]
            },
            'job_categories': {
                columns: [
                    { key: 'name', label: 'Category', type: 'text', maxLen: 16, maxWidth: 110 },
                    { key: 'description', label: 'Description', type: 'text', maxLen: 28, maxWidth: 160 },
                    { key: 'min_salary', label: 'Min Salary', type: 'currency', maxLen: 10, maxWidth: 85 },
                    { key: 'max_salary', label: 'Max Salary', type: 'currency', maxLen: 10, maxWidth: 85 },
                    { key: 'country_id', label: 'Country', type: 'text', maxLen: 18, maxWidth: 100 },
                    { key: 'city', label: 'City', type: 'text', maxLen: 16, maxWidth: 100 },
                    { key: 'status', label: 'Status', type: 'status', maxWidth: 80 }
                ]
            },
            'age_specifications': {
                columns: [
                    { key: 'name', label: 'Age Range', type: 'text', maxLen: 16, maxWidth: 100 },
                    { key: 'description', label: 'Description', type: 'text', maxLen: 28, maxWidth: 160 },
                    { key: 'country_id', label: 'Country', type: 'text', maxLen: 18, maxWidth: 100 },
                    { key: 'city', label: 'City', type: 'text', maxLen: 16, maxWidth: 100 },
                    { key: 'status', label: 'Status', type: 'status', maxWidth: 80 }
                ]
            },
            'status_specifications': {
                columns: [
                    { key: 'name', label: 'Status Name', type: 'text', maxLen: 16, maxWidth: 100 },
                    { key: 'description', label: 'Description', type: 'text', maxLen: 28, maxWidth: 160 },
                    { key: 'country_id', label: 'Country', type: 'text', maxLen: 18, maxWidth: 100 },
                    { key: 'city', label: 'City', type: 'text', maxLen: 16, maxWidth: 100 },
                    { key: 'status', label: 'Status', type: 'status', maxWidth: 80 }
                ]
            },
            'arrival_agencies': {
                columns: [
                    { key: 'name', label: 'Agency', type: 'text', maxLen: 16, maxWidth: 100 },
                    { key: 'description', label: 'Description', type: 'text', maxLen: 28, maxWidth: 160 },
                    { key: 'country_id', label: 'Country', type: 'text', maxLen: 18, maxWidth: 100 },
                    { key: 'city', label: 'City', type: 'text', maxLen: 16, maxWidth: 100 },
                    { key: 'status', label: 'Status', type: 'status', maxWidth: 80 }
                ]
            },
            'arrival_stations': {
                columns: [
                    { key: 'name', label: 'Station', type: 'text', maxLen: 16, maxWidth: 100 },
                    { key: 'description', label: 'Description', type: 'text', maxLen: 28, maxWidth: 160 },
                    { key: 'country_id', label: 'Country', type: 'text', maxLen: 18, maxWidth: 100 },
                    { key: 'city', label: 'City', type: 'text', maxLen: 16, maxWidth: 100 },
                    { key: 'status', label: 'Status', type: 'status', maxWidth: 80 }
                ]
            },
            'worker_statuses': {
                columns: [
                    { key: 'name', label: 'Status Name', type: 'text', maxLen: 16, maxWidth: 100 },
                    { key: 'description', label: 'Description', type: 'text', maxLen: 28, maxWidth: 160 },
                    { key: 'country_id', label: 'Country', type: 'text', maxLen: 18, maxWidth: 100 },
                    { key: 'city', label: 'City', type: 'text', maxLen: 16, maxWidth: 100 },
                    { key: 'status', label: 'Status', type: 'status', maxWidth: 80 }
                ]
            },
            'system_config': {
                columns: [
                    { key: 'name', label: 'Config Key', type: 'text', maxLen: 18, maxWidth: 120 },
                    { key: 'description', label: 'Description', type: 'text', maxLen: 28, maxWidth: 160 },
                    { key: 'country_id', label: 'Country', type: 'text', maxLen: 18, maxWidth: 100 },
                    { key: 'city', label: 'City', type: 'text', maxLen: 16, maxWidth: 100 },
                    { key: 'status', label: 'Status', type: 'status', maxWidth: 80 }
                ]
            },
            'users': {
                columns: (() => {
                    const isControl = (typeof isControlPanelContext === 'function' && isControlPanelContext()) || (window.location && window.location.search && window.location.search.includes('control=1'));
                    if (isControl) {
                        return [
                            { key: 'name', label: 'Username', type: 'text', maxLen: 16, maxWidth: 100 },
                            { key: 'password', label: 'Password', type: 'password', maxWidth: 100 },
                            { key: 'permissions', label: 'Permissions', type: 'permissions', maxWidth: 130 },
                            { key: 'status', label: 'Status', type: 'status', maxWidth: 80 }
                        ];
                    }
                    const cols = [
                        { key: 'name', label: 'Username', type: 'text', maxLen: 16, maxWidth: 100 },
                        { key: 'password', label: 'Password', type: 'password', maxWidth: 100 },
                        { key: 'email', label: 'Email', type: 'text', maxLen: 20, maxWidth: 140 },
                        { key: 'phone', label: 'Phone', type: 'text', maxLen: 14, maxWidth: 90 },
                        { key: 'permissions', label: 'Permissions', type: 'permissions', maxWidth: 130 },
                        { key: 'status', label: 'Status', type: 'status', maxWidth: 80 }
                    ];
                    cols.splice(4, 0, { key: 'fingerprint_status', label: 'Fingerprint', type: 'fingerprint', maxWidth: 120 });
                    return cols;
                })()
            },
            'appearance_specifications': {
                columns: [
                    { key: 'name', label: 'Specification Name', type: 'text', maxLen: 16, maxWidth: 100 },
                    { key: 'description', label: 'Description', type: 'text', maxLen: 28, maxWidth: 160 },
                    { key: 'country_id', label: 'Country', type: 'text', maxLen: 18, maxWidth: 100 },
                    { key: 'city', label: 'City', type: 'text', maxLen: 16, maxWidth: 100 },
                    { key: 'status', label: 'Status', type: 'status', maxWidth: 80 }
                ]
            },
            'request_statuses': {
                columns: [
                    { key: 'name', label: 'Status Name', type: 'text', maxLen: 16, maxWidth: 100 },
                    { key: 'description', label: 'Description', type: 'text', maxLen: 28, maxWidth: 160 },
                    { key: 'country_id', label: 'Country', type: 'text', maxLen: 18, maxWidth: 100 },
                    { key: 'city', label: 'City', type: 'text', maxLen: 16, maxWidth: 100 },
                    { key: 'status', label: 'Status', type: 'status', maxWidth: 80 }
                ]
            },
            'currencies': {
                columns: [
                    { key: 'code', label: 'Code', type: 'text', maxLen: 6, maxWidth: 70 },
                    { key: 'name', label: 'Name', type: 'text', maxLen: 20, maxWidth: 140 },
                    { key: 'symbol', label: 'Symbol', type: 'text', maxLen: 8, maxWidth: 80 },
                    { key: 'display_order', label: 'Order', type: 'number', maxLen: 6, maxWidth: 70 },
                    { key: 'status', label: 'Status', type: 'status', maxWidth: 80 }
                ]
            }
        };
        
        return configs[setting] || {
            columns: [
                { key: 'name', label: 'Name', type: 'text', maxLen: 16, maxWidth: 100 },
                { key: 'description', label: 'Description', type: 'text', maxLen: 28, maxWidth: 160 },
                { key: 'country_id', label: 'Country', type: 'text', maxLen: 18, maxWidth: 100 },
                { key: 'city', label: 'City', type: 'text', maxLen: 16, maxWidth: 100 },
                { key: 'status', label: 'Status', type: 'status', maxWidth: 80 }
            ]
        };
    }
    
    // Get form configuration
    getFormConfig(setting) {
        const configs = {
            'office_managers': {
                fields: [
                    { name: 'name', label: 'Name', type: 'text', required: true, placeholder: 'Enter full name' },
                    { name: 'email', label: 'Email', type: 'email', required: true, placeholder: 'Enter email address' },
                    { name: 'phone', label: 'Phone', type: 'tel', placeholder: 'Enter phone number' },
                    { name: 'position', label: 'Position', type: 'text', placeholder: 'Enter position title' },
                    { name: 'country_id', label: 'Country', type: 'select', required: false, relation: { table: 'recruitment_countries', displayField: 'country_name', valueField: 'id' } },
                    { name: 'city', label: 'City', type: 'select', required: false },
                    { name: 'address', label: 'Address', type: 'textarea', placeholder: 'Enter address', fullWidth: true },
                    { name: 'status', label: 'Status', type: 'select', options: [
                        { value: 'active', label: 'Active' },
                        { value: 'inactive', label: 'Inactive' }
                    ] }
                ]
            },
            'visa_types': {
                fields: [
                    { name: 'name', label: 'Visa Type', type: 'select', required: true, placeholder: 'Select visa type', options: [
                        { value: 'Tourist / Visit Visa', label: 'Tourist / Visit Visa' },
                        { value: 'eVisa / ETA (Electronic)', label: 'eVisa / ETA (Electronic)' },
                        { value: 'Visa on Arrival', label: 'Visa on Arrival' },
                        { value: 'Single Entry Visit', label: 'Single Entry Visit' },
                        { value: 'Multiple Entry Visit', label: 'Multiple Entry Visit' },
                        { value: 'Business Visa', label: 'Business Visa' },
                        { value: 'Conference / Exhibition', label: 'Conference / Exhibition' },
                        { value: 'Work Visa / Employment', label: 'Work Visa / Employment' },
                        { value: 'Temporary Work / Seasonal', label: 'Temporary Work / Seasonal' },
                        { value: 'Skilled Worker / Professional', label: 'Skilled Worker / Professional' },
                        { value: 'Intra-Company Transfer', label: 'Intra-Company Transfer' },
                        { value: 'Domestic Worker / Household', label: 'Domestic Worker / Household' },
                        { value: 'Crew / Seafarer / Maritime', label: 'Crew / Seafarer / Maritime' },
                        { value: 'Working Holiday', label: 'Working Holiday' },
                        { value: 'Internship / Training', label: 'Internship / Training' },
                        { value: 'Digital Nomad / Remote Work', label: 'Digital Nomad / Remote Work' },
                        { value: 'Freelance / Self-Employed', label: 'Freelance / Self-Employed' },
                        { value: 'Investor / Entrepreneur', label: 'Investor / Entrepreneur' },
                        { value: 'Golden Visa / Long-Stay Investor', label: 'Golden Visa / Long-Stay Investor' },
                        { value: 'Free Zone / Business Setup', label: 'Free Zone / Business Setup' },
                        { value: 'Student Visa', label: 'Student Visa' },
                        { value: 'Language Course / Study Short-Term', label: 'Language Course / Study Short-Term' },
                        { value: 'Research / Academic', label: 'Research / Academic' },
                        { value: 'Exchange / Au Pair / Cultural', label: 'Exchange / Au Pair / Cultural' },
                        { value: 'Family / Dependent Visa', label: 'Family / Dependent Visa' },
                        { value: 'Spouse / Partner / Marriage', label: 'Spouse / Partner / Marriage' },
                        { value: 'Parent / Super Visa (Family)', label: 'Parent / Super Visa (Family)' },
                        { value: 'Child / Minor Dependent', label: 'Child / Minor Dependent' },
                        { value: 'Residence / Iqama', label: 'Residence / Iqama' },
                        { value: 'Permanent Residence', label: 'Permanent Residence' },
                        { value: 'Transit Visa', label: 'Transit Visa' },
                        { value: 'Airport Transit (Sterile)', label: 'Airport Transit (Sterile)' },
                        { value: 'Medical Treatment', label: 'Medical Treatment' },
                        { value: 'Medical Escort / Companion', label: 'Medical Escort / Companion' },
                        { value: 'Hajj / Umrah', label: 'Hajj / Umrah' },
                        { value: 'Religious / Missionary', label: 'Religious / Missionary' },
                        { value: 'Retirement', label: 'Retirement' },
                        { value: 'Artist / Entertainer / Performer', label: 'Artist / Entertainer / Performer' },
                        { value: 'Athlete / Sports', label: 'Athlete / Sports' },
                        { value: 'Journalist / Media', label: 'Journalist / Media' },
                        { value: 'NGO / Volunteer', label: 'NGO / Volunteer' },
                        { value: 'Diplomatic / Official', label: 'Diplomatic / Official' },
                        { value: 'Courtesy / Official Guest', label: 'Courtesy / Official Guest' },
                        { value: 'UN / International Organization', label: 'UN / International Organization' },
                        { value: 'Humanitarian / Protection', label: 'Humanitarian / Protection' },
                        { value: 'Adoption', label: 'Adoption' },
                        { value: 'Embassy / Consular Staff', label: 'Embassy / Consular Staff' },
                        { value: 'Other', label: 'Other' }
                    ] },
                    { name: 'description', label: 'Role / category detail', type: 'select', required: false, placeholder: 'Depends on visa type' },
                    { name: 'validity_days', label: 'Validity (Days)', type: 'number', required: true, placeholder: 'Enter validity in days' },
                    { name: 'processing_fee', label: 'Processing Fee', type: 'number', step: '0.01', placeholder: 'Enter processing fee' },
                    { name: 'country_id', label: 'Country', type: 'select', required: false },
                    { name: 'city', label: 'City', type: 'select', required: false },
                    { name: 'requirements', label: 'Requirements', type: 'textarea', placeholder: 'Enter extra requirements or notes', fullWidth: true },
                    { name: 'status', label: 'Status', type: 'select', options: [
                        { value: 'active', label: 'Active' },
                        { value: 'inactive', label: 'Inactive' }
                    ] }
                ]
            },
            'recruitment_countries': {
                fields: [
                    { name: 'name', label: 'Country Name', type: 'text', required: true, placeholder: 'Type country name or select from list', countryDropdown: true },
                    { name: 'code', label: 'Country Code', type: 'text', required: true, placeholder: 'Auto-filled when country selected' },
                    { name: 'city', label: 'City', type: 'text', placeholder: 'Type city name or select from list (select country first)' },
                    { name: 'currency', label: 'Currency', type: 'select', placeholder: 'Select currency', currencyDropdown: true },
                    { name: 'flag_emoji', label: 'Flag Emoji', type: 'text', placeholder: 'Auto-filled when country selected' },
                    { name: 'position', label: 'Position', type: 'text', placeholder: 'Enter position (optional)' },
                    { name: 'status', label: 'Status', type: 'select', options: [
                        { value: 'active', label: 'Active' },
                        { value: 'inactive', label: 'Inactive' }
                    ] }
                ]
            },
            'job_categories': {
                fields: [
                    { name: 'name', label: 'Category Name', type: 'text', required: true, placeholder: 'Enter category name' },
                    { name: 'description', label: 'Description', type: 'textarea', placeholder: 'Enter description', fullWidth: true },
                    { name: 'min_salary', label: 'Min Salary', type: 'number', step: '0.01', placeholder: 'Enter minimum salary' },
                    { name: 'max_salary', label: 'Max Salary', type: 'number', step: '0.01', placeholder: 'Enter maximum salary' },
                    { name: 'country_id', label: 'Country', type: 'select', required: false, relation: { table: 'recruitment_countries', displayField: 'country_name', valueField: 'id' } },
                    { name: 'city', label: 'City', type: 'select', required: false },
                    { name: 'position', label: 'Position', type: 'text', placeholder: 'Enter position (optional)' },
                    { name: 'status', label: 'Status', type: 'select', options: [
                        { value: 'active', label: 'Active' },
                        { value: 'inactive', label: 'Inactive' }
                    ] }
                ]
            },
            'age_specifications': {
                fields: [
                    { name: 'name', label: 'Age Range', type: 'text', required: true, placeholder: 'Enter age range' },
                    { name: 'description', label: 'Description', type: 'textarea', placeholder: 'Enter description', fullWidth: true },
                    { name: 'country_id', label: 'Country', type: 'select', required: false, relation: { table: 'recruitment_countries', displayField: 'country_name', valueField: 'id' } },
                    { name: 'city', label: 'City', type: 'select', required: false },
                    { name: 'position', label: 'Position', type: 'text', placeholder: 'Enter position (optional)' },
                    { name: 'status', label: 'Status', type: 'select', options: [
                        { value: 'active', label: 'Active' },
                        { value: 'inactive', label: 'Inactive' }
                    ] }
                ]
            },
            'status_specifications': {
                fields: [
                    { name: 'name', label: 'Status Name', type: 'text', required: true, placeholder: 'Enter status name' },
                    { name: 'description', label: 'Description', type: 'textarea', placeholder: 'Enter description', fullWidth: true },
                    { name: 'country_id', label: 'Country', type: 'select', required: false, relation: { table: 'recruitment_countries', displayField: 'country_name', valueField: 'id' } },
                    { name: 'city', label: 'City', type: 'select', required: false },
                    { name: 'position', label: 'Position', type: 'text', placeholder: 'Enter position (optional)' },
                    { name: 'status', label: 'Status', type: 'select', options: [
                        { value: 'active', label: 'Active' },
                        { value: 'inactive', label: 'Inactive' }
                    ] }
                ]
            },
            'arrival_agencies': {
                fields: [
                    { name: 'name', label: 'Agency Name', type: 'text', required: true, placeholder: 'Enter agency name' },
                    { name: 'description', label: 'Description', type: 'textarea', placeholder: 'Enter description', fullWidth: true },
                    { name: 'country_id', label: 'Country', type: 'select', required: false, relation: { table: 'recruitment_countries', displayField: 'country_name', valueField: 'id' } },
                    { name: 'city', label: 'City', type: 'select', required: false },
                    { name: 'position', label: 'Position', type: 'text', placeholder: 'Enter position (optional)' },
                    { name: 'status', label: 'Status', type: 'select', options: [
                        { value: 'active', label: 'Active' },
                        { value: 'inactive', label: 'Inactive' }
                    ] }
                ]
            },
            'arrival_stations': {
                fields: [
                    { name: 'name', label: 'Station Name', type: 'text', required: true, placeholder: 'Enter station name' },
                    { name: 'description', label: 'Description', type: 'textarea', placeholder: 'Enter description', fullWidth: true },
                    { name: 'country_id', label: 'Country', type: 'select', required: false, relation: { table: 'recruitment_countries', displayField: 'country_name', valueField: 'id' } },
                    { name: 'city', label: 'City', type: 'select', required: false },
                    { name: 'position', label: 'Position', type: 'text', placeholder: 'Enter position (optional)' },
                    { name: 'status', label: 'Status', type: 'select', options: [
                        { value: 'active', label: 'Active' },
                        { value: 'inactive', label: 'Inactive' }
                    ] }
                ]
            },
            'worker_statuses': {
                fields: [
                    { name: 'name', label: 'Status Name', type: 'text', required: true, placeholder: 'Enter status name' },
                    { name: 'description', label: 'Description', type: 'textarea', placeholder: 'Enter description', fullWidth: true },
                    { name: 'country_id', label: 'Country', type: 'select', required: false, relation: { table: 'recruitment_countries', displayField: 'country_name', valueField: 'id' } },
                    { name: 'city', label: 'City', type: 'select', required: false },
                    { name: 'position', label: 'Position', type: 'text', placeholder: 'Enter position (optional)' },
                    { name: 'status', label: 'Status', type: 'select', options: [
                        { value: 'active', label: 'Active' },
                        { value: 'inactive', label: 'Inactive' }
                    ] }
                ]
            },
            'system_config': {
                fields: [
                    { name: 'config_key', label: 'Config Key', type: 'text', required: true, placeholder: 'Enter config key (e.g., company_office_name)' },
                    { name: 'config_value', label: 'Config Value', type: 'text', required: true, placeholder: 'Enter config value', fullWidth: true },
                    { name: 'name', label: 'Display Name', type: 'text', placeholder: 'Enter display name (optional)' },
                    { name: 'description', label: 'Description', type: 'textarea', placeholder: 'Enter description', fullWidth: true },
                    { name: 'status', label: 'Status', type: 'select', options: [
                        { value: 'active', label: 'Active' },
                        { value: 'inactive', label: 'Inactive' }
                    ] }
                ],
                companyInfoFields: [
                    { name: 'company_office_name', label: 'Company Name (English)', type: 'text', required: true, placeholder: 'Enter company name', section: 'basic' },
                    { name: 'company_email', label: 'Email', type: 'email', required: true, placeholder: 'Enter email address', section: 'basic' },
                    { name: 'company_region', label: 'Region (English)', type: 'text', placeholder: 'Enter region', section: 'address' },
                    { name: 'company_city', label: 'City (English)', type: 'text', placeholder: 'Enter city', section: 'address' },
                    { name: 'company_address', label: 'Address (English)', type: 'text', placeholder: 'Enter address', section: 'address' },
                    { name: 'company_city_subdivision', label: 'City Subdivision (English)', type: 'text', placeholder: 'Enter city subdivision', section: 'address' },
                    { name: 'company_building_number', label: 'Building Number (English)', type: 'text', placeholder: 'Enter building number', section: 'address' },
                    { name: 'company_telephone', label: 'Telephone Number', type: 'tel', placeholder: 'Enter telephone number', section: 'contact' },
                    { name: 'company_telephone_country_code', label: 'Telephone Country Code', type: 'text', placeholder: 'e.g., 966+', value: '966+', section: 'contact' },
                    { name: 'company_mobile', label: 'Mobile Number', type: 'tel', placeholder: 'Enter mobile number', section: 'contact' },
                    { name: 'company_mobile_country_code', label: 'Mobile Country Code', type: 'text', placeholder: 'e.g., 966+', value: '966+', section: 'contact' },
                    { name: 'company_fax', label: 'Fax', type: 'tel', placeholder: 'Enter fax number', section: 'contact' },
                    { name: 'company_fax_country_code', label: 'Fax Country Code', type: 'text', placeholder: 'e.g., 966+', value: '966+', section: 'contact' },
                    { name: 'company_license_number', label: 'License Number', type: 'text', placeholder: 'Enter license number', section: 'legal' },
                    { name: 'company_commercial_register', label: 'Commercial Register', type: 'text', placeholder: 'Enter commercial register number', section: 'legal' },
                    { name: 'company_postal_box', label: 'Postal Box', type: 'text', placeholder: 'Enter postal box number', section: 'address' },
                    { name: 'company_postal_code', label: 'Postal Code', type: 'text', placeholder: 'Enter postal code', section: 'address' },
                    { name: 'company_sender_name', label: 'Sender Name', type: 'text', placeholder: 'Enter sender name', section: 'basic' },
                    { name: 'company_logo', label: 'Upload Logo', type: 'file', accept: 'image/*', placeholder: 'Select logo file', section: 'basic' },
                    { name: 'company_vat_number', label: 'VAT Number', type: 'text', placeholder: 'Enter VAT number', section: 'legal' },
                    { name: 'company_vat_percentage', label: 'VAT Percentage', type: 'number', step: '0.01', placeholder: 'Enter VAT percentage', section: 'legal' },
                    { name: 'company_invoice_temp_code', label: 'Temporary Code for Electronic Invoice System', type: 'text', placeholder: 'Enter temporary code', section: 'legal' }
                ]
            },
            'users': {
                fields: (() => {
                    const isControl = (typeof isControlPanelContext === 'function' && isControlPanelContext()) || (window.location && window.location.search && window.location.search.includes('control=1'));
                    if (isControl) {
                        return [
                            { name: 'name', label: 'Username', type: 'text', required: true, placeholder: 'Enter username' },
                            { name: 'password', label: 'Password', type: 'password', required: true, placeholder: 'Enter password (required for new user)' },
                            { name: 'status', label: 'Status', type: 'select', options: [
                                { value: 'active', label: 'Active' },
                                { value: 'inactive', label: 'Inactive' }
                            ] }
                        ];
                    }
                    return [
                        { name: 'name', label: 'Username', type: 'text', required: true, placeholder: 'Enter username' },
                        { name: 'email', label: 'Email', type: 'email', required: true, placeholder: 'Enter email address' },
                        { name: 'password', label: 'Password', type: 'password', required: false, placeholder: 'Enter password (leave blank to keep current)' },
                        { name: 'phone', label: 'Phone', type: 'tel', placeholder: 'Enter phone number' },
                        { name: 'position', label: 'Position', type: 'text', placeholder: 'Enter position (optional)' },
                        { name: 'status', label: 'Status', type: 'select', options: [
                            { value: 'active', label: 'Active' },
                            { value: 'inactive', label: 'Inactive' }
                        ] }
                    ];
                })()
            },
            'appearance_specifications': {
                fields: [
                    { name: 'name', label: 'Specification Name', type: 'text', required: true, placeholder: 'Enter specification name' },
                    { name: 'description', label: 'Description', type: 'textarea', placeholder: 'Enter description', fullWidth: true },
                    { name: 'country_id', label: 'Country', type: 'select', required: false, relation: { table: 'recruitment_countries', displayField: 'country_name', valueField: 'id' } },
                    { name: 'city', label: 'City', type: 'select', required: false },
                    { name: 'position', label: 'Position', type: 'text', placeholder: 'Enter position (optional)' },
                    { name: 'status', label: 'Status', type: 'select', options: [
                        { value: 'active', label: 'Active' },
                        { value: 'inactive', label: 'Inactive' }
                    ] }
                ]
            },
            'request_statuses': {
                fields: [
                    { name: 'name', label: 'Status Name', type: 'text', required: true, placeholder: 'Enter status name' },
                    { name: 'description', label: 'Description', type: 'textarea', placeholder: 'Enter description', fullWidth: true },
                    { name: 'country_id', label: 'Country', type: 'select', required: false, relation: { table: 'recruitment_countries', displayField: 'country_name', valueField: 'id' } },
                    { name: 'city', label: 'City', type: 'select', required: false },
                    { name: 'position', label: 'Position', type: 'text', placeholder: 'Enter position (optional)' },
                    { name: 'status', label: 'Status', type: 'select', options: [
                        { value: 'active', label: 'Active' },
                        { value: 'inactive', label: 'Inactive' }
                    ] }
                ]
            },
            'currencies': {
                fields: [
                    { name: 'copy_from_currency', label: 'Copy From Currency (Optional)', type: 'select', required: false, placeholder: 'Select currency to copy from', currencyDropdown: true, copySource: true },
                    { name: 'country_id', label: 'Country (Optional)', type: 'select', required: false, relation: { table: 'recruitment_countries', displayField: 'country_name', valueField: 'id' } },
                    { name: 'code', label: 'Currency Code', type: 'text', required: true, placeholder: 'Enter 3-letter code (e.g., USD)' },
                    { name: 'name', label: 'Currency Name', type: 'text', required: true, placeholder: 'Enter currency name (e.g., US Dollar)' },
                    { name: 'symbol', label: 'Symbol', type: 'text', required: false, placeholder: 'Enter currency symbol (e.g., $)' },
                    { name: 'display_order', label: 'Display Order', type: 'number', required: false, placeholder: 'Enter display order (lower numbers first)' },
                    { name: 'status', label: 'Status', type: 'select', options: [
                        { value: 'active', label: 'Active' },
                        { value: 'inactive', label: 'Inactive' }
                    ] }
                ]
            }
        };
        
        return configs[setting] || {
            fields: [
                { name: 'name', label: 'Name', type: 'text', required: true, placeholder: 'Enter name' },
                { name: 'description', label: 'Description', type: 'textarea', placeholder: 'Enter description', fullWidth: true },
                { name: 'country_id', label: 'Country', type: 'select', required: false, relation: { table: 'recruitment_countries', displayField: 'country_name', valueField: 'id' } },
                { name: 'city', label: 'City', type: 'select', required: false },
                { name: 'position', label: 'Position', type: 'text', placeholder: 'Enter position (optional)' },
                { name: 'status', label: 'Status', type: 'select', options: [
                    { value: 'active', label: 'Active' },
                    { value: 'inactive', label: 'Inactive' }
                ] }
            ]
        };
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.modernForms = new ModernForms();
});
