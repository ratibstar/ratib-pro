<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/dashboard-hr.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/dashboard-hr.php`.
 */
/**
 * HR Management Dashboard - Ratib Pro
 */
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] < 1
    || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

if (empty($_GET['embedded'])) {
    header('Location: ' . pageUrl('hr.php'));
    exit;
}

$pageTitle = 'HR Management - Control Panel';
$baseUrl = getBaseUrl();
$path = $_SERVER['REQUEST_URI'] ?? '';
$basePath = preg_replace('#/pages/[^?]*.*$#', '', $path) ?: '';
$fullBase = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $basePath, '/');
if ($baseUrl) {
    $fullBase = rtrim($baseUrl, '/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%236b21a8'/%3E%3Ctext x='16' y='22' font-size='18' font-family='sans-serif' fill='white' text-anchor='middle'%3ER%3C/text%3E%3C/svg%3E">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <link rel="stylesheet" href="<?php echo asset('css/hr.css'); ?>?v=<?php echo filemtime(__DIR__ . '/../css/hr.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/control-accounting.css'); ?>?v=9">
</head>
<body class="control-embedded-hr">
    <div id="app-config" data-base-url="<?php echo htmlspecialchars($fullBase, ENT_QUOTES, 'UTF-8'); ?>" data-api-base="<?php echo htmlspecialchars($fullBase . '/api', ENT_QUOTES, 'UTF-8'); ?>" data-control="1" class="hidden"></div>
    <script>(function(){var e=document.getElementById('app-config');if(e){window.APP_CONFIG=window.APP_CONFIG||{};window.APP_CONFIG.baseUrl=e.getAttribute('data-base-url')||'';window.APP_CONFIG.apiBase=e.getAttribute('data-api-base')||(window.APP_CONFIG.baseUrl+'/api');window.BASE_PATH=window.APP_CONFIG.baseUrl;window.API_BASE=window.APP_CONFIG.apiBase;}})();</script>

    <div class="main-content">
        <!-- HR Dashboard Header -->
        <div class="hr-header">
            <div class="hr-title">
                <h1><i class="fas fa-users-cog"></i> HR Management System</h1>
                <p>Manage employees, attendance, payroll, and more</p>
            </div>
            <div class="hr-actions">
                <button class="btn btn-primary" id="addEmployeeBtn" data-permission="add_employee">
                    <i class="fas fa-user-plus"></i> Add Employee
                </button>
                <button class="btn btn-success" id="markAttendanceBtn" data-permission="view_hr_dashboard">
                    <i class="fas fa-clock"></i> Mark Attendance
                </button>
            </div>
        </div>

        <!-- Dashboard Stats -->
        <div class="hr-stats-grid">
            <div class="stat-card employees" data-hr-module="employees">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3 id="employeeCount">0</h3>
                    <p>Total Employees</p>
                </div>
            </div>

            <div class="stat-card attendance" data-hr-module="attendance">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3 id="attendanceCount">0</h3>
                    <p>Today's Attendance</p>
                </div>
            </div>

            <div class="stat-card advances" data-hr-module="advances">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-info">
                    <h3 id="advanceCount">0</h3>
                    <p>Pending Advances</p>
                </div>
            </div>

            <div class="stat-card salaries" data-hr-module="salaries">
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-info">
                    <h3 id="salaryCount">0</h3>
                    <p>Pending Salaries</p>
                </div>
            </div>

            <div class="stat-card documents" data-hr-module="documents">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-info">
                    <h3 id="documentCount">0</h3>
                    <p>Active Documents</p>
                </div>
            </div>

            <div class="stat-card cars" data-hr-module="cars">
                <div class="stat-icon">
                    <i class="fas fa-car"></i>
                </div>
                <div class="stat-info">
                    <h3 id="carCount">0</h3>
                    <p>Company Vehicles</p>
                </div>
            </div>
        </div>

        <!-- HR Modules - Compact Grid -->
        <div class="hr-modules">
            <div class="module-card" data-hr-module="employees">
                <div class="module-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="module-content">
                    <h3>Employees</h3>
                    <p>Manage employee records and information</p>
                    <div class="module-actions">
                        <button class="hr-btn hr-btn-primary" data-hr-action="view" data-hr-module="employees">
                            <i class="fas fa-list"></i> View
                        </button>
                        <button class="hr-btn hr-btn-success" data-hr-action="add" data-hr-module="employees">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                </div>
            </div>

            <div class="module-card" data-hr-module="attendance">
                <div class="module-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="module-content">
                    <h3>Attendance</h3>
                    <p>Track employee attendance and time</p>
                    <div class="module-actions">
                        <button class="hr-btn hr-btn-primary" data-hr-action="view" data-hr-module="attendance">
                            <i class="fas fa-list"></i> View
                        </button>
                        <button class="hr-btn hr-btn-success" data-hr-action="mark" data-hr-module="attendance">
                            <i class="fas fa-check"></i> Mark
                        </button>
                    </div>
                </div>
            </div>

            <div class="module-card" data-hr-module="advances">
                <div class="module-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="module-content">
                    <h3>Advances</h3>
                    <p>Manage advance payments and approvals</p>
                    <div class="module-actions">
                        <button class="hr-btn hr-btn-primary" data-hr-action="view" data-hr-module="advances">
                            <i class="fas fa-list"></i> View
                        </button>
                        <button class="hr-btn hr-btn-success" data-hr-action="add" data-hr-module="advances">
                            <i class="fas fa-plus"></i> New
                        </button>
                    </div>
                </div>
            </div>

            <div class="module-card" data-hr-module="salaries">
                <div class="module-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="module-content">
                    <h3>Payroll</h3>
                    <p>Process salaries and manage payroll</p>
                    <div class="module-actions">
                        <button class="hr-btn hr-btn-primary" data-hr-action="view" data-hr-module="salaries">
                            <i class="fas fa-list"></i> View
                        </button>
                        <button class="hr-btn hr-btn-success" data-hr-action="process" data-hr-module="salaries">
                            <i class="fas fa-calculator"></i> Process
                        </button>
                    </div>
                </div>
            </div>

            <div class="module-card" data-hr-module="documents">
                <div class="module-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="module-content">
                    <h3>Documents</h3>
                    <p>Store and manage employee documents</p>
                    <div class="module-actions">
                        <button class="hr-btn hr-btn-primary" data-hr-action="view" data-hr-module="documents">
                            <i class="fas fa-list"></i> View
                        </button>
                        <button class="hr-btn hr-btn-success" data-hr-action="upload" data-hr-module="documents">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                    </div>
                </div>
            </div>

            <div class="module-card" data-hr-module="cars">
                <div class="module-icon">
                    <i class="fas fa-car"></i>
                </div>
                <div class="module-content">
                    <h3>Vehicles</h3>
                    <p>Manage company vehicles and drivers</p>
                    <div class="module-actions">
                        <button class="hr-btn hr-btn-primary" data-hr-action="view" data-hr-module="cars">
                            <i class="fas fa-list"></i> View
                        </button>
                        <button class="hr-btn hr-btn-success" data-hr-action="add" data-hr-module="cars">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- HR Settings - Mobile Optimized -->
        <div class="hr-settings">
            <div class="settings-card">
                <div class="settings-header">
                    <h3><i class="fas fa-cog"></i> HR Settings</h3>
                    <button class="hr-btn hr-btn-primary" id="configureSettingsBtn">
                        <i class="fas fa-edit"></i> Configure
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- HR Forms Modal - Force LTR and English, no Arabic -->
    <div id="hrModal" class="modal fade" tabindex="-1" aria-labelledby="hrModalTitle" aria-hidden="true" dir="ltr" lang="en">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="hrModalTitle">HR Management</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="hrModalBody" dir="ltr" lang="en">
                    <!-- Dynamic content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js"></script>
    <script src="<?php echo asset('js/hr.js'); ?>"></script>
    <script src="<?php echo asset('js/hr-forms.js'); ?>"></script>
    <script src="<?php echo asset('js/hr/hr-page-init.js'); ?>"></script>
    <script src="<?php echo asset('js/utils/currencies-utils.js'); ?>"></script>
    <script src="<?php echo asset('js/countries-cities.js'); ?>"></script>
    <script src="<?php echo asset('js/hr/countries-cities-handler.js'); ?>"></script>
    <script src="<?php echo asset('js/hr/hr-page.js'); ?>"></script>
</body>
</html>
