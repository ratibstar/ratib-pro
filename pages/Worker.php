<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/Worker.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/Worker.php`.
 */
// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once '../includes/config.php';
require_once '../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

// Check if user has permission to view workers
if (!hasPermission('view_workers')) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

// Check if the request method is POST to verify form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Verify the CSRF token to prevent cross-site request forgery
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}

// Set security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$programCountryText = strtolower(implode(' ', [
    (string) ($_SESSION['country_name'] ?? ''),
    (string) ($_SESSION['country_code'] ?? ''),
    defined('COUNTRY_NAME') ? (string) COUNTRY_NAME : '',
    defined('COUNTRY_CODE') ? (string) COUNTRY_CODE : '',
    defined('SITE_URL') ? (string) SITE_URL : '',
]));
$isIndonesiaProgram = strpos($programCountryText, 'indonesia') !== false
    || preg_match('/\bidn?\b/', $programCountryText) === 1;

$countryNameLower = strtolower((string) ($_SESSION['country_name'] ?? (defined('COUNTRY_NAME') ? COUNTRY_NAME : '')));
$countryCodeLower = strtolower((string) ($_SESSION['country_code'] ?? (defined('COUNTRY_CODE') ? COUNTRY_CODE : '')));
$countrySlug = 'default';
if (strpos($countryNameLower, 'indonesia') !== false || preg_match('/\bidn?\b/', $countryCodeLower) === 1) {
    $countrySlug = 'indonesia';
} elseif (strpos($countryNameLower, 'bangladesh') !== false || preg_match('/\bbd\b/', $countryCodeLower) === 1) {
    $countrySlug = 'bangladesh';
} elseif (strpos($countryNameLower, 'sri lanka') !== false || strpos($countryNameLower, 'srilanka') !== false || preg_match('/\blk\b/', $countryCodeLower) === 1) {
    $countrySlug = 'sri_lanka';
} elseif (strpos($countryNameLower, 'kenya') !== false || preg_match('/\bke\b/', $countryCodeLower) === 1) {
    $countrySlug = 'kenya';
}
$countryProfileConfig = null;
$ctrlConn = $GLOBALS['control_conn'] ?? null;
if ($ctrlConn instanceof mysqli) {
    $tblCheck = $ctrlConn->query("SHOW TABLES LIKE 'control_country_profiles'");
    if ($tblCheck && $tblCheck->num_rows > 0) {
        $stProfile = $ctrlConn->prepare("SELECT labels_json, requirements_json FROM control_country_profiles WHERE country_slug = ? LIMIT 1");
        if ($stProfile) {
            $stProfile->bind_param('s', $countrySlug);
            $stProfile->execute();
            $rowProfile = $stProfile->get_result()->fetch_assoc();
            $stProfile->close();
            if (is_array($rowProfile)) {
                $countryProfileConfig = [
                    'labels' => json_decode((string) ($rowProfile['labels_json'] ?? '{}'), true) ?: new stdClass(),
                    'requirements' => json_decode((string) ($rowProfile['requirements_json'] ?? '[]'), true) ?: [],
                ];
            }
        }
    }
}

// CSS files configuration - Only the main CSS file with aggressive cache-busting
$cacheBuster = time();
$forceCache = rand(1000, 9999);
$cssFiles = [
    'worker-table-styles' => asset('css/worker/worker-table-styles.css') . "?v=$cacheBuster&force=$forceCache",
    'musaned' => asset('css/worker/musaned.css') . "?v=$cacheBuster&force=$forceCache",
    'documents' => asset('css/worker/documents.css') . "?v=$cacheBuster&force=$forceCache",
    'notifications' => asset('css/worker/notifications.css') . "?v=$cacheBuster",
    'deployment-modal' => asset('css/worker/deployment-modal.css') . "?v=$cacheBuster"
];

$pageCss = $cssFiles;
$pageCss[] = "https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css";
$pageCss[] = "https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css";
$pageTitle = "Worker Management";

include '../includes/header.php';
?>

<!-- Force no caching -->
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">

<!-- Force cache clear on page load -->
<script src="<?php echo asset('js/utils/cache-clear.js'); ?>?v=<?php echo $cacheBuster; ?>"></script>
<script>window.RATIB_IS_INDONESIA_PROGRAM = <?php echo $isIndonesiaProgram ? 'true' : 'false'; ?>;</script>
<script>window.RATIB_COUNTRY_PROFILE = <?php echo json_encode($countrySlug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;</script>
<script>window.RATIB_COUNTRY_PROFILE_CONFIG = <?php echo json_encode($countryProfileConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;</script>

<!-- Main container for the content of the page -->
<div class="main-container worker-management-container">
    <!-- Container for the table displaying workers -->
    <div class="table-container">
        <div class="table-overlay">
            <div class="loading-spinner"></div>
        </div>



        <!-- Wrapper for statistics cards -->
        <div class="stats-wrapper">
            <div class="stat-card total glow"> <!-- Card for total workers -->
                <i class="fas fa-users"></i> <!-- Icon for total workers -->
                <div class="stat-info">
                    <div class="stat-value">
                        <span class="counter" id="totalWorkers">0</span> <!-- Counter for total workers -->
                        <span class="stat-label">Total Workers</span> <!-- Label for total workers -->
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up trend-icon trend-up"></i> <!-- Trend icon -->
                        <span class="trend-value">0%</span> <!-- Trend value -->
                        <span class="trend-period">vs last month</span> <!-- Comparison period -->
                    </div>
                </div>
            </div>

            <!-- Combined Active + Inactive Workers Card -->
            <div class="stat-card combined-status active-inactive">
                <div class="combined-status-content">
                    <div class="status-section active-section">
                        <i class="fas fa-user-check"></i>
                        <div class="stat-info">
                            <div class="stat-value">
                                <span class="counter" id="activeWorkers">0</span>
                                <span class="stat-label">Active Workers</span>
                            </div>
                            <div class="stat-trend">
                                <i class="fas fa-arrow-up trend-icon trend-up"></i>
                                <span class="trend-value">0%</span>
                                <span class="trend-period">vs last month</span>
                            </div>
                        </div>
                    </div>
                    <div class="status-divider"></div>
                    <div class="status-section inactive-section">
                        <i class="fas fa-user-times"></i>
                        <div class="stat-info">
                            <div class="stat-value">
                                <span class="counter" id="inactiveWorkers">0</span>
                                <span class="stat-label">Inactive Workers</span>
                            </div>
                            <div class="stat-trend">
                                <i class="fas fa-arrow-down trend-icon trend-down"></i>
                                <span class="trend-value">0%</span>
                                <span class="trend-period">vs last month</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Combined Pending + Suspended Workers Card -->
            <div class="stat-card combined-status pending-suspended">
                <div class="combined-status-content">
                    <div class="status-section pending-section">
                        <i class="fas fa-clock"></i>
                        <div class="stat-info">
                            <div class="stat-value">
                                <span class="counter" id="pendingWorkers">0</span>
                                <span class="stat-label">Pending Workers</span>
                            </div>
                            <div class="stat-trend">
                                <i class="fas fa-arrow-up trend-icon trend-up"></i>
                                <span class="trend-value">0%</span>
                                <span class="trend-period">vs last month</span>
                            </div>
                        </div>
                    </div>
                    <div class="status-divider"></div>
                    <div class="status-section suspended-section">
                        <i class="fas fa-pause-circle"></i>
                        <div class="stat-info">
                            <div class="stat-value">
                                <span class="counter" id="suspendedWorkers">0</span>
                                <span class="stat-label">Suspended Workers</span>
                            </div>
                            <div class="stat-trend">
                                <i class="fas fa-arrow-down trend-icon trend-down"></i>
                                <span class="trend-value">0%</span>
                                <span class="trend-period">vs last month</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Combined POLICE + MEDICAL Card -->
            <div class="stat-card combined-status documents-police-medical">
                <div class="combined-status-content">
                    <div class="status-section police-section">
                        <i class="fas fa-shield-alt"></i>
                        <div class="stat-info">
                            <div class="stat-value">
                                <span class="counter" id="policeCount">0</span>
                                <span class="stat-label">POLICE</span>
                            </div>
                            <div class="stat-trend">
                                <span class="trend-period"></span>
                            </div>
                        </div>
                    </div>
                    <div class="status-divider"></div>
                    <div class="status-section medical-section">
                        <i class="fas fa-heartbeat"></i>
                        <div class="stat-info">
                            <div class="stat-value">
                                <span class="counter" id="medicalCount">0</span>
                                <span class="stat-label">MEDICAL</span>
                            </div>
                            <div class="stat-trend">
                                <span class="trend-period"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Combined VISA + TICKET Card -->
            <div class="stat-card combined-status documents-visa-ticket">
                <div class="combined-status-content">
                    <div class="status-section visa-section">
                        <i class="fas fa-passport"></i>
                        <div class="stat-info">
                            <div class="stat-value">
                                <span class="counter" id="visaCount">0</span>
                                <span class="stat-label">VISA</span>
                            </div>
                            <div class="stat-trend">
                                <span class="trend-period"></span>
                            </div>
                        </div>
                    </div>
                    <div class="status-divider"></div>
                    <div class="status-section ticket-section">
                        <i class="fas fa-plane-departure"></i>
                        <div class="stat-info">
                            <div class="stat-value">
                                <span class="counter" id="ticketCount">0</span>
                                <span class="stat-label">TICKET</span>
                            </div>
                            <div class="stat-trend">
                                <span class="trend-period"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($isIndonesiaProgram): ?>
            <div class="stat-card combined-status indonesia-compliance-card indonesia-compliance-field">
                <div class="combined-status-content">
                    <div class="status-section">
                        <i class="fas fa-plane-circle-check"></i>
                        <div class="stat-info">
                            <div class="stat-value">
                                <span class="counter" id="indonesiaReadyToDeploy">0</span>
                                <span class="stat-label">Ready to Deploy</span>
                            </div>
                        </div>
                    </div>
                    <div class="status-divider"></div>
                    <div class="status-section">
                        <i class="fas fa-notes-medical"></i>
                        <div class="stat-info">
                            <div class="stat-value">
                                <span class="counter" id="indonesiaWaitingMedical">0</span>
                                <span class="stat-label">Waiting Medical</span>
                            </div>
                        </div>
                    </div>
                    <div class="status-divider"></div>
                    <div class="status-section">
                        <i class="fas fa-landmark"></i>
                        <div class="stat-info">
                            <div class="stat-value">
                                <span class="counter" id="indonesiaWaitingApproval">0</span>
                                <span class="stat-label">Waiting Approval</span>
                            </div>
                        </div>
                    </div>
                    <div class="status-divider"></div>
                    <div class="status-section">
                        <i class="fas fa-ban"></i>
                        <div class="stat-info">
                            <div class="stat-value">
                                <span class="counter" id="indonesiaBlockedWorkers">0</span>
                                <span class="stat-label">Blocked Workers</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Group for control elements like search and filters -->
        <div class="controls-wrapper">
            <div class="controls-left">
                <!-- Button to add a new worker -->
                <button id="addNewBtn" class="add-new-btn add-worker" type="button" data-permission="add_worker">
                    <i class="fas fa-plus"></i>
                    Add New Worker
                </button>
                <button id="aiWorkflowBtn" class="add-new-btn ai-workflow-btn" type="button" title="Run AI onboarding workflow">
                    <i class="fas fa-robot"></i>
                    AI Workflow
                </button>

                <!-- Selected count display -->
                <div class="selected-count-display">
                    <span id="selectedCount">0</span> workers selected
                </div>

                <!-- Group for bulk action buttons -->
                <div class="bulk-actions">
                    <button id="bulkActivateBtn" class="bulk-btn bulk-activate" disabled data-permission="bulk_edit_workers">
                        <i class="fas fa-check-circle"></i> <!-- Icon for activate action -->
                        <span>Activate</span> <!-- Button label -->
                    </button>
                    <button id="bulkDeactivateBtn" class="bulk-btn bulk-deactivate" disabled data-permission="bulk_edit_workers">
                        <i class="fas fa-times-circle"></i> <!-- Icon for deactivate action -->
                        <span>Deactivate</span> <!-- Button label -->
                    </button>
                    <button id="bulkPendingBtn" class="bulk-btn bulk-pending" disabled data-permission="bulk_edit_workers">
                        <i class="fas fa-clock"></i> <!-- Icon for pending action -->
                        <span>Pending</span> <!-- Button label -->
                    </button>
                    <button id="bulkSuspendedBtn" class="bulk-btn bulk-suspended" disabled data-permission="bulk_edit_workers">
                        <i class="fas fa-pause-circle"></i> <!-- Icon for suspended action -->
                        <span>Suspended</span> <!-- Button label -->
                    </button>
                    <button id="bulkDeleteBtn" class="bulk-btn bulk-delete" disabled data-permission="delete_worker">
                        <i class="fas fa-trash"></i> <!-- Icon for delete action -->
                        <span>Delete</span> <!-- Button label -->
                    </button>
                </div>
            </div>

            <div class="controls-right">
                <!-- Search input for filtering workers -->
                <div class="search-wrapper">
                    <input type="text"
                        id="searchInput"
                        class="search-input"
                        placeholder="Search by ID, name, email..."
                        aria-label="Search workers"> <!-- Accessible label for search input -->
                    <i class="fas fa-search"></i> <!-- Search icon -->
                </div>

                <!-- Dropdown for filtering by worker status -->
                <select id="statusFilter" class="status-select" aria-label="Filter by status">
                    <option value="">All Status</option> <!-- Option for all statuses -->
                    <option value="active">Active</option> <!-- Option for active status -->
                    <option value="inactive">Inactive</option> <!-- Option for inactive status -->
                </select>
            </div>
        </div>

        <!-- Pagination controls for navigating through pages of workers -->
        <div class="pagination-wrapper">
            <div class="page-size">
                Show
                <select id="topPageSize" class="page-size-select">
                    <option value="5">5</option> <!-- Option for showing 5 entries -->
                    <option value="10" selected>10</option> <!-- Option for showing 10 entries -->
                    <option value="25">25</option> <!-- Option for showing 25 entries -->
                    <option value="50">50</option> <!-- Option for showing 50 entries -->
                    <option value="100">100</option> <!-- Option for showing 100 entries -->
                </select>
                entries
            </div>
            <div class="pagination-controls">
                <div class="page-numbers" id="topPageNumbers">
                    <!-- Page numbers will be inserted here by JavaScript -->
                </div>
            </div>
            <div id="topPaginationInfo" class="pagination-info">
                Showing 0 to 0 of 0 entries <!-- Information about the current pagination state -->
            </div>
        </div>

        <!-- Table with fixed headers and conditional scrolling -->
        <div class="table-wrapper">
            <table class="worker-table neon-border" role="grid" aria-label="Workers List">
                <thead class="table-header">
                    <tr>
                        <th>ID</th>
                        <th>NAME</th>
                        <th>IDENTITY</th>
                        <th>PASSPORT</th>
                        <?php if ($isIndonesiaProgram): ?><th class="indonesia-only-column">TRAINING</th><?php endif; ?>
                        <th>POLICE</th>
                        <th>MEDICAL</th>
                        <th>VISA</th>
                        <th>TICKET</th>
                        <th>AGENT</th>
                        <th>SUBAGENT</th>
                        <th>STATUS</th>
                        <th>
                            <input type="checkbox" id="selectAll" class="form-checkbox" title="Select All" aria-label="Select all workers">
                        </th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody id="workerTableBody" class="table-body">
                    <!-- Table content will be dynamically populated -->
                </tbody>
            </table>
        </div>

        <!-- Mobile Worker Cards Container -->
        <div class="mobile-worker-cards"></div>

        <!-- Bottom Pagination Controls -->
        <div class="pagination-wrapper bottom">
            <div class="page-size">
                Show
                <select id="bottomPageSize" class="page-size-select">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                entries
            </div>
            <div class="pagination-controls">
                <div class="page-numbers" id="bottomPageNumbers">
                    <!-- Page numbers will be inserted here by JavaScript -->
                </div>
            </div>
            <div id="bottomPaginationInfo" class="pagination-info">
                Showing 0 to 0 of 0 entries
            </div>
        </div>

    </div>
</div>

<!-- Form container for worker operations (dir=ltr, lang=en for Western numerals) -->
<div id="workerFormContainer" dir="ltr" lang="en">
    <div class="form-overlay"></div>
    <div class="form-wrapper">
        <h2 id="formTitle">Add New Worker</h2>  <!-- Dynamic title for Add/Edit -->
        <button class="close-btn">×</button>
        
        <!-- Sidebar Navigation -->
        <div class="form-sidebar">
            <div class="sidebar-nav-item active" data-section="basic-info">
                <i class="fas fa-user"></i>
                <span>Basic Info</span>
            </div>
            <div class="sidebar-nav-item" data-section="professional-details">
                <i class="fas fa-briefcase"></i>
                <span>Professional</span>
            </div>
            <div class="sidebar-nav-item" data-section="contact-info">
                <i class="fas fa-address-card"></i>
                <span>Contact</span>
            </div>
            <div class="sidebar-nav-item" data-section="documents">
                <i class="fas fa-file-alt"></i>
                <span>Documents</span>
            </div>
            <?php if (!$isIndonesiaProgram): ?>
            <div class="sidebar-nav-item" data-section="lifecycle-personal-info">
                <i class="fas fa-user-circle"></i>
                <span>Personal Info</span>
            </div>
            <div class="sidebar-nav-item" data-section="lifecycle-passport-identity">
                <i class="fas fa-id-card"></i>
                <span>Passport & Identity</span>
            </div>
            <div class="sidebar-nav-item" data-section="lifecycle-job-contract">
                <i class="fas fa-file-signature"></i>
                <span>Job & Contract</span>
            </div>
            <div class="sidebar-nav-item" data-section="lifecycle-medical">
                <i class="fas fa-notes-medical"></i>
                <span>Medical</span>
            </div>
            <div class="sidebar-nav-item" data-section="lifecycle-training">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Training</span>
            </div>
            <div class="sidebar-nav-item" data-section="lifecycle-government-registration">
                <i class="fas fa-landmark"></i>
                <span>Gov Registration</span>
            </div>
            <div class="sidebar-nav-item" data-section="lifecycle-visa-travel">
                <i class="fas fa-plane-departure"></i>
                <span>Visa & Travel</span>
            </div>
            <?php endif; ?>
        </div>
        
        <form id="workerForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="id" value="">
            <input type="hidden" name="workflow_id" value="">
            <input type="hidden" name="current_stage" value="identity">
            <input type="hidden" name="stage_completed" value="{}">
            <div class="workflow-progress-shell">
                <div class="workflow-progress-head">
                    <span>Workflow Progress</span>
                    <span id="workflowProgressText">0%</span>
                </div>
                <div class="workflow-progress-track">
                    <div id="workflowProgressFill" class="workflow-progress-fill"></div>
                </div>
            </div>
            <div class="form-content">
                <!-- Basic Info Section -->
                <div class="section basic-info">
                    <h3>Basic Information</h3>
                    <div class="form-group">
                        <label for="full_name" class="form-label">Full Name *</label>
                        <input type="text" 
                               name="full_name" 
                               id="full_name" 
                               class="form-control" 
                               placeholder="Enter Full Name" 
                               required>
                    </div>
                    <div class="form-group">
                        <label for="nationality" class="form-label">Nationality</label>
                        <select name="nationality" id="nationality" class="form-select">
                            <option value="">Select nationality</option>
                            <option value="Not specified">Not specified</option>
                            <option value="Indonesia">Indonesia</option>
                            <option value="Philippines">Philippines</option>
                            <option value="India">India</option>
                            <option value="Bangladesh">Bangladesh</option>
                            <option value="Sri Lanka">Sri Lanka</option>
                            <option value="Nepal">Nepal</option>
                            <option value="Pakistan">Pakistan</option>
                            <option value="Egypt">Egypt</option>
                            <option value="Sudan">Sudan</option>
                            <option value="Kenya">Kenya</option>
                            <option value="Uganda">Uganda</option>
                            <option value="Ethiopia">Ethiopia</option>
                            <option value="Ghana">Ghana</option>
                            <option value="Nigeria">Nigeria</option>
                            <option value="Vietnam">Vietnam</option>
                            <option value="Myanmar">Myanmar</option>
                            <option value="Thailand">Thailand</option>
                            <option value="Malaysia">Malaysia</option>
                            <option value="China">China</option>
                            <option value="Jordan">Jordan</option>
                            <option value="Lebanon">Lebanon</option>
                            <option value="Syria">Syria</option>
                            <option value="Yemen">Yemen</option>
                            <option value="Saudi Arabia">Saudi Arabia</option>
                            <option value="United Arab Emirates">United Arab Emirates</option>
                            <option value="Kuwait">Kuwait</option>
                            <option value="Qatar">Qatar</option>
                            <option value="Oman">Oman</option>
                            <option value="Bahrain">Bahrain</option>
                            <option value="Iraq">Iraq</option>
                            <option value="Morocco">Morocco</option>
                            <option value="Tunisia">Tunisia</option>
                            <option value="Algeria">Algeria</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="gender" class="form-label">Gender *</label>
                        <select name="gender" id="gender" class="form-select" required>
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="age" class="form-label">Age</label>
                        <input type="text" name="age" id="age" class="form-control" 
                               placeholder="Enter age" min="18" max="65" dir="ltr" inputmode="numeric" pattern="[0-9]*" maxlength="2" lang="en" data-age-input>
                    </div>
                    <div class="form-group">
                        <label for="marital_status" class="form-label">Marital Status</label>
                        <select name="marital_status" id="marital_status" class="form-select">
                            <option value="">Select Status</option>
                            <option value="single">Single</option>
                            <option value="married">Married</option>
                            <option value="divorced">Divorced</option>
                            <option value="widowed">Widowed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="language" class="form-label">Language</label>
                        <select name="language" id="language" class="form-select">
                            <option value="">Select Language</option>
                            <option value="arabic">Arabic</option>
                            <option value="english">English</option>
                            <option value="hindi">Hindi</option>
                            <option value="urdu">Urdu</option>
                            <option value="bengali">Bengali</option>
                            <option value="tagalog">Tagalog</option>
                            <option value="indonesian">Indonesian</option>
                            <option value="amharic">Amharic</option>
                            <option value="swahili">Swahili</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="birth_date" class="form-label">Date of birth</label>
                            <div class="date-input-wrapper">
                                <input type="text" name="birth_date" id="birth_date" class="form-control date-input" placeholder="YYYY-MM-DD" autocomplete="off">
                                <i class="fas fa-calendar-alt date-icon"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="place_of_birth" class="form-label">Place of birth</label>
                            <input type="text" name="place_of_birth" id="place_of_birth" class="form-control" 
                                   placeholder="Enter place of birth">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="status" class="form-label">Worker Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="pending">Pending</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <?php if ($isIndonesiaProgram): ?>
                        <div class="form-group indonesia-compliance-field">
                            <label for="status_stage" class="form-label">Indonesia Stage</label>
                            <select name="status_stage" id="status_stage" class="form-select">
                                <option value="registered">Registered</option>
                                <option value="document_ready">Document Ready</option>
                                <option value="medical_passed">Medical Passed</option>
                                <option value="training_completed">Training Completed</option>
                                <option value="contract_signed">Contract Signed</option>
                                <option value="govt_approved">Govt Approved</option>
                                <option value="visa_issued">Visa Issued</option>
                                <option value="ready_to_depart">Ready to Depart</option>
                                <option value="deployed">Deployed</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Professional Details Section -->
                <div class="section professional-details">
                    <h3>Professional Details</h3>
                    <div class="form-group">
                        <label for="job_title" class="form-label">Job Title & Skills</label>
                        <!-- Hidden select for form submission -->
                        <select name="job_title[]" id="job_title" class="form-select hidden-select" multiple>
                            <option value="domestic_worker">Domestic Worker</option>
                            <option value="driver">Driver</option>
                            <option value="cook">Cook</option>
                            <option value="gardener">Gardener</option>
                            <option value="security_guard">Security Guard</option>
                            <option value="cleaner">Cleaner</option>
                            <option value="babysitter">Babysitter</option>
                            <option value="elderly_care">Elderly Care</option>
                            <option value="construction_worker">Construction Worker</option>
                            <option value="electrician">Electrician</option>
                            <option value="plumber">Plumber</option>
                            <option value="mechanic">Mechanic</option>
                            <option value="nurse">Nurse</option>
                            <option value="teacher">Teacher</option>
                            <option value="office_worker">Office Worker</option>
                            <option value="salesperson">Salesperson</option>
                            <option value="other">Other</option>
                        </select>
                        <!-- Custom dropdown -->
                        <div class="custom-multiselect-dropdown" id="job_title_dropdown">
                            <div class="custom-dropdown-trigger">
                                <span class="dropdown-placeholder">Select job titles and skills...</span>
                                <i class="fas fa-chevron-down dropdown-arrow"></i>
                            </div>
                            <div class="custom-dropdown-menu">
                                <div class="custom-dropdown-options">
                                    <label class="custom-option"><input type="checkbox" value="domestic_worker"> Domestic Worker</label>
                                    <label class="custom-option"><input type="checkbox" value="driver"> Driver</label>
                                    <label class="custom-option"><input type="checkbox" value="cook"> Cook</label>
                                    <label class="custom-option"><input type="checkbox" value="gardener"> Gardener</label>
                                    <label class="custom-option"><input type="checkbox" value="security_guard"> Security Guard</label>
                                    <label class="custom-option"><input type="checkbox" value="cleaner"> Cleaner</label>
                                    <label class="custom-option"><input type="checkbox" value="babysitter"> Babysitter</label>
                                    <label class="custom-option"><input type="checkbox" value="elderly_care"> Elderly Care</label>
                                    <label class="custom-option"><input type="checkbox" value="construction_worker"> Construction Worker</label>
                                    <label class="custom-option"><input type="checkbox" value="electrician"> Electrician</label>
                                    <label class="custom-option"><input type="checkbox" value="plumber"> Plumber</label>
                                    <label class="custom-option"><input type="checkbox" value="mechanic"> Mechanic</label>
                                    <label class="custom-option"><input type="checkbox" value="nurse"> Nurse</label>
                                    <label class="custom-option"><input type="checkbox" value="teacher"> Teacher</label>
                                    <label class="custom-option"><input type="checkbox" value="office_worker"> Office Worker</label>
                                    <label class="custom-option"><input type="checkbox" value="salesperson"> Salesperson</label>
                                    <label class="custom-option"><input type="checkbox" value="other"> Other</label>
                                </div>
                            </div>
                        </div>
                        <!-- Selected items display area -->
                        <div id="selectedJobTitleTags" class="selected-tags-container"></div>
                    </div>
                    <div class="form-group">
                        <label for="qualification" class="form-label">Qualification</label>
                        <select name="qualification" id="qualification" class="form-select">
                            <option value="">Select your highest qualification</option>
                            <option value="illiterate">Illiterate</option>
                            <option value="primary_school">Primary School</option>
                            <option value="preparatory_school">Preparatory School</option>
                            <option value="high_school">High School</option>
                            <option value="diploma">Diploma</option>
                            <option value="bachelor">Bachelor's Degree</option>
                            <option value="master">Master's Degree</option>
                            <option value="doctorate">Doctorate</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="skills" class="form-label">Skills</label>
                        <select name="skills" id="skills" class="form-select">
                            <option value="">Select your primary skills</option>
                            <option value="cooking">Cooking</option>
                            <option value="cleaning">Cleaning</option>
                            <option value="childcare">Childcare</option>
                            <option value="elderly_care">Elderly Care</option>
                            <option value="driving">Driving</option>
                            <option value="gardening">Gardening</option>
                            <option value="laundry">Laundry</option>
                            <option value="ironing">Ironing</option>
                            <option value="housekeeping">Housekeeping</option>
                            <option value="babysitting">Babysitting</option>
                            <option value="nursing">Nursing</option>
                            <option value="teaching">Teaching</option>
                            <option value="sewing">Sewing</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="local_experience" class="form-label">Local Experience</label>
                        <input type="text" 
                               name="local_experience" 
                               id="local_experience" 
                               class="form-control" 
                               placeholder="Enter years of local work experience">
                    </div>
                    <div class="form-group">
                        <label for="abroad_experience" class="form-label">Abroad Experience</label>
                        <input type="text" 
                               name="abroad_experience" 
                               id="abroad_experience" 
                               class="form-control" 
                               placeholder="Enter years of work experience abroad">
                    </div>
                    <div class="form-group employment-training-bundle">
                        <label for="training_notes" class="form-label">Training / duties notes</label>
                        <textarea name="training_notes" id="training_notes" class="form-control" rows="3" placeholder="Enter training notes, duties, or responsibilities"></textarea>
                    </div>
                    <div class="form-row employment-terms-row">
                        <div class="form-group">
                            <label for="salary" class="form-label">Salary</label>
                            <input type="text" name="salary" id="salary" class="form-control" data-field-key="salary"
                                   placeholder="e.g. 1500.00" inputmode="decimal" lang="en" dir="ltr">
                        </div>
                        <div class="form-group">
                            <label for="working_hours" class="form-label">Working hours</label>
                            <input type="text" name="working_hours" id="working_hours" class="form-control" data-field-key="working_hours"
                                   placeholder="e.g. 8h/day">
                        </div>
                        <div class="form-group">
                            <label for="contract_duration" class="form-label">Contract duration</label>
                            <input type="text" name="contract_duration" id="contract_duration" class="form-control" data-field-key="contract_duration"
                                   placeholder="e.g. 24 months">
                        </div>
                    </div>
                    <?php if ($isIndonesiaProgram): ?>
                    <div class="form-group indonesia-compliance-field">
                        <label for="training_status" class="form-label">Training Status</label>
                        <select name="training_status" id="training_status" class="form-select">
                            <option value="not_started">Not Started</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="form-group indonesia-compliance-field">
                        <label for="training_center" class="form-label">Training Center</label>
                        <input type="text" name="training_center" id="training_center" class="form-control" placeholder="Enter training center">
                    </div>
                    <div class="form-group indonesia-compliance-field">
                        <label for="language_level" class="form-label">Language Level</label>
                        <select name="language_level" id="language_level" class="form-select">
                            <option value="basic">Basic</option>
                            <option value="intermediate">Intermediate</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Contact Info Section -->
                <div class="section contact-info">
                    <h3>Contact Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" id="email" class="form-control" 
                                   placeholder="Enter email address">
                        </div>
                        <div class="form-group">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" name="phone" id="phone" class="form-control" 
                                   placeholder="Enter phone number">
                        </div>
                        <div class="form-group">
                            <label for="agent_id" class="form-label">Agent *</label>
                            <select name="agent_id" 
                                    id="agent_id" 
                                    class="form-select" 
                                    required>
                                <option value="">Select Agent</option>
                                <!-- options here -->
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="subagent_id" class="form-label">Subagent</label>
                            <select name="subagent_id" id="subagent_id" class="form-select">
                                <option value="">Select Subagent</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="country" class="form-label">Country</label>
                            <select name="country" id="country" class="form-select" data-action="load-cities">
                                <option value="">Select Country</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="city" class="form-label">City</label>
                            <select name="city" id="city" class="form-select">
                                <option value="">Select City</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="address" class="form-label">Address</label>
                            <input type="text" name="address" id="address" class="form-control" 
                                   placeholder="Enter address">
                        </div>
                        <div class="form-group">
                            <label for="emergency_name" class="form-label">Emergency Contact</label>
                            <input type="text" name="emergency_name" id="emergency_name" 
                                   class="form-control" placeholder="Enter emergency contact name">
                        </div>
                        <div class="form-group">
                            <label for="emergency_relation" class="form-label">Relationship</label>
                            <select name="emergency_relation" id="emergency_relation" class="form-select">
                                <option value="">Select Relationship</option>
                                <option value="father">Father</option>
                                <option value="mother">Mother</option>
                                <option value="spouse">Spouse</option>
                                <option value="sibling">Sibling</option>
                                <option value="relative">Other Relative</option>
                                <option value="friend">Friend</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="emergency_phone" class="form-label">Emergency Phone</label>
                            <input type="tel" name="emergency_phone" id="emergency_phone" 
                                   class="form-control" placeholder="Enter emergency contact phone">
                        </div>
                        <div class="form-group">
                            <label for="emergency_address" class="form-label">Emergency Address</label>
                            <input type="text" name="emergency_address" id="emergency_address" 
                                   class="form-control" placeholder="Enter emergency contact address">
                        </div>
                    </div>
                </div>

                <!-- Documents Section -->
                <div class="section documents">
                    <h3>Documents</h3>
                    <!-- Identity -->
                    <div class="doc-row identity" data-workflow-stage="identity" data-stage-label="Identity">
                        <div class="doc-group">
                            <label class="form-label">Identity Number</label>
                            <input type="text" 
                                   name="identity_number" 
                                   data-field-key="identity_number"
                                   placeholder="Enter Identity Number">
                            <input type="text" name="identity_date" data-field-key="identity_date" class="date-input" placeholder="Identity Date (YYYY-MM-DD)" autocomplete="off">
                            <div class="upload-wrapper">
                                <input type="file" class="file-input" id="identity_file" accept=".pdf,.jpg,.jpeg,.png">
                                <button type="button" class="upload-btn" data-target="identity_file">
                                    <i class="fas fa-upload"></i> UPLOAD
                                </button>
                            </div>
                            <div class="status-wrapper" data-doc-type="identity">
                                <span class="status-indicator status-pending"></span>
                                <span class="status-text status-pending">pending</span>
                            </div>
                            <input type="hidden" name="identity_status" value="pending">
                        </div>
                    </div>

                    <!-- Passport -->
                    <div class="doc-row passport" data-workflow-stage="passport" data-stage-label="Passport">
                        <div class="doc-group">
                            <label class="form-label">Passport Number</label>
                            <input type="text" 
                                   name="passport_number" 
                                   data-field-key="passport_number"
                                   placeholder="Enter Passport Number">
                            <input type="text" name="passport_date" data-field-key="passport_date" class="date-input" placeholder="Passport Date (YYYY-MM-DD)" autocomplete="off">
                            <input type="text" name="passport_expiry_date" data-field-key="passport_expiry_date" class="date-input" placeholder="Passport Expiry (YYYY-MM-DD)" autocomplete="off">
                            <div class="upload-wrapper">
                                <input type="file" class="file-input" id="passport_file" accept=".pdf,.jpg,.jpeg,.png">
                                <button type="button" class="upload-btn" data-target="passport_file">
                                    <i class="fas fa-upload"></i> UPLOAD
                                </button>
                            </div>
                            <div class="status-wrapper" data-doc-type="passport">
                                <span class="status-indicator status-pending"></span>
                                <span class="status-text status-pending">pending</span>
                            </div>
                            <input type="hidden" name="passport_status" value="pending">
                        </div>
                    </div>

                    <?php if ($isIndonesiaProgram): ?>
                    <!-- Training Certificate -->
                    <div class="doc-row training-certificate indonesia-compliance-field" data-workflow-stage="technical_training" data-stage-label="Technical Training">
                        <div class="doc-group">
                            <label class="form-label">Training Certificate</label>
                            <input type="text" 
                                   name="training_certificate_number" 
                                   placeholder="Enter Training Certificate Number">
                            <input type="text" name="training_certificate_date" class="date-input" placeholder="Training Date (YYYY-MM-DD)" autocomplete="off">
                            <div class="upload-wrapper">
                                <input type="file" class="file-input" id="training_certificate_file" accept=".pdf,.jpg,.jpeg,.png">
                                <button type="button" class="upload-btn" data-target="training_certificate_file">
                                    <i class="fas fa-upload"></i> UPLOAD
                                </button>
                            </div>
                            <div class="status-wrapper" data-doc-type="training_certificate">
                                <span class="status-indicator status-pending"></span>
                                <span class="status-text status-pending">pending</span>
                            </div>
                            <input type="hidden" name="training_certificate_status" value="pending">
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Technical Training -->
                    <div class="doc-row training-certificate" data-workflow-stage="technical_training" data-stage-label="Technical Training">
                        <div class="doc-group">
                            <label class="form-label">Technical Training</label>
                            <input type="text"
                                   name="training_certificate_number"
                                   data-field-key="training_certificate_number"
                                   placeholder="Training Certificate Number">
                            <input type="text" name="training_certificate_date" data-field-key="training_certificate_date" class="date-input" placeholder="Training Date (YYYY-MM-DD)" autocomplete="off">
                            <div class="upload-wrapper">
                                <input type="file" class="file-input" id="training_certificate_file" accept=".pdf,.jpg,.jpeg,.png">
                                <button type="button" class="upload-btn" data-target="training_certificate_file">
                                    <i class="fas fa-upload"></i> UPLOAD
                                </button>
                            </div>
                            <div class="status-wrapper" data-doc-type="training_certificate">
                                <span class="status-indicator status-pending"></span>
                                <span class="status-text status-pending">pending</span>
                            </div>
                            <input type="hidden" name="training_certificate_status" value="pending">
                        </div>
                    </div>

                    <!-- Signed Contract -->
                    <div class="doc-row contract-signed indonesia-compliance-field" data-workflow-stage="contract" data-stage-label="Contract">
                        <div class="doc-group">
                            <label class="form-label">Signed Contract</label>
                            <input type="text" name="contract_signed_number" placeholder="Enter Contract Reference">
                            <div class="upload-wrapper">
                                <input type="file" class="file-input" id="contract_signed_file" accept=".pdf,.jpg,.jpeg,.png">
                                <button type="button" class="upload-btn" data-target="contract_signed_file">
                                    <i class="fas fa-upload"></i> UPLOAD
                                </button>
                            </div>
                            <div class="status-wrapper" data-doc-type="contract_signed">
                                <span class="status-indicator status-pending"></span>
                                <span class="status-text status-pending">pending</span>
                            </div>
                            <input type="hidden" name="contract_signed_status" value="pending">
                        </div>
                    </div>

                    <!-- Insurance -->
                    <div class="doc-row insurance indonesia-compliance-field" data-workflow-stage="government" data-stage-label="Government Registration">
                        <div class="doc-group">
                            <label class="form-label">Insurance</label>
                            <input type="text" name="insurance_number" placeholder="Enter Insurance Reference">
                            <div class="upload-wrapper">
                                <input type="file" class="file-input" id="insurance_file" accept=".pdf,.jpg,.jpeg,.png">
                                <button type="button" class="upload-btn" data-target="insurance_file">
                                    <i class="fas fa-upload"></i> UPLOAD
                                </button>
                            </div>
                            <div class="status-wrapper" data-doc-type="insurance">
                                <span class="status-indicator status-pending"></span>
                                <span class="status-text status-pending">pending</span>
                            </div>
                            <input type="hidden" name="insurance_status" value="pending">
                        </div>
                    </div>
                    <?php endif; ?>
                    

                    <!-- Police Clearance -->
                    <div class="doc-row police" data-workflow-stage="police" data-stage-label="Police Clearance">
                        <div class="doc-group">
                            <label class="form-label">Police Clearance</label>
                            <input type="text" 
                                   name="police_number" 
                                   data-field-key="police_number"
                                   placeholder="Enter Police Clearance Number">
                            <input type="text" name="police_date" data-field-key="police_date" class="date-input" placeholder="Police Date (YYYY-MM-DD)" autocomplete="off">
                            <div class="upload-wrapper">
                                <input type="file" class="file-input" id="police_file" accept=".pdf,.jpg,.jpeg,.png">
                                <button type="button" class="upload-btn" data-target="police_file">
                                    <i class="fas fa-upload"></i> UPLOAD
                                </button>
                            </div>
                            <div class="status-wrapper" data-doc-type="police">
                                <span class="status-indicator status-pending"></span>
                                <span class="status-text status-pending">pending</span>
                            </div>
                            <input type="hidden" name="police_status" value="pending">
                        </div>
                    </div>

                    <!-- Medical Report -->
                    <div class="doc-row medical" data-workflow-stage="medical" data-stage-label="Medical">
                        <div class="doc-group">
                            <label class="form-label">Medical Report</label>
                            <input type="text" 
                                   name="medical_number" 
                                   data-field-key="medical_number"
                                   placeholder="Enter Medical Report Number">
                            <input type="text" name="medical_date" data-field-key="medical_date" class="date-input" placeholder="Medical Date (YYYY-MM-DD)" autocomplete="off">
                            <input type="text" name="medical_center_name" data-field-key="medical_center_name" placeholder="Medical Center Name">
                            <div class="upload-wrapper">
                                <input type="file" class="file-input" id="medical_file" accept=".pdf,.jpg,.jpeg,.png">
                                <button type="button" class="upload-btn" data-target="medical_file">
                                    <i class="fas fa-upload"></i> UPLOAD
                                </button>
                            </div>
                            <div class="status-wrapper" data-doc-type="medical">
                                <span class="status-indicator status-pending"></span>
                                <span class="status-text status-pending">pending</span>
                            </div>
                            <input type="hidden" name="medical_status" value="pending">
                        </div>
                    </div>

                    <!-- Visa -->
                    <div class="doc-row visa" data-workflow-stage="visa" data-stage-label="Visa">
                        <div class="doc-group">
                            <label class="form-label">Visa Number</label>
                            <input type="text" 
                                   name="visa_number" 
                                   data-field-key="visa_number"
                                   placeholder="Enter Visa Number">
                            <input type="text" name="visa_date" data-field-key="visa_date" class="date-input" placeholder="Visa Date (YYYY-MM-DD)" autocomplete="off">
                            <div class="upload-wrapper">
                                <input type="file" class="file-input" id="visa_file" accept=".pdf,.jpg,.jpeg,.png">
                                <button type="button" class="upload-btn" data-target="visa_file">
                                    <i class="fas fa-upload"></i> UPLOAD
                                </button>
                            </div>
                            <div class="status-wrapper" data-doc-type="visa">
                                <span class="status-indicator status-pending"></span>
                                <span class="status-text status-pending">pending</span>
                            </div>
                            <input type="hidden" name="visa_status" value="pending">
                        </div>
                    </div>

                    <?php if ($isIndonesiaProgram): ?>
                    <!-- Exit Permit -->
                    <div class="doc-row exit-permit indonesia-compliance-field" data-workflow-stage="work_permit" data-stage-label="Work Permit">
                        <div class="doc-group">
                            <label class="form-label">Exit Permit</label>
                            <input type="text" name="exit_permit_number" placeholder="Enter Exit Permit Reference">
                            <div class="upload-wrapper">
                                <input type="file" class="file-input" id="exit_permit_file" accept=".pdf,.jpg,.jpeg,.png">
                                <button type="button" class="upload-btn" data-target="exit_permit_file">
                                    <i class="fas fa-upload"></i> UPLOAD
                                </button>
                            </div>
                            <div class="status-wrapper" data-doc-type="exit_permit">
                                <span class="status-indicator status-pending"></span>
                                <span class="status-text status-pending">pending</span>
                            </div>
                            <input type="hidden" name="exit_permit_status" value="pending">
                        </div>
                    </div>

                    <!-- Government Approval -->
                    <div class="doc-row govt-approval indonesia-compliance-field" data-workflow-stage="government" data-stage-label="Government Registration">
                        <div class="doc-group">
                            <label class="form-label">Government Approval</label>
                            <input type="text" name="approval_reference_id" placeholder="Approval Reference ID">
                            <select name="gov_approval_status" class="form-select">
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Ticket -->
                    <div class="doc-row ticket" data-workflow-stage="ticket" data-stage-label="Ticket">
                        <div class="doc-group">
                            <label class="form-label">Ticket Number</label>
                            <input type="text" 
                                   name="ticket_number" 
                                   data-field-key="ticket_number"
                                   placeholder="Enter Ticket Number">
                            <input type="text" name="ticket_date" data-field-key="ticket_date" class="date-input" placeholder="Ticket Date (YYYY-MM-DD)" autocomplete="off">
                            <div class="upload-wrapper">
                                <input type="file" class="file-input" id="ticket_file" accept=".pdf,.jpg,.jpeg,.png">
                                <button type="button" class="upload-btn" data-target="ticket_file">
                                    <i class="fas fa-upload"></i> UPLOAD
                                </button>
                            </div>
                            <div class="status-wrapper" data-doc-type="ticket">
                                <span class="status-indicator status-pending"></span>
                                <span class="status-text status-pending">pending</span>
                            </div>
                            <input type="hidden" name="ticket_status" value="pending">
                        </div>
                    </div>

                    <div class="doc-row country-compliance compliance-black" data-workflow-stage="government" data-stage-label="Government Registration">
                        <div class="doc-group">
                            <label class="form-label">Government Registration</label>
                            <input type="text" name="government_registration_number" data-field-key="government_registration_number" placeholder="Government Registration Number">
                            <input type="text" name="worker_card_number" data-field-key="worker_card_number" placeholder="Worker Card Number">
                            <div class="upload-wrapper">
                                <input type="file" class="file-input" id="country_compliance_primary_file" accept=".pdf,.jpg,.jpeg,.png">
                                <button type="button" class="upload-btn" data-target="country_compliance_primary_file">
                                    <i class="fas fa-upload"></i> UPLOAD
                                </button>
                            </div>
                            <div class="status-wrapper" data-doc-type="country_compliance_primary">
                                <span class="status-indicator status-pending"></span>
                                <span class="status-text status-pending">pending</span>
                            </div>
                            <input type="hidden" name="country_compliance_primary_status" value="pending">
                        </div>
                    </div>

                    <div class="doc-row country-compliance compliance-black" data-workflow-stage="work_permit" data-stage-label="Work Permit">
                        <div class="doc-group">
                            <label class="form-label">Work Permit</label>
                            <input type="text" name="work_permit_number" data-field-key="work_permit_number" placeholder="Work Permit Number">
                            <input type="text" name="insurance_policy_number" data-field-key="insurance_policy_number" placeholder="Insurance Policy Number">
                            <div class="upload-wrapper">
                                <input type="file" class="file-input" id="country_compliance_secondary_file" accept=".pdf,.jpg,.jpeg,.png">
                                <button type="button" class="upload-btn" data-target="country_compliance_secondary_file">
                                    <i class="fas fa-upload"></i> UPLOAD
                                </button>
                            </div>
                            <div class="status-wrapper" data-doc-type="country_compliance_secondary">
                                <span class="status-indicator status-pending"></span>
                                <span class="status-text status-pending">pending</span>
                            </div>
                            <input type="hidden" name="country_compliance_secondary_status" value="pending">
                        </div>
                    </div>

                    <div class="doc-row contract-compliance compliance-black" data-workflow-stage="contract" data-stage-label="Contract">
                        <div class="doc-group">
                            <label class="form-label">Contract (document)</label>
                            <p class="contract-doc-hint">Salary, working hours, and contract duration are edited in <strong>Professional Details</strong> (above).</p>
                            <div class="upload-wrapper">
                                <input type="file" class="file-input" id="contract_deployment_primary_file" accept=".pdf,.jpg,.jpeg,.png">
                                <button type="button" class="upload-btn" data-target="contract_deployment_primary_file">
                                    <i class="fas fa-upload"></i> UPLOAD
                                </button>
                            </div>
                            <div class="status-wrapper" data-doc-type="contract_deployment_primary">
                                <span class="status-indicator status-pending"></span>
                                <span class="status-text status-pending">pending</span>
                            </div>
                            <input type="hidden" name="contract_deployment_primary_status" value="pending">
                        </div>
                    </div>

                    <div class="doc-row contract-compliance compliance-black" data-workflow-stage="travel" data-stage-label="Travel & Departure">
                        <div class="doc-group">
                            <label class="form-label">Travel & Departure</label>
                            <input type="text" name="vacation_days" data-field-key="vacation_days" placeholder="Vacation Days">
                            <input type="text" name="flight_ticket_number" data-field-key="flight_ticket_number" placeholder="Flight Ticket Number">
                            <input type="text" name="travel_date" data-field-key="travel_date" class="date-input" placeholder="Planned Travel Date (YYYY-MM-DD)" autocomplete="off">
                            <div class="upload-wrapper">
                                <input type="file" class="file-input" id="contract_deployment_secondary_file" accept=".pdf,.jpg,.jpeg,.png">
                                <button type="button" class="upload-btn" data-target="contract_deployment_secondary_file">
                                    <i class="fas fa-upload"></i> UPLOAD
                                </button>
                            </div>
                            <div class="status-wrapper" data-doc-type="contract_deployment_secondary">
                                <span class="status-indicator status-pending"></span>
                                <span class="status-text status-pending">pending</span>
                            </div>
                            <input type="hidden" name="contract_deployment_secondary_status" value="pending">
                        </div>
                    </div>

                    <div class="doc-row contract-compliance compliance-black" data-workflow-stage="predeparture_training" data-stage-label="Pre-Departure Training">
                        <div class="doc-group">
                            <label class="form-label">Pre-Departure Training</label>
                            <select name="predeparture_training_completed" class="form-select">
                                <option value="0">Pre-Departure Training: No</option>
                                <option value="1">Pre-Departure Training: Yes</option>
                            </select>
                            <select name="contract_verified" class="form-select">
                                <option value="0">Contract Verified: No</option>
                                <option value="1">Contract Verified: Yes</option>
                            </select>
                            <div class="upload-wrapper">
                                <input type="file" class="file-input" id="contract_deployment_verification_file" accept=".pdf,.jpg,.jpeg,.png">
                                <button type="button" class="upload-btn" data-target="contract_deployment_verification_file">
                                    <i class="fas fa-upload"></i> UPLOAD
                                </button>
                            </div>
                            <div class="status-wrapper" data-doc-type="contract_deployment_verification">
                                <span class="status-indicator status-pending"></span>
                                <span class="status-text status-pending">pending</span>
                            </div>
                            <input type="hidden" name="contract_deployment_verification_status" value="pending">
                        </div>
                    </div>

                <!-- Non-Indonesia lifecycle sections removed to restore previous Ratib Pro form state -->

                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-save">Save Worker</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Form -->
<div id="editWorkerFormContainer" class="form-container"></div>

<!-- Flatpickr + English date picker (load after form exists) - CDN fallback if jsdelivr blocked -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js" 
        data-fallback="https://unpkg.com/flatpickr/dist/flatpickr.min.js"
        onerror="console.error('Failed to load flatpickr from primary CDN');"></script>
<script src="<?php echo asset('js/utils/english-date-picker.js'); ?>?v=<?php echo $cacheBuster; ?>"></script>

<!-- Add notifications import -->
<script type="module" src="<?php echo asset('js/utils/notifications.js'); ?>"></script>

<!-- Console cleanup - suppress warnings in production -->
<script src="<?php echo asset('js/worker/console-cleanup.js'); ?>?v=<?php echo $cacheBuster; ?>"></script>

<!-- Single consolidated worker script (defer: HTML parses while scripts download; order preserved) -->
    <script defer src="<?php echo asset('js/worker/worker-consolidated.js'); ?>?v=<?php echo $cacheBuster; ?>&force=<?php echo $forceCache; ?>"></script>
    <script defer src="<?php echo asset('js/worker/modal-handlers.js'); ?>?v=<?php echo $cacheBuster; ?>&force=<?php echo $forceCache; ?>"></script>
    <script defer src="<?php echo asset('js/worker/worker-deployments.js'); ?>?v=<?php echo $cacheBuster; ?>"></script>
    
    <!-- Worker Form JavaScript -->
    <script defer src="<?php echo asset('js/worker/worker-form.js'); ?>?v=<?php echo $cacheBuster; ?>"></script>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Confirm Delete</h2>
            <button type="button" class="close-btn">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete this worker?</p>
            <div class="modal-actions">
                <button type="button" class="btn-cancel">Cancel</button>
                <button type="button" class="btn-delete">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Document Update Modal -->
<div id="bulkDocumentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Update Document Status</h2>
            <button type="button" class="close-btn">&times;</button>
        </div>
        <div class="modal-body">
            <form id="bulkDocumentForm">
                <div class="form-group">
                    <label for="docType">Document Type</label>
                    <select id="docType" class="form-control" required>
                        <option value="">Select Document Type</option>
                        <?php if ($isIndonesiaProgram): ?>
                        <option value="contract_signed">Signed Contract</option>
                        <option value="insurance">Insurance</option>
                        <?php endif; ?>
                        <option value="police">Police Clearance</option>
                        <option value="medical">Medical Report</option>
                        <option value="visa">Visa</option>
                        <?php if ($isIndonesiaProgram): ?>
                        <option value="exit_permit">Exit Permit</option>
                        <?php endif; ?>
                        <option value="ticket">Ticket</option>
                        <?php if ($isIndonesiaProgram): ?>
                        <option value="training_certificate">Training Certificate</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="docStatus">Status</label>
                    <select id="docStatus" class="form-control" required>
                        <option value="">Select Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-save">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Documents Modal -->
<div id="documentsModal" class="modal">
    <div class="modal-content modern-documents-modal">
        <div class="modal-header">
            <div class="header-content">
                <div class="header-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="header-text">
                    <h2>Worker Documents</h2>
                    <p id="documentsWorkerName">Worker Name</p>
                </div>
            </div>
            <button type="button" class="close-btn">&times;</button>
        </div>
        
        <div class="modal-body">
            <!-- Progress Overview -->
            <div class="progress-overview">
                <div class="progress-stats">
                    <div class="stat-item">
                        <span class="stat-number" id="documentProgressCount">0</span>
                        <span class="stat-label">Completed</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">6</span>
                        <span class="stat-label">Total</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" id="documentsWorkerStatus">Active</span>
                        <span class="stat-label">Status</span>
                    </div>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar">
                        <div class="progress-fill" id="documentProgressFill"></div>
                    </div>
                    <span class="progress-percentage" id="documentProgressPercentage">0%</span>
                </div>
            </div>

            <!-- Documents Form -->
            <form id="documentsForm" class="modern-documents-form">
                <input type="hidden" name="worker_id" id="documentsWorkerIdField">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="documents-grid">
                    <!-- Identity Document -->
                    <div class="document-card" data-type="identity">
                        <div class="card-header">
                            <div class="document-icon identity">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div class="document-info">
                                <h3>Identity</h3>
                                <p>National ID Document</p>
                            </div>
                            <div class="status-badge" id="identityStatusBadge">
                                <select name="identity_status" id="identityStatus">
                                    <option value="pending">Pending</option>
                                    <option value="ok">OK</option>
                                    <option value="passed">Passed</option>
                                    <option value="failed">Failed</option>
                                    <option value="not_ok">Not OK</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-content">
                            <div class="input-group">
                                <input type="text" name="identity_number" id="identityNumber" placeholder="Identity Number">
                                <input type="text" name="identity_date" id="identityDate" class="date-input" placeholder="YYYY-MM-DD" autocomplete="off">
                            </div>
                            <div class="file-section">
                                <input type="file" name="identity_file" id="identityFile" accept=".pdf,.jpg,.jpeg,.png" class="file-input">
                                <button type="button" class="file-btn" data-target="identityFile">
                                    <i class="fas fa-upload"></i>
                                    <span>Upload</span>
                                </button>
                                <div class="current-file" id="identityCurrentFile">
                                    <span class="no-file">No file</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Passport Document -->
                    <div class="document-card" data-type="passport">
                        <div class="card-header">
                            <div class="document-icon passport">
                                <i class="fas fa-passport"></i>
                            </div>
                            <div class="document-info">
                                <h3>Passport</h3>
                                <p>Travel Document</p>
                            </div>
                            <div class="status-badge" id="passportStatusBadge">
                                <select name="passport_status" id="passportStatus">
                                    <option value="pending">Pending</option>
                                    <option value="ok">OK</option>
                                    <option value="not_ok">Not OK</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-content">
                            <div class="input-group">
                                <input type="text" name="passport_number" id="passportNumber" placeholder="Passport Number">
                                <input type="text" name="passport_date" id="passportDate" class="date-input" placeholder="YYYY-MM-DD" autocomplete="off">
                            </div>
                            <div class="file-section">
                                <input type="file" name="passport_file" id="passportFile" accept=".pdf,.jpg,.jpeg,.png" class="file-input">
                                <button type="button" class="file-btn" data-target="passportFile">
                                    <i class="fas fa-upload"></i>
                                    <span>Upload</span>
                                </button>
                                <div class="current-file" id="passportCurrentFile">
                                    <span class="no-file">No file</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Police Clearance -->
                    <div class="document-card" data-type="police">
                        <div class="card-header">
                            <div class="document-icon police">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="document-info">
                                <h3>Police Clearance</h3>
                                <p>Background Check</p>
                            </div>
                            <div class="status-badge" id="policeStatusBadge">
                                <select name="police_status" id="policeStatus">
                                    <option value="pending">Pending</option>
                                    <option value="ok">OK</option>
                                    <option value="not_ok">Not OK</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-content">
                            <div class="input-group">
                                <input type="text" name="police_number" id="policeNumber" placeholder="Clearance Number">
                                <input type="text" name="police_date" id="policeDate" class="date-input" placeholder="YYYY-MM-DD" autocomplete="off">
                            </div>
                            <div class="file-section">
                                <input type="file" name="police_file" id="policeFile" accept=".pdf,.jpg,.jpeg,.png" class="file-input">
                                <button type="button" class="file-btn" data-target="policeFile">
                                    <i class="fas fa-upload"></i>
                                    <span>Upload</span>
                                </button>
                                <div class="current-file" id="policeCurrentFile">
                                    <span class="no-file">No file</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Medical Report -->
                    <div class="document-card" data-type="medical">
                        <div class="card-header">
                            <div class="document-icon medical">
                                <i class="fas fa-heartbeat"></i>
                            </div>
                            <div class="document-info">
                                <h3>Medical Report</h3>
                                <p>Health Certificate</p>
                            </div>
                            <div class="status-badge" id="medicalStatusBadge">
                                <select name="medical_status" id="medicalStatus">
                                    <option value="pending">Pending</option>
                                    <option value="ok">OK</option>
                                    <option value="not_ok">Not OK</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-content">
                            <div class="input-group">
                                <input type="text" name="medical_number" id="medicalNumber" placeholder="Report Number">
                                <input type="text" name="medical_date" id="medicalDate" class="date-input" placeholder="YYYY-MM-DD" autocomplete="off">
                            </div>
                            <div class="file-section">
                                <input type="file" name="medical_file" id="medicalFile" accept=".pdf,.jpg,.jpeg,.png" class="file-input">
                                <button type="button" class="file-btn" data-target="medicalFile">
                                    <i class="fas fa-upload"></i>
                                    <span>Upload</span>
                                </button>
                                <div class="current-file" id="medicalCurrentFile">
                                    <span class="no-file">No file</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Visa Document -->
                    <div class="document-card" data-type="visa">
                        <div class="card-header">
                            <div class="document-icon visa">
                                <i class="fas fa-passport"></i>
                            </div>
                            <div class="document-info">
                                <h3>Visa</h3>
                                <p>Entry Permit</p>
                            </div>
                            <div class="status-badge" id="visaStatusBadge">
                                <select name="visa_status" id="visaStatus">
                                    <option value="pending">Pending</option>
                                    <option value="ok">OK</option>
                                    <option value="not_ok">Not OK</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-content">
                            <div class="input-group">
                                <input type="text" name="visa_number" id="visaNumber" placeholder="Visa Number">
                                <input type="text" name="visa_date" id="visaDate" class="date-input" placeholder="YYYY-MM-DD" autocomplete="off">
                            </div>
                            <div class="file-section">
                                <input type="file" name="visa_file" id="visaFile" accept=".pdf,.jpg,.jpeg,.png" class="file-input">
                                <button type="button" class="file-btn" data-target="visaFile">
                                    <i class="fas fa-upload"></i>
                                    <span>Upload</span>
                                </button>
                                <div class="current-file" id="visaCurrentFile">
                                    <span class="no-file">No file</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ticket Document -->
                    <div class="document-card" data-type="ticket">
                        <div class="card-header">
                            <div class="document-icon ticket">
                                <i class="fas fa-plane"></i>
                            </div>
                            <div class="document-info">
                                <h3>Ticket</h3>
                                <p>Flight Booking</p>
                            </div>
                            <div class="status-badge" id="ticketStatusBadge">
                                <select name="ticket_status" id="ticketStatus">
                                    <option value="pending">Pending</option>
                                    <option value="ok">OK</option>
                                    <option value="not_ok">Not OK</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-content">
                            <div class="input-group">
                                <input type="text" name="ticket_number" id="ticketNumber" placeholder="Ticket Number">
                                <input type="text" name="ticket_date" id="ticketDate" class="date-input" placeholder="YYYY-MM-DD" autocomplete="off">
                            </div>
                            <div class="file-section">
                                <input type="file" name="ticket_file" id="ticketFile" accept=".pdf,.jpg,.jpeg,.png" class="file-input">
                                <button type="button" class="file-btn" data-target="ticketFile">
                                    <i class="fas fa-upload"></i>
                                    <span>Upload</span>
                                </button>
                                <div class="current-file" id="ticketCurrentFile">
                                    <span class="no-file">No file</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($isIndonesiaProgram): ?>
                    <!-- Training Certificate Document -->
                    <div class="document-card indonesia-compliance-field" data-type="training_certificate">
                        <div class="card-header">
                            <div class="document-icon training-certificate">
                                <i class="fas fa-certificate"></i>
                            </div>
                            <div class="document-info">
                                <h3>Training Certificate</h3>
                                <p>Training Completion Proof</p>
                            </div>
                            <div class="status-badge" id="trainingCertificateStatusBadge">
                                <select name="training_certificate_status" id="trainingCertificateStatus">
                                    <option value="pending">Pending</option>
                                    <option value="ok">OK</option>
                                    <option value="not_ok">Not OK</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-content">
                            <div class="input-group">
                                <input type="text" name="training_certificate_number" id="trainingCertificateNumber" placeholder="Certificate Number">
                                <input type="text" name="training_certificate_date" id="trainingCertificateDate" class="date-input" placeholder="YYYY-MM-DD" autocomplete="off">
                            </div>
                            <div class="file-section">
                                <input type="file" name="training_certificate_file" id="trainingCertificateFile" accept=".pdf,.jpg,.jpeg,.png" class="file-input">
                                <button type="button" class="file-btn" data-target="trainingCertificateFile">
                                    <i class="fas fa-upload"></i>
                                    <span>Upload</span>
                                </button>
                                <div class="current-file" id="trainingCertificateCurrentFile">
                                    <span class="no-file">No file</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="button" class="btn-cancel" id="closeDocumentsModal">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i>
                        Save Documents
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Musaned JavaScript -->
<script src="<?php echo asset('js/worker/musaned.js'); ?>?v=<?php echo $cacheBuster; ?>"></script>
<script src="<?php echo asset('js/utils/currencies-utils.js'); ?>?v=<?php echo $cacheBuster; ?>"></script>

<!-- Document Viewer Modal -->
<div id="documentViewerModal" class="modal">
    <div class="modal-content document-viewer">
        <div class="modal-header">
            <h2>Document Viewer</h2>
            <button type="button" class="close-btn" id="closeDocumentViewer">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="document-container">
                <iframe id="documentFrame" src="" frameborder="0"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Modern Closing Alert Modal -->
<div id="closingAlertModal" class="closing-alert-modal d-none">
    <div class="closing-alert-content">
        <div class="closing-alert-header">
            <div class="closing-alert-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3>Unsaved Changes</h3>
        </div>
        <div class="closing-alert-body">
            <p>You have unsaved changes. Are you sure you want to close without saving?</p>
        </div>
        <div class="closing-alert-footer">
            <button id="closingAlertCancel" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button id="closingAlertDiscard" class="btn btn-danger">
                <i class="fas fa-trash"></i> Discard Changes
            </button>
        </div>
    </div>
</div>

<div id="workerDeploymentModal" class="modal-wrap">
    <div class="modal-card glass-card">
        <div class="modal-header-row">
            <h3>Send Worker Abroad</h3>
            <button id="closeDeploymentModal" class="icon-btn" type="button">×</button>
        </div>
        <form id="workerDeploymentForm" class="grid-form">
            <input type="hidden" id="deploymentWorkerId">
            <select id="deploymentAgency" required></select>
            <input id="deploymentCountry" type="text" placeholder="Country" required>
            <input id="deploymentJobTitle" type="text" placeholder="Job Title" required>
            <input id="deploymentSalary" type="text" inputmode="decimal" lang="en" dir="ltr" placeholder="Salary (e.g. 1500.00)">
            <input id="deploymentContractStart" type="text" class="date-input" inputmode="numeric" lang="en" dir="ltr" placeholder="contract_start">
            <input id="deploymentContractEnd" type="text" class="date-input" inputmode="numeric" lang="en" dir="ltr" placeholder="contract_end">
            <small class="deployment-field-hint">contract_start (when worker starts job abroad)</small>
            <small class="deployment-field-hint">contract_end (when contract is expected to finish)</small>
            <textarea id="deploymentNotes" rows="3" placeholder="Notes"></textarea>
            <div class="form-actions">
                <button id="cancelDeploymentBtn" type="button" class="muted-btn">Cancel</button>
                <button type="submit" class="neon-btn">Save Deployment</button>
            </div>
        </form>
    </div>
</div>

<!-- Countries and cities are now loaded dynamically from System Settings via API -->
<!-- DEPRECATED: countries-cities.js is no longer used - removed to prevent hardcoded data -->


<!-- Closing alert handler for worker forms - DISABLED: Now using UniversalClosingAlerts -->
<!-- <script src="<?php echo asset('js/worker/closing-alert-handler.js'); ?>?v=<?php echo $cacheBuster; ?>"></script> -->

<?php
// Check if footer exists
$footerPath = '../includes/footer.php';
if (!file_exists($footerPath)) {
    echo "Footer file missing!";
} else {
    include $footerPath;
}
?> 