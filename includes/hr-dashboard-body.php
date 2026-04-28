<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/hr-dashboard-body.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/hr-dashboard-body.php`.
 */
/**
 * Shared HR dashboard markup (Ratib Pro HR) — used by pages/hr.php and control-panel HR.
 * Expects Bootstrap/Font Awesome already loaded; modal + .main-content match js/hr.js.
 */
?>
<div class="main-content">
    <div class="hr-header">
        <div class="hr-title">
            <h1><i class="fas fa-users-cog"></i> HR Management System</h1>
            <p>Manage employees, attendance, payroll, and more</p>
        </div>
        <div class="hr-actions">
            <button type="button" class="btn btn-primary" id="addEmployeeBtn" data-permission="add_employee">
                <i class="fas fa-user-plus"></i> Add Employee
            </button>
            <button type="button" class="btn btn-success" id="markAttendanceBtn" data-permission="view_hr_dashboard">
                <i class="fas fa-clock"></i> Mark Attendance
            </button>
        </div>
    </div>

    <div class="hr-stats-grid">
        <div class="stat-card employees" data-hr-module="employees">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <h3 id="employeeCount">0</h3>
                <p>Total Employees</p>
            </div>
        </div>
        <div class="stat-card attendance" data-hr-module="attendance">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <h3 id="attendanceCount">0</h3>
                <p>Today's Attendance</p>
            </div>
        </div>
        <div class="stat-card advances" data-hr-module="advances">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-info">
                <h3 id="advanceCount">0</h3>
                <p>Pending Advances</p>
            </div>
        </div>
        <div class="stat-card salaries" data-hr-module="salaries">
            <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
            <div class="stat-info">
                <h3 id="salaryCount">0</h3>
                <p>Pending Salaries</p>
            </div>
        </div>
        <div class="stat-card documents" data-hr-module="documents">
            <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
            <div class="stat-info">
                <h3 id="documentCount">0</h3>
                <p>Active Documents</p>
            </div>
        </div>
        <div class="stat-card cars" data-hr-module="cars">
            <div class="stat-icon"><i class="fas fa-car"></i></div>
            <div class="stat-info">
                <h3 id="carCount">0</h3>
                <p>Company Vehicles</p>
            </div>
        </div>
    </div>

    <div class="hr-modules">
        <div class="module-card" data-hr-module="employees">
            <div class="module-icon"><i class="fas fa-users"></i></div>
            <div class="module-content">
                <h3>Employees</h3>
                <p>Manage employee records and information</p>
                <div class="module-actions">
                    <button type="button" class="hr-btn hr-btn-primary" data-hr-action="view" data-hr-module="employees"><i class="fas fa-list"></i> View</button>
                    <button type="button" class="hr-btn hr-btn-success" data-hr-action="add" data-hr-module="employees"><i class="fas fa-plus"></i> Add</button>
                </div>
            </div>
        </div>
        <div class="module-card" data-hr-module="attendance">
            <div class="module-icon"><i class="fas fa-clock"></i></div>
            <div class="module-content">
                <h3>Attendance</h3>
                <p>Track employee attendance and time</p>
                <div class="module-actions">
                    <button type="button" class="hr-btn hr-btn-primary" data-hr-action="view" data-hr-module="attendance"><i class="fas fa-list"></i> View</button>
                    <button type="button" class="hr-btn hr-btn-success" data-hr-action="mark" data-hr-module="attendance"><i class="fas fa-check"></i> Mark</button>
                </div>
            </div>
        </div>
        <div class="module-card" data-hr-module="advances">
            <div class="module-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="module-content">
                <h3>Advances</h3>
                <p>Manage advance payments and approvals</p>
                <div class="module-actions">
                    <button type="button" class="hr-btn hr-btn-primary" data-hr-action="view" data-hr-module="advances"><i class="fas fa-list"></i> View</button>
                    <button type="button" class="hr-btn hr-btn-success" data-hr-action="add" data-hr-module="advances"><i class="fas fa-plus"></i> New</button>
                </div>
            </div>
        </div>
        <div class="module-card" data-hr-module="salaries">
            <div class="module-icon"><i class="fas fa-dollar-sign"></i></div>
            <div class="module-content">
                <h3>Payroll</h3>
                <p>Process salaries and manage payroll</p>
                <div class="module-actions">
                    <button type="button" class="hr-btn hr-btn-primary" data-hr-action="view" data-hr-module="salaries"><i class="fas fa-list"></i> View</button>
                    <button type="button" class="hr-btn hr-btn-success" data-hr-action="process" data-hr-module="salaries"><i class="fas fa-calculator"></i> Process</button>
                </div>
            </div>
        </div>
        <div class="module-card" data-hr-module="documents">
            <div class="module-icon"><i class="fas fa-file-alt"></i></div>
            <div class="module-content">
                <h3>Documents</h3>
                <p>Store and manage employee documents</p>
                <div class="module-actions">
                    <button type="button" class="hr-btn hr-btn-primary" data-hr-action="view" data-hr-module="documents"><i class="fas fa-list"></i> View</button>
                    <button type="button" class="hr-btn hr-btn-success" data-hr-action="upload" data-hr-module="documents"><i class="fas fa-upload"></i> Upload</button>
                </div>
            </div>
        </div>
        <div class="module-card" data-hr-module="cars">
            <div class="module-icon"><i class="fas fa-car"></i></div>
            <div class="module-content">
                <h3>Vehicles</h3>
                <p>Manage company vehicles and drivers</p>
                <div class="module-actions">
                    <button type="button" class="hr-btn hr-btn-primary" data-hr-action="view" data-hr-module="cars"><i class="fas fa-list"></i> View</button>
                    <button type="button" class="hr-btn hr-btn-success" data-hr-action="add" data-hr-module="cars"><i class="fas fa-plus"></i> Add</button>
                </div>
            </div>
        </div>
    </div>

    <div class="hr-settings">
        <div class="settings-card">
            <div class="settings-header">
                <h3><i class="fas fa-cog"></i> HR Settings</h3>
                <button type="button" class="hr-btn hr-btn-primary" id="configureSettingsBtn"><i class="fas fa-edit"></i> Configure</button>
            </div>
        </div>
    </div>
</div>

<div id="hrModal" class="modal fade" tabindex="-1" aria-labelledby="hrModalTitle" aria-hidden="true" dir="ltr" lang="en">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="hrModalTitle">HR Management</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="hrModalBody" dir="ltr" lang="en"></div>
        </div>
    </div>
</div>
