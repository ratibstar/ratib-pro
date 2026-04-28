/**
 * EN: Implements frontend interaction behavior in `js/hr-forms.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/hr-forms.js`.
 */
// HR Forms - No Scrolling Version

function getControlHrApiBaseForForms() {
    const el = document.getElementById('app-config');
    const a = (window.APP_CONFIG && window.APP_CONFIG.controlHrApiBase) || (el && el.getAttribute('data-control-hr-api-base')) || '';
    return a ? String(a).replace(/\/+$/, '') : '';
}

/** Full URL to an HR PHP endpoint (main: /api/hr/x.php, CP: /control-panel/api/control/hr/x.php). */
function hrFormApiUrl(pathAfterHr) {
    const q = String(pathAfterHr).replace(/^\//, '');
    const cp = getControlHrApiBaseForForms();
    if (cp) return `${cp}/${q}`;
    const root = getHRApiBaseForForms();
    return `${root}/hr/${q}`;
}

// Append &control=1 when embedded in main app HR with control DB — not when using CP HR proxy URLs.
function getHRControlSuffix() {
    if (getControlHrApiBaseForForms()) return '';
    const el = document.getElementById('app-config');
    return (el && el.getAttribute('data-control') === '1') ? '&control=1' : '';
}

/** Same as main HR api root: APP_CONFIG.apiBase (site /api), not baseUrl (/control-panel). */
function getHRApiBaseForForms() {
    const b = (window.APP_CONFIG && window.APP_CONFIG.apiBase) || (window.API_BASE || '');
    if (b) return b.replace(/\/$/, '');
    const base = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (window.BASE_PATH || '') || '';
    return base ? base.replace(/\/$/, '') + '/api' : '/api';
}

// Create compact employee form
function createEmployeeForm() {
    return `
        <form id="employeeForm" class="hr-form" dir="ltr" lang="en">
            <div class="form-content">
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" class="form-control" id="name" name="name" required dir="ltr" lang="en">
                </div>
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="text" class="form-control" id="email" name="email" required dir="ltr" lang="en" autocomplete="email" inputmode="email" autocapitalize="off" spellcheck="false"
                        pattern=".+@.+\..+"
                        title="Valid email: name@company.com (domain must contain a dot). No spaces or slashes.">
                </div>
                <div class="form-group">
                    <label for="phone">Phone *</label>
                    <input type="tel" class="form-control" id="phone" name="phone" required dir="ltr" lang="en">
                </div>
                <div class="form-group">
                    <label for="birthdate">Birth Date</label>
                    <input type="text" class="form-control date-input" id="birthdate" name="birthdate" placeholder="YYYY-MM-DD" autocomplete="off" dir="ltr" lang="en">
                </div>
                <div class="form-group">
                    <label for="department">Department *</label>
                    <select class="form-select" id="department" name="department" required dir="ltr" lang="en">
                        <option value="">Select Department</option>
                        <option value="HR Management">HR Management</option>
                        <option value="Recruitment">Recruitment</option>
                        <option value="Training">Training</option>
                        <option value="Employee Relations">Employee Relations</option>
                        <option value="Finance">Finance</option>
                        <option value="IT">IT</option>
                        <option value="Operations">Operations</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="position">Position *</label>
                    <select class="form-select" id="position" name="position" required dir="ltr" lang="en">
                        <option value="">Select Position</option>
                        <option value="Chief Human Resources Officer (CHRO)">Chief Human Resources Officer (CHRO)</option>
                        <option value="VP of Human Resources">VP of Human Resources</option>
                        <option value="HR Director">HR Director</option>
                        <option value="HR Manager">HR Manager</option>
                        <option value="HR Generalist">HR Generalist</option>
                        <option value="HR Specialist">HR Specialist</option>
                        <option value="HR Assistant">HR Assistant</option>
                        <option value="HR Coordinator">HR Coordinator</option>
                        <option value="HR Business Partner">HR Business Partner</option>
                        <option value="Senior HR Business Partner">Senior HR Business Partner</option>
                        <option value="Recruitment Manager">Recruitment Manager</option>
                        <option value="Recruitment Specialist">Recruitment Specialist</option>
                        <option value="Talent Acquisition Manager">Talent Acquisition Manager</option>
                        <option value="Talent Acquisition Specialist">Talent Acquisition Specialist</option>
                        <option value="Talent Acquisition Coordinator">Talent Acquisition Coordinator</option>
                        <option value="Recruiter">Recruiter</option>
                        <option value="Senior Recruiter">Senior Recruiter</option>
                        <option value="Head of Talent Acquisition">Head of Talent Acquisition</option>
                        <option value="Training Manager">Training Manager</option>
                        <option value="Training Specialist">Training Specialist</option>
                        <option value="Learning & Development Manager">Learning & Development Manager</option>
                        <option value="Learning & Development Specialist">Learning & Development Specialist</option>
                        <option value="Training Coordinator">Training Coordinator</option>
                        <option value="Organizational Development Manager">Organizational Development Manager</option>
                        <option value="Employee Engagement Specialist">Employee Engagement Specialist</option>
                        <option value="Payroll Manager">Payroll Manager</option>
                        <option value="Payroll Specialist">Payroll Specialist</option>
                        <option value="Payroll Administrator">Payroll Administrator</option>
                        <option value="Payroll Coordinator">Payroll Coordinator</option>
                        <option value="Employee Relations Manager">Employee Relations Manager</option>
                        <option value="Employee Relations Specialist">Employee Relations Specialist</option>
                        <option value="Labor Relations Manager">Labor Relations Manager</option>
                        <option value="Compensation Manager">Compensation Manager</option>
                        <option value="Compensation Analyst">Compensation Analyst</option>
                        <option value="Benefits Manager">Benefits Manager</option>
                        <option value="Benefits Administrator">Benefits Administrator</option>
                        <option value="Benefits Specialist">Benefits Specialist</option>
                        <option value="Benefits Analyst">Benefits Analyst</option>
                        <option value="HRIS Administrator">HRIS Administrator</option>
                        <option value="HRIS Analyst">HRIS Analyst</option>
                        <option value="HR Technology Specialist">HR Technology Specialist</option>
                        <option value="Workforce Planning Manager">Workforce Planning Manager</option>
                        <option value="Succession Planning Specialist">Succession Planning Specialist</option>
                        <option value="HR Compliance Manager">HR Compliance Manager</option>
                        <option value="HR Compliance Specialist">HR Compliance Specialist</option>
                        <option value="HR Data Analyst">HR Data Analyst</option>
                        <option value="HR Reporting Analyst">HR Reporting Analyst</option>
                        <option value="Onboarding Specialist">Onboarding Specialist</option>
                        <option value="Offboarding Specialist">Offboarding Specialist</option>
                        <option value="HR Administrator">HR Administrator</option>
                        <option value="Executive Assistant to HR">Executive Assistant to HR</option>
                        <option value="HR Receptionist">HR Receptionist</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="join_date">Join Date *</label>
                    <input type="text" class="form-control date-input" id="join_date" name="join_date" placeholder="YYYY-MM-DD" required autocomplete="off" dir="ltr" lang="en">
                </div>
                <div class="form-group">
                    <label for="basic_salary">Basic Salary</label>
                    <input type="text" class="form-control hr-number-en" id="basic_salary" name="basic_salary" inputmode="decimal" dir="ltr" lang="en" placeholder="0.00" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="country">Country *</label>
                    <select class="form-select" id="country" name="country" data-action="load-cities" required dir="ltr" lang="en">
                        <option value="">Select Country</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="city">City *</label>
                    <select class="form-select" id="city" name="city" required dir="ltr" lang="en">
                        <option value="">Select Country First</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select class="form-select" id="status" name="status" dir="ltr" lang="en">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Terminated">Terminated</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="type">Employment Type</label>
                    <select class="form-select" id="type" name="type" dir="ltr" lang="en">
                        <option value="Full-time">Full-time</option>
                        <option value="Part-time">Part-time</option>
                        <option value="Contract">Contract</option>
                    </select>
                </div>
                <div class="form-group form-group-full">
                    <label for="address">Address *</label>
                    <textarea class="form-control" id="address" name="address" rows="2" required dir="ltr" lang="en"></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save
                </button>
                <button type="button" class="btn btn-secondary" data-hr-action="closeModal">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    `;
}

// Create pagination component (default 5 rows per page for all tables)
const PAGINATION_OPTIONS = [5, 10, 25, 50, 100];
function createPagination(pagination, module) {
    const { total, page, limit, pages } = pagination;
    
    const numPage = parseInt(page) || 1;
    let numLimit = parseInt(limit) || 5;
    if (!PAGINATION_OPTIONS.includes(numLimit)) numLimit = 5;
    const numPages = parseInt(pages) || 1;
    
    
    const startEntry = total === 0 ? 0 : ((numPage - 1) * numLimit) + 1;
    const endEntry = Math.min(numPage * numLimit, total);
    
    let pageButtons = '';
    const maxVisiblePages = 5;
    let startPage = Math.max(1, numPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(numPages, startPage + maxVisiblePages - 1);
    
    if (endPage - startPage + 1 < maxVisiblePages) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    
    const firstDisabled = numPage <= 1 ? 'disabled' : '';
    const prevDisabled = numPage <= 1 ? 'disabled' : '';
    pageButtons += `<button class="hr-pagination-btn" data-hr-action="pagination" data-hr-module="${module}" data-hr-page="1" data-hr-limit="${numLimit}" title="First Page" ${firstDisabled}>‹‹</button>`;
    pageButtons += `<button class="hr-pagination-btn" data-hr-action="pagination" data-hr-module="${module}" data-hr-page="${numPage - 1}" data-hr-limit="${numLimit}" title="Previous Page" ${prevDisabled}>‹</button>`;
    
    if (numPages <= 5) {
        for (let i = 1; i <= numPages; i++) {
            const isActive = i === numPage ? 'active' : '';
            pageButtons += `<button class="hr-pagination-btn ${isActive}" data-hr-action="pagination" data-hr-module="${module}" data-hr-page="${i}" data-hr-limit="${numLimit}">${i}</button>`;
        }
        if (numPages < 10) {
            pageButtons += `<span class="hr-pagination-ellipsis">...</span>`;
        }
    } else {
        if (numPage <= 3) {
            for (let i = 1; i <= Math.min(5, numPages); i++) {
                const isActive = i === numPage ? 'active' : '';
                pageButtons += `<button class="hr-pagination-btn ${isActive}" data-hr-action="pagination" data-hr-module="${module}" data-hr-page="${i}" data-hr-limit="${numLimit}">${i}</button>`;
            }
            if (numPages > 5) {
                pageButtons += `<span class="hr-pagination-ellipsis">...</span>`;
            }
        } else if (numPage >= numPages - 2) {
            if (numPages > 5) {
                pageButtons += `<button class="hr-pagination-btn" data-hr-action="pagination" data-hr-module="${module}" data-hr-page="1" data-hr-limit="${numLimit}">1</button>`;
                pageButtons += `<span class="hr-pagination-ellipsis">...</span>`;
            }
            for (let i = Math.max(1, numPages - 4); i <= numPages; i++) {
                const isActive = i === numPage ? 'active' : '';
                pageButtons += `<button class="hr-pagination-btn ${isActive}" data-hr-action="pagination" data-hr-module="${module}" data-hr-page="${i}" data-hr-limit="${numLimit}">${i}</button>`;
            }
        } else {
            pageButtons += `<button class="hr-pagination-btn" data-hr-action="pagination" data-hr-module="${module}" data-hr-page="1" data-hr-limit="${numLimit}">1</button>`;
            pageButtons += `<span class="hr-pagination-ellipsis">...</span>`;
            for (let i = numPage - 1; i <= numPage + 1; i++) {
                const isActive = i === numPage ? 'active' : '';
                pageButtons += `<button class="hr-pagination-btn ${isActive}" data-hr-action="pagination" data-hr-module="${module}" data-hr-page="${i}" data-hr-limit="${numLimit}">${i}</button>`;
            }
            pageButtons += `<span class="hr-pagination-ellipsis">...</span>`;
            pageButtons += `<button class="hr-pagination-btn" data-hr-action="pagination" data-hr-module="${module}" data-hr-page="${numPages}" data-hr-limit="${numLimit}">${numPages}</button>`;
        }
    }
    
    const nextDisabled = numPage >= numPages ? 'disabled' : '';
    const lastDisabled = numPage >= numPages ? 'disabled' : '';
    pageButtons += `<button class="hr-pagination-btn" data-hr-action="pagination" data-hr-module="${module}" data-hr-page="${numPage + 1}" data-hr-limit="${numLimit}" title="Next Page" ${nextDisabled}>›</button>`;
    pageButtons += `<button class="hr-pagination-btn" data-hr-action="pagination" data-hr-module="${module}" data-hr-page="${numPages}" data-hr-limit="${numLimit}" title="Last Page" ${lastDisabled}>››</button>`;
    
    
    return `
        <div class="hr-pagination-container">
            <div class="hr-pagination-info">
                <span>Showing ${startEntry} to ${endEntry} of ${total} entries</span>
            </div>
            <div class="hr-pagination-controls">
                <select class="hr-pagination-select" data-hr-action="paginationSelect" data-hr-module="${module}" title="Rows per page">
                    ${PAGINATION_OPTIONS.map(n => `<option value="${n}" ${numLimit === n ? 'selected' : ''}>${n}</option>`).join('')}
                </select>
                <span>entries per page</span>
                <div class="hr-pagination-buttons">
                    ${pageButtons}
                </div>
            </div>
        </div>
    `;
}

// Create bulk action buttons for employees
function createBulkActionsButtonsEmployees(searchVal = '') {
    return `
        <div class="bulk-actions-container mb-3 d-flex align-items-center flex-wrap gap-2 justify-content-between">
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-sm btn-success" data-hr-action="bulkSetEmployeeStatus" data-hr-status="active">
                    <i class="fas fa-check"></i> Set Active
                </button>
                <button class="btn btn-sm btn-warning" data-hr-action="bulkSetEmployeeStatus" data-hr-status="inactive">
                    <i class="fas fa-ban"></i> Set Inactive
                </button>
                <button class="btn btn-sm btn-danger" data-hr-action="bulkDeleteEmployees">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>
            </div>
            <div class="input-group input-group-sm hr-search-input-group">
                <input type="text" class="form-control" placeholder="Search..." value="${(searchVal + '').replace(/"/g, '&quot;')}" data-hr-action="employeesSearchInput" dir="ltr" lang="en">
                <button type="button" class="btn btn-outline-secondary" data-hr-action="employeesSearchBtn" title="Search"><i class="fas fa-search"></i></button>
            </div>
        </div>
    `;
}

// Create compact table
function createEmployeeTable(employees) {
    return `
        <div class="hr-table-container">
            <table class="hr-table" dir="ltr" lang="en">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th class="checkbox-column">
                            <input type="checkbox" id="selectAllEmployees" data-hr-action="toggleAllEmployees">
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${employees.map(emp => `
                        <tr>
                            <td class="employee-id"><strong>${emp.employee_id || 'N/A'}</strong></td>
                            <td class="employee-name">${emp.name}</td>
                            <td class="employee-email">${emp.email}</td>
                            <td class="employee-department">${emp.department}</td>
                            <td class="status-field"><span class="status-badge ${(emp.status || '').toLowerCase()}">${emp.status || 'N/A'}</span></td>
                            <td class="checkbox-column">
                                <input type="checkbox" class="employee-checkbox" value="${emp.id}">
                            </td>
                            <td class="actions">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-primary" data-hr-action="viewEmployee" data-hr-id="${emp.id}" title="View Employee">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning" data-hr-action="editEmployee" data-hr-id="${emp.id}" data-permission="edit_employee" title="Edit Employee">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" data-hr-action="deleteEmployee" data-hr-id="${emp.id}" data-permission="delete_employee" title="Delete Employee">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

// Create attendance form
function createAttendanceForm() {
    return `
        <form id="attendanceForm" class="hr-form" dir="ltr" lang="en">
            <div class="form-content">
                <div class="form-group">
                    <label for="employee_id">Employee *</label>
                    <select class="form-select" id="employee_id" name="employee_id" required dir="ltr" lang="en">
                        <option value="">Select Employee</option>
                        <!-- Employee options will be loaded dynamically -->
                    </select>
                </div>
                <div class="form-group">
                    <label for="date">Date *</label>
                    <input type="text" class="form-control date-input" id="date" name="date" placeholder="YYYY-MM-DD" required autocomplete="off" dir="ltr" lang="en">
                </div>
                <div class="form-group">
                    <label for="check_in_time">Check In Time</label>
                    <div class="d-flex gap-2 align-items-center">
                        <select class="form-select hr-time-select" id="check_in_hour" dir="ltr" lang="en">
                            <option value="">Hour</option>
                        </select>
                        <span>:</span>
                        <select class="form-select hr-time-select" id="check_in_minute" dir="ltr" lang="en">
                            <option value="">Min</option>
                        </select>
                        <input type="hidden" id="check_in_time" name="check_in_time">
                    </div>
                </div>
                <div class="form-group">
                    <label for="check_out_time">Check Out Time</label>
                    <div class="d-flex gap-2 align-items-center">
                        <select class="form-select hr-time-select" id="check_out_hour" dir="ltr" lang="en">
                            <option value="">Hour</option>
                        </select>
                        <span>:</span>
                        <select class="form-select hr-time-select" id="check_out_minute" dir="ltr" lang="en">
                            <option value="">Min</option>
                        </select>
                        <input type="hidden" id="check_out_time" name="check_out_time">
                    </div>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select class="form-select" id="status" name="status" dir="ltr" lang="en">
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="late">Late</option>
                        <option value="leave">Leave</option>
                        <option value="half_day">Half Day</option>
                    </select>
                </div>
                <div class="form-group form-group-full">
                    <label for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2" dir="ltr" lang="en"></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save
                </button>
                <button type="button" class="btn btn-secondary" data-hr-action="closeModal">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    `;
}

// Create advances form
function createAdvancesForm() {
    return `
        <form id="advancesForm" class="hr-form" dir="ltr" lang="en">
            <div class="form-content">
                <div class="form-group">
                    <label for="employee_id">Employee *</label>
                    <select class="form-select" id="employee_id" name="employee_id" required dir="ltr" lang="en">
                        <option value="">Select Employee</option>
                        <!-- Employee options will be loaded dynamically -->
                    </select>
                </div>
                <div class="form-group">
                    <label for="request_date">Request Date *</label>
                    <input type="text" class="form-control date-input" id="request_date" name="request_date" placeholder="YYYY-MM-DD" required autocomplete="off" dir="ltr" lang="en">
                </div>
                <div class="form-group">
                    <label for="amount">Amount *</label>
                    <input type="text" class="form-control hr-number-en" id="amount" name="amount" inputmode="decimal" dir="ltr" lang="en" placeholder="0.00" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="repayment_date">Repayment Date *</label>
                    <input type="text" class="form-control date-input" id="repayment_date" name="repayment_date" placeholder="YYYY-MM-DD" required autocomplete="off" dir="ltr" lang="en">
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select class="form-select" id="status" name="status" dir="ltr" lang="en">
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="paid">Paid</option>
                    </select>
                </div>
                <div class="form-group form-group-full">
                    <label for="purpose">Purpose *</label>
                    <textarea class="form-control" id="purpose" name="purpose" rows="3" required dir="ltr" lang="en"></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save
                </button>
                <button type="button" class="btn btn-secondary" data-hr-action="closeModal">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    `;
}

// Create payroll form
function createPayrollForm() {
    return `
        <form id="payrollForm" class="hr-form" dir="ltr" lang="en">
            <div class="form-content">
                <div class="form-group">
                    <label for="employee_id">Employee *</label>
                    <select class="form-select" id="employee_id" name="employee_id" required dir="ltr" lang="en">
                        <option value="">Select Employee</option>
                        <!-- Employee options will be loaded dynamically -->
                    </select>
                </div>
                <div class="form-group">
                    <label for="currency">Currency *</label>
                    <select class="form-select" id="currency" name="currency" required dir="ltr" lang="en">
                        <option value="">Loading currencies...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="salary_month">Salary Month *</label>
                    <select class="form-select" id="salary_month" name="salary_month" required dir="ltr" lang="en">
                        <option value="">Select Month</option>
                        <option value="2025-01">January 2025</option>
                        <option value="2025-02">February 2025</option>
                        <option value="2025-03">March 2025</option>
                        <option value="2025-04">April 2025</option>
                        <option value="2025-05">May 2025</option>
                        <option value="2025-06">June 2025</option>
                        <option value="2025-07">July 2025</option>
                        <option value="2025-08">August 2025</option>
                        <option value="2025-09">September 2025</option>
                        <option value="2025-10">October 2025</option>
                        <option value="2025-11">November 2025</option>
                        <option value="2025-12">December 2025</option>
                        <option value="2026-01">January 2026</option>
                        <option value="2026-02">February 2026</option>
                        <option value="2026-03">March 2026</option>
                        <option value="2026-04">April 2026</option>
                        <option value="2026-05">May 2026</option>
                        <option value="2026-06">June 2026</option>
                        <option value="2026-07">July 2026</option>
                        <option value="2026-08">August 2026</option>
                        <option value="2026-09">September 2026</option>
                        <option value="2026-10">October 2026</option>
                        <option value="2026-11">November 2026</option>
                        <option value="2026-12">December 2026</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="working_days">Working Days *</label>
                    <select class="form-select" id="working_days" name="working_days" required dir="ltr" lang="en">
                        <option value="">Select Days</option>
                        <option value="15">15 Days</option>
                        <option value="16">16 Days</option>
                        <option value="17">17 Days</option>
                        <option value="18">18 Days</option>
                        <option value="19">19 Days</option>
                        <option value="20">20 Days</option>
                        <option value="21">21 Days</option>
                        <option value="22">22 Days</option>
                        <option value="23">23 Days</option>
                        <option value="24">24 Days</option>
                        <option value="25">25 Days</option>
                        <option value="26">26 Days</option>
                        <option value="27">27 Days</option>
                        <option value="28">28 Days</option>
                        <option value="29">29 Days</option>
                        <option value="30">30 Days</option>
                        <option value="31">31 Days</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="basic_salary">Basic Salary *</label>
                    <select class="form-select" id="basic_salary" name="basic_salary" required dir="ltr" lang="en">
                        <option value="">Select Basic Salary</option>
                        <option value="5000">5,000</option>
                        <option value="10000">10,000</option>
                        <option value="15000">15,000</option>
                        <option value="20000">20,000</option>
                        <option value="25000">25,000</option>
                        <option value="30000">30,000</option>
                        <option value="35000">35,000</option>
                        <option value="40000">40,000</option>
                        <option value="45000">45,000</option>
                        <option value="50000">50,000</option>
                        <option value="60000">60,000</option>
                        <option value="70000">70,000</option>
                        <option value="80000">80,000</option>
                        <option value="90000">90,000</option>
                        <option value="100000">100,000</option>
                        <option value="custom">Custom Amount</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="housing_allowance">Housing Allowance</label>
                    <select class="form-select" id="housing_allowance" name="housing_allowance" dir="ltr" lang="en">
                        <option value="">Select Housing Allowance</option>
                        <option value="0">No Housing Allowance</option>
                        <option value="1000">1,000</option>
                        <option value="2000">2,000</option>
                        <option value="3000">3,000</option>
                        <option value="4000">4,000</option>
                        <option value="5000">5,000</option>
                        <option value="6000">6,000</option>
                        <option value="7000">7,000</option>
                        <option value="8000">8,000</option>
                        <option value="9000">9,000</option>
                        <option value="10000">10,000</option>
                        <option value="12000">12,000</option>
                        <option value="15000">15,000</option>
                        <option value="20000">20,000</option>
                        <option value="custom">Custom Amount</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="transportation">Transportation</label>
                    <select class="form-select" id="transportation" name="transportation" dir="ltr" lang="en">
                        <option value="">Select Transportation</option>
                        <option value="0">No Transportation</option>
                        <option value="500">500</option>
                        <option value="1000">1,000</option>
                        <option value="1500">1,500</option>
                        <option value="2000">2,000</option>
                        <option value="2500">2,500</option>
                        <option value="3000">3,000</option>
                        <option value="4000">4,000</option>
                        <option value="5000">5,000</option>
                        <option value="custom">Custom Amount</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="overtime_hours">Overtime Hours</label>
                    <select class="form-select" id="overtime_hours" name="overtime_hours" dir="ltr" lang="en">
                        <option value="">Select Overtime Hours</option>
                        <option value="0">0 Hours</option>
                        <option value="5">5 Hours</option>
                        <option value="10">10 Hours</option>
                        <option value="15">15 Hours</option>
                        <option value="20">20 Hours</option>
                        <option value="30">30 Hours</option>
                        <option value="40">40 Hours</option>
                        <option value="50">50 Hours</option>
                        <option value="custom">Custom Hours</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="overtime_rate">Overtime Rate</label>
                    <select class="form-select" id="overtime_rate" name="overtime_rate" dir="ltr" lang="en">
                        <option value="">Select Overtime Rate</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="150">150</option>
                        <option value="200">200</option>
                        <option value="250">250</option>
                        <option value="300">300</option>
                        <option value="400">400</option>
                        <option value="500">500</option>
                        <option value="custom">Custom Rate</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="bonus">Bonus</label>
                    <select class="form-select" id="bonus" name="bonus" dir="ltr" lang="en">
                        <option value="">Select Bonus</option>
                        <option value="0">No Bonus</option>
                        <option value="500">500</option>
                        <option value="1000">1,000</option>
                        <option value="2000">2,000</option>
                        <option value="3000">3,000</option>
                        <option value="5000">5,000</option>
                        <option value="10000">10,000</option>
                        <option value="20000">20,000</option>
                        <option value="custom">Custom Amount</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="insurance">Insurance</label>
                    <select class="form-select" id="insurance" name="insurance" dir="ltr" lang="en">
                        <option value="">Select Insurance</option>
                        <option value="0">No Insurance</option>
                        <option value="100">100</option>
                        <option value="200">200</option>
                        <option value="300">300</option>
                        <option value="400">400</option>
                        <option value="500">500</option>
                        <option value="750">750</option>
                        <option value="1000">1,000</option>
                        <option value="1500">1,500</option>
                        <option value="2000">2,000</option>
                        <option value="custom">Custom Amount</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tax_percentage">Tax Percentage</label>
                    <select class="form-select" id="tax_percentage" name="tax_percentage" dir="ltr" lang="en">
                        <option value="">Select Tax Percentage</option>
                        <option value="0">0%</option>
                        <option value="5">5%</option>
                        <option value="10">10%</option>
                        <option value="15">15%</option>
                        <option value="20">20%</option>
                        <option value="25">25%</option>
                        <option value="30">30%</option>
                        <option value="custom">Custom Percentage</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="other_deductions">Other Deductions</label>
                    <select class="form-select" id="other_deductions" name="other_deductions" dir="ltr" lang="en">
                        <option value="">Select Other Deductions</option>
                        <option value="0">No Deductions</option>
                        <option value="100">100</option>
                        <option value="200">200</option>
                        <option value="300">300</option>
                        <option value="500">500</option>
                        <option value="1000">1,000</option>
                        <option value="2000">2,000</option>
                        <option value="5000">5,000</option>
                        <option value="custom">Custom Amount</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save
                </button>
                <button type="button" class="btn btn-secondary" data-hr-action="closeModal">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    `;
}

// Create documents form
function createDocumentsForm() {
    return `
        <form id="documentsForm" class="hr-form" dir="ltr" lang="en">
            <div class="form-content">
                <div class="form-group">
                    <label for="employee_id">Employee *</label>
                    <select class="form-select" id="employee_id" name="employee_id" required dir="ltr" lang="en">
                        <option value="">Select Employee</option>
                        <!-- Employee options will be loaded dynamically -->
                    </select>
                </div>
                <div class="form-group">
                    <label for="title">Document Title *</label>
                    <input type="text" class="form-control" id="title" name="title" required dir="ltr" lang="en">
                </div>
                <div class="form-group">
                    <label for="document_type">Document Type *</label>
                    <select class="form-select" id="document_type" name="document_type" required dir="ltr" lang="en">
                        <option value="">Select Type</option>
                        <option value="Contract">Contract</option>
                        <option value="Policy">Policy</option>
                        <option value="Report">Report</option>
                        <option value="Certificate">Certificate</option>
                        <option value="Letter">Letter</option>
                        <option value="ID">ID</option>
                        <option value="Passport">Passport</option>
                        <option value="Visa">Visa</option>
                        <option value="Work Permit">Work Permit</option>
                        <option value="Medical Certificate">Medical Certificate</option>
                        <option value="Police Clearance">Police Clearance</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="department">Department *</label>
                    <select class="form-select" id="department" name="department" required dir="ltr" lang="en">
                        <option value="">Select Department</option>
                        <option value="HR Management">HR Management</option>
                        <option value="Recruitment">Recruitment</option>
                        <option value="Training">Training</option>
                        <option value="Employee Relations">Employee Relations</option>
                        <option value="Finance">Finance</option>
                        <option value="IT">IT</option>
                        <option value="Operations">Operations</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="issue_date">Issue Date *</label>
                    <input type="text" class="form-control date-input" id="issue_date" name="issue_date" placeholder="YYYY-MM-DD" required autocomplete="off" dir="ltr" lang="en">
                </div>
                <div class="form-group">
                    <label for="expiry_date">Expiry Date</label>
                    <input type="text" class="form-control date-input" id="expiry_date" name="expiry_date" placeholder="YYYY-MM-DD" autocomplete="off" dir="ltr" lang="en">
                </div>
                <div class="form-group">
                    <label for="document_number">Document Number *</label>
                    <input type="text" class="form-control" id="document_number" name="document_number" required dir="ltr" lang="en">
                </div>
                <div class="form-group">
                    <label for="file_upload">Upload File *</label>
                    <input type="file" class="form-control" id="file_upload" name="file_upload" required dir="ltr" lang="en">
                </div>
                <div class="form-group form-group-full">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3" dir="ltr" lang="en"></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save
                </button>
                <button type="button" class="btn btn-secondary" data-hr-action="closeModal">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    `;
}

// Create vehicles form
function createVehiclesForm() {
    return `
        <form id="vehiclesForm" class="hr-form" dir="ltr" lang="en">
            <div class="form-content">
                <div class="form-group">
                    <label for="vehicle_number">Vehicle Number *</label>
                    <input type="text" class="form-control" id="vehicle_number" name="vehicle_number" required dir="ltr" lang="en">
                </div>
                <div class="form-group">
                    <label for="vehicle_model">Vehicle Model *</label>
                    <input type="text" class="form-control" id="vehicle_model" name="vehicle_model" required dir="ltr" lang="en">
                </div>
                <div class="form-group">
                    <label for="driver_id">Driver</label>
                    <select class="form-select" id="driver_id" name="driver_id" dir="ltr" lang="en">
                        <option value="">Select Driver</option>
                        <!-- Driver options will be loaded dynamically -->
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select class="form-select" id="status" name="status" dir="ltr" lang="en">
                        <option value="available">Available</option>
                        <option value="inuse">In Use</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="registration_date">Registration Date</label>
                    <input type="text" class="form-control date-input" id="registration_date" name="registration_date" placeholder="YYYY-MM-DD" autocomplete="off" dir="ltr" lang="en">
                </div>
                <div class="form-group">
                    <label for="insurance_expiry">Insurance Expiry</label>
                    <input type="text" class="form-control date-input" id="insurance_expiry" name="insurance_expiry" placeholder="YYYY-MM-DD" autocomplete="off" dir="ltr" lang="en">
                </div>
                <div class="form-group">
                    <label for="maintenance_due_date">Maintenance Due Date</label>
                    <input type="text" class="form-control date-input" id="maintenance_due_date" name="maintenance_due_date" placeholder="YYYY-MM-DD" autocomplete="off" dir="ltr" lang="en">
                </div>
                <div class="form-group form-group-full">
                    <label for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3" dir="ltr" lang="en"></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save
                </button>
                <button type="button" class="btn btn-secondary" data-hr-action="closeModal">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    `;
}

// Override the existing loadEmployeesContent function
async function loadEmployeesContent(action, page = 1, limit = 5, status = '', search = '', department = '') {
    if (action === 'add') {
        return createEmployeeForm();
    } else {
        try {
            const timestamp = new Date().getTime();
            let apiUrl = `${hrFormApiUrl(`employees.php?action=list&page=${page}&limit=${limit}&_t=${timestamp}`)}${getHRControlSuffix()}`;
            if (status) apiUrl += `&status=${encodeURIComponent(status)}`;
            if (search) apiUrl += `&search=${encodeURIComponent(search)}`;
            if (department) apiUrl += `&department=${encodeURIComponent(department)}`;
            
            const response = await fetch(apiUrl, {
                method: 'GET',
                cache: 'no-cache',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const responseText = await response.text();
            const data = JSON.parse(responseText);
            
            
            if (data.success && data.data.length > 0) {
                const statusVal = status || '';
                const searchVal = search || '';
                const pagination = createPagination(data.pagination, 'employees');
                return `
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="mb-0">Employee Records (${data.pagination.total} employees)</h5>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <select class="form-select form-select-sm hr-filter-select" data-hr-action="employeesStatusFilter" dir="ltr" lang="en">
                                <option value="">All Status</option>
                                <option value="active" ${statusVal === 'active' ? 'selected' : ''}>Active</option>
                                <option value="inactive" ${statusVal === 'inactive' ? 'selected' : ''}>Inactive</option>
                            </select>
                            <button class="btn btn-primary btn-sm" data-hr-action="showForm" data-hr-module="employees" data-hr-form-action="add" data-permission="add_employee">
                                <i class="fas fa-plus"></i> Add Employee
                            </button>
                        </div>
                    </div>
                    ${createBulkActionsButtonsEmployees(searchVal)}
                    ${pagination}
                    ${createEmployeeTable(data.data)}
                    ${pagination}
                `;
            } else {
                const searchVal = search || '';
                return `
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="mb-0">Employee Records</h5>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <select class="form-select form-select-sm hr-filter-select" data-hr-action="employeesStatusFilter" dir="ltr" lang="en">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <button class="btn btn-primary btn-sm" data-hr-action="showForm" data-hr-module="employees" data-hr-form-action="add" data-permission="add_employee">
                                <i class="fas fa-plus"></i> Add Employee
                            </button>
                            <div class="input-group input-group-sm hr-search-input-group">
                                <input type="text" class="form-control" placeholder="Search..." value="${(searchVal + '').replace(/"/g, '&quot;')}" data-hr-action="employeesSearchInput" dir="ltr" lang="en">
                                <button type="button" class="btn btn-outline-secondary" data-hr-action="employeesSearchBtn" title="Search"><i class="fas fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="hr-message info">
                        <i class="fas fa-info-circle"></i>
                        No employees found. <a href="#" data-hr-action="showForm" data-hr-module="employees" data-hr-form-action="add">Add your first employee</a>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading employees:', error);
            return '<div class="hr-message error">Failed to load employees: ' + error.message + '</div>';
        }
    }
}

// Create bulk actions for attendance
function createBulkActionsButtonsAttendance(searchVal = '') {
    return `
        <div class="bulk-actions-container mb-3 d-flex align-items-center flex-wrap gap-2 justify-content-between">
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-sm btn-success" data-hr-action="bulkSetAttendanceStatus" data-hr-status="present"><i class="fas fa-check"></i> Set Present</button>
                <button class="btn btn-sm btn-warning" data-hr-action="bulkSetAttendanceStatus" data-hr-status="absent"><i class="fas fa-ban"></i> Set Absent</button>
                <button class="btn btn-sm btn-info" data-hr-action="bulkSetAttendanceStatus" data-hr-status="late"><i class="fas fa-clock"></i> Set Late</button>
                <button class="btn btn-sm btn-danger" data-hr-action="bulkDeleteAttendance"><i class="fas fa-trash"></i> Delete Selected</button>
            </div>
            <div class="input-group input-group-sm hr-search-input-group">
                <input type="text" class="form-control" placeholder="Search..." value="${(searchVal + '').replace(/"/g, '&quot;')}" data-hr-action="attendanceSearchInput" dir="ltr" lang="en">
                <button type="button" class="btn btn-outline-secondary" data-hr-action="attendanceSearchBtn" title="Search"><i class="fas fa-search"></i></button>
            </div>
        </div>
    `;
}

function createAttendanceTable(records) {
    return `
        <div class="hr-table-container">
            <table class="hr-table" dir="ltr" lang="en">
                <thead>
                    <tr>
                        <th>ID</th><th>Employee</th><th>Date</th><th>Check In</th><th>Check Out</th><th>Status</th><th>Notes</th>
                        <th class="checkbox-column"><input type="checkbox" id="selectAllAttendance" data-hr-action="toggleAllAttendance"></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${records.map(r => `
                        <tr>
                            <td class="employee-id"><strong>${r.record_id || 'N/A'}</strong></td>
                            <td>${r.employee_name || 'N/A'}</td>
                            <td>${r.date}</td>
                            <td>${(window.toWesternNumerals || (x=>x))(String(r.check_in_time || '').replace(/^-/, '')) || '-'}</td>
                            <td>${(window.toWesternNumerals || (x=>x))(String(r.check_out_time || '').replace(/^-/, '')) || '-'}</td>
                            <td><span class="status-badge ${(r.status || '').toLowerCase()}">${r.status || 'N/A'}</span></td>
                            <td>${r.notes || '-'}</td>
                            <td class="checkbox-column"><input type="checkbox" class="attendance-checkbox" value="${r.id}"></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-primary" data-hr-action="viewAttendance" data-hr-id="${r.id}" title="View"><i class="fas fa-eye"></i></button>
                                    <button class="btn btn-sm btn-warning" data-hr-action="editAttendance" data-hr-id="${r.id}" title="Edit"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-danger" data-hr-action="deleteAttendance" data-hr-id="${r.id}" title="Delete"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

// Load attendance content
async function loadAttendanceContent(action, page = 1, limit = 5, status = '', search = '') {
    if (action === 'add' || action === 'mark') {
        const form = createAttendanceForm();
        setTimeout(() => {
            if (typeof loadEmployeesForAttendance === 'function') {
                loadEmployeesForAttendance();
            }
            if (typeof initTimePickers === 'function') {
                setTimeout(initTimePickers, 200);
            }
        }, 100);
        return form;
    } else {
        try {
            let url = `${hrFormApiUrl(`attendance.php?action=list&page=${page}&limit=${limit}`)}${getHRControlSuffix()}`;
            if (status) url += `&status=${encodeURIComponent(status)}`;
            if (search) url += `&search=${encodeURIComponent(search)}`;
            const response = await fetch(url);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            const statusVal = status || '';
            const searchVal = search || '';
            if (data.success && data.data.length > 0) {
                return `
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="mb-0">Attendance Records (${data.pagination.total})</h5>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <select class="form-select form-select-sm hr-filter-select" data-hr-action="attendanceStatusFilter" dir="ltr" lang="en">
                                <option value="">All Status</option>
                                <option value="present" ${statusVal === 'present' ? 'selected' : ''}>Present</option>
                                <option value="absent" ${statusVal === 'absent' ? 'selected' : ''}>Absent</option>
                                <option value="late" ${statusVal === 'late' ? 'selected' : ''}>Late</option>
                                <option value="leave" ${statusVal === 'leave' ? 'selected' : ''}>Leave</option>
                                <option value="half_day" ${statusVal === 'half_day' ? 'selected' : ''}>Half Day</option>
                            </select>
                            <button class="btn btn-primary btn-sm" data-hr-action="showForm" data-hr-module="attendance" data-hr-form-action="add"><i class="fas fa-plus"></i> Mark Attendance</button>
                        </div>
                    </div>
                    ${createBulkActionsButtonsAttendance(searchVal)}
                    ${createPagination(data.pagination, 'attendance')}
                    ${createAttendanceTable(data.data)}
                    ${createPagination(data.pagination, 'attendance')}
                `;
            } else {
                return `
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="mb-0">Attendance Records</h5>
                        <div class="d-flex align-items-center gap-2">
                            <select class="form-select form-select-sm hr-filter-select" data-hr-action="attendanceStatusFilter" dir="ltr" lang="en">
                                <option value="">All Status</option>
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                                <option value="late">Late</option>
                                <option value="leave">Leave</option>
                                <option value="half_day">Half Day</option>
                            </select>
                            <button class="btn btn-primary btn-sm" data-hr-action="showForm" data-hr-module="attendance" data-hr-form-action="add"><i class="fas fa-plus"></i> Mark Attendance</button>
                            <div class="input-group input-group-sm hr-search-input-group">
                                <input type="text" class="form-control" placeholder="Search..." value="${(searchVal + '').replace(/"/g, '&quot;')}" data-hr-action="attendanceSearchInput" dir="ltr" lang="en">
                                <button type="button" class="btn btn-outline-secondary" data-hr-action="attendanceSearchBtn" title="Search"><i class="fas fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="hr-message info">
                        <i class="fas fa-info-circle"></i> No attendance records found. <a href="#" data-hr-action="showForm" data-hr-module="attendance" data-hr-form-action="add">Mark first attendance</a>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading attendance:', error);
            return `<div class="hr-message error"><i class="fas fa-exclamation-circle"></i> Failed to load attendance records</div>`;
        }
    }
}

// Load employees for advance form dropdown
async function loadEmployeesForAdvance() {
    try {
        const response = await fetch(`${hrFormApiUrl('employees.php?action=list&limit=100')}${getHRControlSuffix()}`);
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('employee_id');
            if (select) {
                select.innerHTML = '<option value="">Select Employee</option>';
                data.data.forEach(employee => {
                    const option = document.createElement('option');
                    option.value = employee.id;
                    option.textContent = `${employee.name} (${employee.employee_id})`;
                    select.appendChild(option);
                });
            }
        }
    } catch (error) {
        console.error('Error loading employees for advance form:', error);
    }
}

// Create bulk actions for advances
function createBulkActionsButtonsAdvances(searchVal = '') {
    return `
        <div class="bulk-actions-container mb-3 d-flex align-items-center flex-wrap gap-2 justify-content-between">
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-sm btn-success" data-hr-action="bulkSetAdvanceStatus" data-hr-status="approved"><i class="fas fa-check"></i> Approve</button>
                <button class="btn btn-sm btn-warning" data-hr-action="bulkSetAdvanceStatus" data-hr-status="rejected"><i class="fas fa-ban"></i> Reject</button>
                <button class="btn btn-sm btn-danger" data-hr-action="bulkDeleteAdvances"><i class="fas fa-trash"></i> Delete Selected</button>
            </div>
            <div class="input-group input-group-sm hr-search-input-group">
                <input type="text" class="form-control" placeholder="Search..." value="${(searchVal + '').replace(/"/g, '&quot;')}" data-hr-action="advancesSearchInput" dir="ltr" lang="en">
                <button type="button" class="btn btn-outline-secondary" data-hr-action="advancesSearchBtn" title="Search"><i class="fas fa-search"></i></button>
            </div>
        </div>
    `;
}

// Create advances table
function createAdvancesTable(advances) {
    return `
        <div class="hr-table-container">
            <table class="hr-table" dir="ltr" lang="en">
                <thead>
                    <tr>
                        <th>ID</th><th>Employee</th><th>Request Date</th><th>Amount</th><th>Repayment Date</th><th>Purpose</th><th>Status</th>
                        <th class="checkbox-column"><input type="checkbox" id="selectAllAdvances" data-hr-action="toggleAllAdvances"></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${advances.map(advance => `
                        <tr>
                            <td class="employee-id"><strong>${advance.record_id || 'N/A'}</strong></td>
                            <td class="advance-employee">${advance.employee_name}</td>
                            <td class="date-field">${advance.request_date}</td>
                            <td class="advance-amount">${advance.amount}</td>
                            <td class="date-field">${advance.repayment_date}</td>
                            <td class="advance-purpose">${advance.purpose || '-'}</td>
                            <td class="advance-status"><span class="status-badge ${(advance.status || '').toLowerCase()}">${advance.status || 'N/A'}</span></td>
                            <td class="checkbox-column"><input type="checkbox" class="advance-checkbox" value="${advance.id}"></td>
                            <td class="actions">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-primary" data-hr-action="viewAdvance" data-hr-id="${advance.id}" title="View"><i class="fas fa-eye"></i></button>
                                    <button class="btn btn-sm btn-warning" data-hr-action="editAdvance" data-hr-id="${advance.id}" title="Edit"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-danger" data-hr-action="deleteAdvance" data-hr-id="${advance.id}" title="Delete"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

// Load advances content
async function loadAdvancesContent(action, page = 1, limit = 5, status = '', search = '') {
    if (action === 'add') {
        const form = createAdvancesForm();
        // Load employees after form is created
        setTimeout(loadEmployeesForAdvance, 100);
        return form;
    } else {
        try {
            let url = `${hrFormApiUrl(`advances.php?action=list&page=${page}&limit=${limit}`)}${getHRControlSuffix()}`;
            if (status) url += `&status=${encodeURIComponent(status)}`;
            if (search) url += `&search=${encodeURIComponent(search)}`;
            const response = await fetch(url);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            const statusVal = status || '';
            const searchVal = search || '';
            if (data.success && data.data.length > 0) {
                return `
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="mb-0">Employee Advances (${data.pagination.total} advances)</h5>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <select class="form-select form-select-sm hr-filter-select" data-hr-action="advancesStatusFilter" dir="ltr" lang="en">
                                <option value="">All Status</option>
                                <option value="pending" ${statusVal === 'pending' ? 'selected' : ''}>Pending</option>
                                <option value="approved" ${statusVal === 'approved' ? 'selected' : ''}>Approved</option>
                                <option value="rejected" ${statusVal === 'rejected' ? 'selected' : ''}>Rejected</option>
                                <option value="paid" ${statusVal === 'paid' ? 'selected' : ''}>Paid</option>
                            </select>
                            <button class="btn btn-primary btn-sm" data-hr-action="showForm" data-hr-module="advances" data-hr-form-action="add"><i class="fas fa-plus"></i> New Advance</button>
                        </div>
                    </div>
                    ${createBulkActionsButtonsAdvances(searchVal)}
                    ${createPagination(data.pagination, 'advances')}
                    ${createAdvancesTable(data.data)}
                    ${createPagination(data.pagination, 'advances')}
                `;
            } else {
                return `
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="mb-0">Employee Advances</h5>
                        <div class="d-flex align-items-center gap-2">
                            <select class="form-select form-select-sm hr-filter-select" data-hr-action="advancesStatusFilter" dir="ltr" lang="en">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="paid">Paid</option>
                            </select>
                            <button class="btn btn-primary btn-sm" data-hr-action="showForm" data-hr-module="advances" data-hr-form-action="add"><i class="fas fa-plus"></i> New Advance</button>
                            <div class="input-group input-group-sm hr-search-input-group">
                                <input type="text" class="form-control" placeholder="Search..." value="${(searchVal + '').replace(/"/g, '&quot;')}" data-hr-action="advancesSearchInput" dir="ltr" lang="en">
                                <button type="button" class="btn btn-outline-secondary" data-hr-action="advancesSearchBtn" title="Search"><i class="fas fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="hr-message info">
                        <i class="fas fa-info-circle"></i> No advance requests found. <a href="#" data-hr-action="showForm" data-hr-module="advances" data-hr-form-action="add">Submit first advance request</a>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading advances:', error);
            return `<div class="hr-message error"><i class="fas fa-exclamation-triangle"></i> Failed to load advances: ${error.message}</div>`;
        }
    }
}

// Create bulk actions for payroll with search
function createBulkActionsButtonsPayroll(searchVal = '') {
    return `
        <div class="bulk-actions-container mb-3 d-flex align-items-center flex-wrap gap-2 justify-content-between">
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-sm btn-success" data-hr-action="bulkApprovePayroll"><i class="fas fa-check"></i> Mark as Processed</button>
                <button class="btn btn-sm btn-warning" data-hr-action="bulkRejectPayroll"><i class="fas fa-undo"></i> Reset to Pending</button>
                <button class="btn btn-sm btn-info" data-hr-action="bulkProcessPayroll"><i class="fas fa-dollar-sign"></i> Mark as Paid</button>
                <button class="btn btn-sm btn-danger" data-hr-action="bulkDeletePayroll"><i class="fas fa-trash"></i> Delete Selected</button>
            </div>
            <div class="input-group input-group-sm hr-search-input-group">
                <input type="text" class="form-control" placeholder="Search..." value="${(searchVal + '').replace(/"/g, '&quot;')}" data-hr-action="payrollSearchInput" dir="ltr" lang="en">
                <button type="button" class="btn btn-outline-secondary" data-hr-action="payrollSearchBtn" title="Search"><i class="fas fa-search"></i></button>
            </div>
        </div>
    `;
}

// Create payroll table
function createPayrollTable(salaries) {
    return `
        <div class="hr-table-container payroll-table-container">
            <table class="hr-table payroll-table" dir="ltr" lang="en">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Employee</th>
                        <th>Salary Month</th>
                        <th>Working Days</th>
                        <th>Basic Salary</th>
                        <th>Total Earnings</th>
                        <th>Total Deductions</th>
                        <th>Net Salary</th>
                        <th>Status</th>
                        <th class="checkbox-column"><input type="checkbox" id="selectAllPayroll" data-hr-action="toggleAllPayroll"></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${salaries.map(salary => `
                        <tr>
                            <td class="employee-id"><strong>${salary.record_id || 'N/A'}</strong></td>
                            <td class="payroll-employee">${salary.employee_name}</td>
                            <td class="payroll-month">${salary.salary_month}</td>
                            <td class="payroll-days">${salary.working_days}</td>
                            <td class="payroll-amount">${salary.basic_salary}</td>
                            <td class="payroll-amount">${salary.total_earnings}</td>
                            <td class="payroll-amount">${salary.total_deductions}</td>
                            <td class="payroll-amount"><strong>${salary.net_salary}</strong></td>
                            <td class="payroll-status"><span class="status-badge ${(salary.status || 'pending').toLowerCase()}">${salary.status || 'Pending'}</span></td>
                            <td class="checkbox-column"><input type="checkbox" class="payroll-checkbox" value="${salary.id}"></td>
                            <td class="actions">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-primary" data-hr-action="viewSalary" data-hr-id="${salary.id}" title="View"><i class="fas fa-eye"></i></button>
                                    <button class="btn btn-sm btn-warning" data-hr-action="editSalary" data-hr-id="${salary.id}" data-permission="edit_employee">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" data-hr-action="deleteSalary" data-hr-id="${salary.id}" data-permission="delete_employee">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

// Load payroll content
async function loadPayrollContent(action, page = 1, limit = 5, status = '', search = '') {
    if (action === 'add') {
        const form = createPayrollForm();
        // Load employees and currencies after form is created
        setTimeout(async () => {
            loadEmployeesForSelect(document.getElementById('employee_id'));
            // Populate currency dropdown from System Settings (only active currencies, auto-select if only one)
            if (window.currencyUtils) {
                const currencySelect = document.getElementById('currency');
                if (currencySelect) {
                    await window.currencyUtils.populateCurrencySelect(currencySelect); // No default currency - will auto-select if only one active
                }
            }
        }, 100);
        return form;
    } else {
        try {
            let url = `${hrFormApiUrl(`salaries.php?action=list&page=${page}&limit=${limit}&_t=${new Date().getTime()}`)}${getHRControlSuffix()}`;
            if (status) url += `&status=${encodeURIComponent(status)}`;
            if (search) url += `&search=${encodeURIComponent(search)}`;
            const response = await fetch(url, {
                method: 'GET',
                cache: 'no-cache',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache',
                    'Expires': '0'
                }
            });
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            const statusVal = status || '';
            const searchVal = search || '';
            const headerControls = `
                <select class="form-select form-select-sm hr-filter-select" data-hr-action="payrollStatusFilter" dir="ltr" lang="en">
                    <option value="">All Status</option>
                    <option value="pending" ${statusVal === 'pending' ? 'selected' : ''}>Pending</option>
                    <option value="processed" ${statusVal === 'processed' ? 'selected' : ''}>Processed</option>
                    <option value="paid" ${statusVal === 'paid' ? 'selected' : ''}>Paid</option>
                </select>
                <button class="btn btn-primary btn-sm" data-hr-action="showForm" data-hr-module="payroll" data-hr-form-action="add">
                    <i class="fas fa-plus"></i> Process Payroll
                </button>
            `;
            if (data.success && data.data.length > 0) {
                return `
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="mb-0">Payroll Management (${data.pagination.total} records)</h5>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            ${headerControls}
                        </div>
                    </div>
                    ${createBulkActionsButtonsPayroll(searchVal)}
                    ${createPagination(data.pagination, 'payroll')}
                    ${createPayrollTable(data.data)}
                    ${createPagination(data.pagination, 'payroll')}
                `;
            } else {
                return `
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="mb-0">Payroll Management</h5>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            ${headerControls}
                            <div class="input-group input-group-sm hr-search-input-group">
                                <input type="text" class="form-control" placeholder="Search..." value="${(searchVal + '').replace(/"/g, '&quot;')}" data-hr-action="payrollSearchInput" dir="ltr" lang="en">
                                <button type="button" class="btn btn-outline-secondary" data-hr-action="payrollSearchBtn" title="Search"><i class="fas fa-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="hr-message info">
                        <i class="fas fa-info-circle"></i>
                        No payroll records found. <a href="#" data-hr-action="showForm" data-hr-module="payroll" data-hr-form-action="add">Process first payroll</a>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading payroll:', error);
            return `
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>Payroll Management</h5>
                    <button class="btn btn-primary btn-sm" data-hr-action="showForm" data-hr-module="payroll" data-hr-form-action="add">
                        <i class="fas fa-plus"></i> Process Payroll
                    </button>
                </div>
                <div class="hr-message error">
                    <i class="fas fa-exclamation-triangle"></i>
                    Failed to load payroll records: ${error.message}
                </div>
            `;
        }
    }
}

// Create bulk action buttons for documents
function createBulkActionsButtons(module) {
    return `
        <div class="bulk-actions-container mb-3">
            <button class="btn btn-sm btn-success" data-hr-action="bulkActivateDocuments">
                <i class="fas fa-check"></i> Activate Selected
            </button>
            <button class="btn btn-sm btn-warning" data-hr-action="bulkDeactivateDocuments">
                <i class="fas fa-ban"></i> Deactivate Selected
            </button>
            <button class="btn btn-sm btn-info" data-hr-action="bulkArchiveDocuments">
                <i class="fas fa-archive"></i> Archive Selected
            </button>
            <button class="btn btn-sm btn-danger" data-hr-action="bulkDeleteDocuments">
                <i class="fas fa-trash"></i> Delete Selected
            </button>
        </div>
    `;
}

// Create documents table
function createDocumentsTable(documents) {
    return `
        <div class="hr-table-container">
            <table class="hr-table" dir="ltr" lang="en">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Employee</th>
                        <th>Document Title</th>
                        <th>Type</th>
                        <th>Department</th>
                        <th>Issue Date</th>
                        <th>Expiry Date</th>
                        <th>Document Number</th>
                        <th class="checkbox-column">
                            <input type="checkbox" id="selectAllDocuments" data-hr-action="toggleAllDocuments">
                        </th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${documents.map(doc => `
                        <tr>
                            <td class="employee-id"><strong>${doc.record_id || 'N/A'}</strong></td>
                            <td class="employee-name">${doc.employee_name || 'N/A'}</td>
                            <td class="document-title">${doc.title}</td>
                            <td class="document-type">${doc.document_type}</td>
                            <td class="department">${doc.department}</td>
                            <td class="date-field">${doc.issue_date}</td>
                            <td class="date-field">${doc.expiry_date || 'N/A'}</td>
                            <td class="document-number">${doc.document_number}</td>
                            <td class="checkbox-column">
                                <input type="checkbox" class="document-checkbox" value="${doc.id}">
                            </td>
                            <td class="status-field">
                                <span class="status-badge ${doc.status ? doc.status.toLowerCase() : 'active'}">
                                    ${doc.status || 'Active'}
                                </span>
                            </td>
                            <td class="actions">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-primary btn-sm" data-hr-action="viewDocument" data-hr-id="${doc.id}" title="View Document">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-warning btn-sm" data-hr-action="editDocument" data-hr-id="${doc.id}" title="Edit Document">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-success btn-sm" data-hr-action="downloadDocument" data-hr-id="${doc.id}" title="Download Document">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" data-hr-action="deleteDocument" data-hr-id="${doc.id}" title="Delete Document">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

// Load documents content
async function loadDocumentsContent(action, page = 1, limit = 5) {
    if (action === 'add') {
        const form = createDocumentsForm();
        // Load employees after form is created
        setTimeout(() => loadEmployeesForSelect(document.getElementById('employee_id')), 100);
        return form;
    } else {
        try {
            const timestamp = new Date().getTime();
            const response = await fetch(`${hrFormApiUrl(`documents.php?action=list&page=${page}&limit=${limit}&_t=${timestamp}`)}${getHRControlSuffix()}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            
            if (data.success && data.data.length > 0) {
                return `
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5>Document Management (${data.pagination.total} documents)</h5>
                        <button class="btn btn-primary btn-sm" data-hr-action="showForm" data-hr-module="documents" data-hr-form-action="add">
                            <i class="fas fa-plus"></i> Upload Document
                        </button>
                    </div>
                    ${createBulkActionsButtons('documents')}
                    ${createPagination(data.pagination, 'documents')}
                    ${createDocumentsTable(data.data)}
                    ${createPagination(data.pagination, 'documents')}
                `;
            } else {
                return `
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5>Document Management</h5>
                        <button class="btn btn-primary btn-sm" data-hr-action="showForm" data-hr-module="documents" data-hr-form-action="add">
                            <i class="fas fa-plus"></i> Upload Document
                        </button>
                    </div>
                    <div class="hr-message info">
                        <i class="fas fa-info-circle"></i>
                        No documents found. <a href="#" data-hr-action="showForm" data-hr-module="documents" data-hr-form-action="add">Upload first document</a>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading documents:', error);
            return `
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>Document Management</h5>
                    <button class="btn btn-primary btn-sm" data-hr-action="showForm" data-hr-module="documents" data-hr-form-action="add">
                        <i class="fas fa-plus"></i> Upload Document
                    </button>
                </div>
                <div class="hr-message error">
                    <i class="fas fa-exclamation-triangle"></i>
                    Failed to load documents: ${error.message}
                </div>
            `;
        }
    }
}

// Create bulk action buttons for vehicles
function createBulkActionsButtonsVehicles(searchVal = '') {
    return `
        <div class="bulk-actions-container mb-3 d-flex align-items-center flex-wrap gap-2 justify-content-between">
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-sm btn-success" data-hr-action="bulkSetVehicleStatus" data-hr-status="available">
                    <i class="fas fa-check"></i> Set Available
                </button>
                <button class="btn btn-sm btn-warning" data-hr-action="bulkSetVehicleStatus" data-hr-status="inuse">
                    <i class="fas fa-car"></i> Set In Use
                </button>
                <button class="btn btn-sm btn-info" data-hr-action="bulkSetVehicleStatus" data-hr-status="maintenance">
                    <i class="fas fa-wrench"></i> Set Maintenance
                </button>
                <button class="btn btn-sm btn-danger" data-hr-action="bulkDeleteVehicles">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>
            </div>
            <div class="input-group input-group-sm hr-search-input-group">
                <input type="text" class="form-control" placeholder="Search..." value="${(searchVal + '').replace(/"/g, '&quot;')}" data-hr-action="vehiclesSearchInput" dir="ltr" lang="en">
                <button type="button" class="btn btn-outline-secondary" data-hr-action="vehiclesSearchBtn" title="Search"><i class="fas fa-search"></i></button>
            </div>
        </div>
    `;
}

// Create vehicles table
function createVehiclesTable(vehicles) {
    return `
        <div class="hr-table-container">
            <table class="hr-table" dir="ltr" lang="en">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Vehicle Number</th>
                        <th>Vehicle Model</th>
                        <th>Driver</th>
                        <th>Status</th>
                        <th>Registration Date</th>
                        <th>Insurance Expiry</th>
                        <th>Maintenance Due</th>
                        <th class="checkbox-column">
                            <input type="checkbox" id="selectAllVehicles" data-hr-action="toggleAllVehicles">
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${vehicles.map(vehicle => `
                        <tr>
                            <td class="employee-id"><strong>${vehicle.record_id || 'N/A'}</strong></td>
                            <td class="vehicle-number">${vehicle.vehicle_number}</td>
                            <td class="vehicle-model">${vehicle.vehicle_model}</td>
                            <td class="vehicle-driver">${vehicle.driver_name || 'N/A'}</td>
                            <td class="vehicle-status"><span class="status-badge ${(vehicle.status || '').toLowerCase()}">${vehicle.status || 'N/A'}</span></td>
                            <td class="date-field">${vehicle.registration_date || 'N/A'}</td>
                            <td class="date-field">${vehicle.insurance_expiry || 'N/A'}</td>
                            <td class="date-field">${vehicle.maintenance_due_date || 'N/A'}</td>
                            <td class="checkbox-column">
                                <input type="checkbox" class="vehicle-checkbox" value="${vehicle.id}">
                            </td>
                            <td class="actions">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-primary" data-hr-action="viewVehicle" data-hr-id="${vehicle.id}" title="View Vehicle">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning" data-hr-action="editVehicle" data-hr-id="${vehicle.id}" data-permission="edit_employee" title="Edit Vehicle">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" data-hr-action="deleteVehicle" data-hr-id="${vehicle.id}" data-permission="delete_employee" title="Delete Vehicle">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

// Load vehicles content
async function loadVehiclesContent(action, page = 1, limit = 5, status = '', search = '') {
    if (action === 'add') {
        const form = createVehiclesForm();
        // Load employees for driver selection after form is created
        setTimeout(() => loadEmployeesForSelect(document.getElementById('driver_id')), 100);
        return form;
    } else {
        try {
            let url = `${hrFormApiUrl(`cars.php?action=list&page=${page}&limit=${limit}`)}${getHRControlSuffix()}`;
            if (status) url += `&status=${encodeURIComponent(status)}`;
            if (search) url += `&search=${encodeURIComponent(search)}`;
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            
            if (data.success && data.data.length > 0) {
                const statusVal = status || '';
                const searchVal = search || '';
                return `
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="mb-0">Vehicle Management (${data.pagination.total} vehicles)</h5>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <select class="form-select form-select-sm hr-filter-select-wide" data-hr-action="vehiclesStatusFilter" data-hr-module="vehicles" dir="ltr" lang="en">
                                <option value="">All Status</option>
                                <option value="available" ${statusVal === 'available' ? 'selected' : ''}>Available</option>
                                <option value="inuse" ${statusVal === 'inuse' ? 'selected' : ''}>In Use</option>
                                <option value="maintenance" ${statusVal === 'maintenance' ? 'selected' : ''}>Maintenance</option>
                            </select>
                            <button class="btn btn-primary btn-sm" data-hr-action="showForm" data-hr-module="vehicles" data-hr-form-action="add">
                                <i class="fas fa-plus"></i> Add Vehicle
                            </button>
                        </div>
                    </div>
                    ${createBulkActionsButtonsVehicles(searchVal)}
                    ${createPagination(data.pagination, 'vehicles')}
                    ${createVehiclesTable(data.data)}
                    ${createPagination(data.pagination, 'vehicles')}
                `;
            } else {
                const searchVal = search || '';
                return `
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="mb-0">Vehicle Management</h5>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <select class="form-select form-select-sm hr-filter-select-wide" data-hr-action="vehiclesStatusFilter" data-hr-module="vehicles" dir="ltr" lang="en">
                                <option value="">All Status</option>
                                <option value="available">Available</option>
                                <option value="inuse">In Use</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                            <button class="btn btn-primary btn-sm" data-hr-action="showForm" data-hr-module="vehicles" data-hr-form-action="add">
                                <i class="fas fa-plus"></i> Add Vehicle
                            </button>
                            <div class="input-group input-group-sm hr-search-input-group-small">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" placeholder="Search..." value="${(searchVal + '').replace(/"/g, '&quot;')}" data-hr-action="vehiclesSearchInput" dir="ltr" lang="en">
                            </div>
                        </div>
                    </div>
                    <div class="hr-message info">
                        <i class="fas fa-info-circle"></i>
                        No vehicles found. <a href="#" data-hr-action="showForm" data-hr-module="vehicles" data-hr-form-action="add">Add first vehicle</a>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading vehicles:', error);
            return `
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>Vehicle Management</h5>
                    <button class="btn btn-primary btn-sm" data-hr-action="showForm" data-hr-module="vehicles" data-hr-form-action="add">
                        <i class="fas fa-plus"></i> Add Vehicle
                    </button>
                </div>
                <div class="hr-message error">
                    <i class="fas fa-exclamation-triangle"></i>
                    Failed to load vehicles: ${error.message}
                </div>
            `;
        }
    }
}
