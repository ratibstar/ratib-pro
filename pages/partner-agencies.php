<?php
/**
 * Staff page: list and manage partner agencies.
 */
require_once '../includes/config.php';
require_once '../includes/permissions.php';

if (!function_exists('ratib_program_session_is_valid_user') || !ratib_program_session_is_valid_user()) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
if (!hasPermission('view_partner_agencies') && !hasPermission('view_workers')) {
    header('Location: ' . ratib_country_dashboard_url((int)($_SESSION['agency_id'] ?? 0)));
    exit;
}

$pageTitle = 'Partner Agencies';
$pageCss = [asset('css/partnerships.css') . '?v=' . time()];
$pageJs = [asset('js/partnerships/agencies.js') . '?v=' . time()];
include '../includes/header.php';
?>

<div class="main-content partnerships-page" lang="en" dir="ltr">
    <div class="partnerships-toolbar glass-card">
        <h2>🌍 Partner Agencies</h2>
        <div class="toolbar-actions">
            <input id="agencySearch" type="text" placeholder="Search agency/contact/email">
            <input id="agencyCountryFilter" type="text" placeholder="Filter country">
            <select id="agencyStatusFilter">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <select id="agencySort">
                <option value="name_asc">Name A-Z</option>
                <option value="name_desc">Name Z-A</option>
                <option value="workers_desc">Workers Sent High-Low</option>
                <option value="workers_asc">Workers Sent Low-High</option>
            </select>
            <select id="agencyPageSize">
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
            <span id="bulkAgencySelectionLabel" class="bulk-agency-selection-label">0 selected</span>
            <button type="button" id="bulkAgencyActivate" class="bulk-agency-btn bulk-agency-btn--act" disabled title="Set selected to Active">Act</button>
            <button type="button" id="bulkAgencyDeactivate" class="bulk-agency-btn bulk-agency-btn--inact" disabled title="Set selected to Inactive">Inact</button>
            <button type="button" id="bulkAgencyClear" class="bulk-agency-btn bulk-agency-btn--clear" disabled title="Clear selection">Clear</button>
            <button id="addAgencyBtn" class="neon-btn">Add Agency</button>
        </div>
    </div>

    <div class="glass-card table-shell">
        <table class="partnerships-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Country</th>
                    <th>City</th>
                    <th>Contact</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Workers Sent (Name / Passport)</th>
                    <th>Status</th>
                    <th class="col-select" scope="col">
                        <input type="checkbox" id="agencySelectAll" title="Select all on this page" aria-label="Select all on this page">
                    </th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="agenciesTableBody"></tbody>
        </table>
        <div class="table-pagination">
            <button id="agencyPrevPage" class="muted-btn" type="button">Prev</button>
            <span id="agencyPageInfo">Page 1</span>
            <button id="agencyNextPage" class="muted-btn" type="button">Next</button>
        </div>
    </div>
</div>

<div id="agencyModal" class="modal-wrap modal-wrap--agency-form">
    <div class="modal-card glass-card modal-card--agency-wide">
        <div class="modal-header-row">
            <h3 id="agencyModalTitle">Add Agency</h3>
            <button id="closeAgencyModal" class="icon-btn">×</button>
        </div>
        <form id="agencyForm" class="grid-form agency-form-grid">
            <input type="hidden" id="agencyId">
            <p class="agency-form-hint">Fields marked <span class="req-mark">*</span> are required. Email and partner password are used for the partner portal login.</p>

            <div class="agency-form-row">
                <label class="agency-field"><span class="req-mark">*</span> Agency name
                    <input type="text" id="agencyName" required maxlength="255" autocomplete="organization"></label>
                <label class="agency-field">Agency code
                    <input type="text" id="agencyCode" maxlength="64" autocomplete="off"></label>
            </div>
            <div class="agency-form-row">
                <label class="agency-field"><span class="req-mark">*</span> Country
                    <input type="text" id="agencyCountry" required maxlength="100" autocomplete="country-name"></label>
                <label class="agency-field"><span class="req-mark">*</span> Email (login)
                    <input type="email" id="agencyEmail" required maxlength="255" autocomplete="email"></label>
            </div>
            <div class="agency-form-row">
                <label class="agency-field"><span class="req-mark">*</span> Partner login password
                    <input type="password" id="agencyPortalPassword" autocomplete="new-password" placeholder="Min 6 characters" minlength="6"></label>
                <label class="agency-field"><span class="req-mark">*</span> Phone 1
                    <input type="text" id="agencyPhone" required maxlength="50" autocomplete="tel"></label>
            </div>
            <div class="agency-form-row">
                <label class="agency-field"><span class="req-mark">*</span> Address
                    <input type="text" id="agencyAddressEn" required maxlength="500" autocomplete="street-address"></label>
                <label class="agency-field"><span class="req-mark">*</span> City
                    <input type="text" id="agencyCity" required maxlength="100" autocomplete="address-level2"></label>
            </div>
            <div class="agency-form-row">
                <label class="agency-field"><span class="req-mark">*</span> License
                    <input type="text" id="agencyLicense" required maxlength="255"></label>
                <label class="agency-field">Manager / contact person
                    <input type="text" id="agencyContact" maxlength="255" autocomplete="name"></label>
            </div>
            <div class="agency-form-row">
                <label class="agency-field">Phone 2
                    <input type="text" id="agencyPhone2" maxlength="50" autocomplete="tel"></label>
                <label class="agency-field"><span class="req-mark">*</span> Fax
                    <input type="text" id="agencyFax" required maxlength="50"></label>
            </div>
            <div class="agency-form-row">
                <label class="agency-field">Mobile
                    <input type="text" id="agencyMobile" maxlength="50" autocomplete="tel"></label>
                <label class="agency-field">Passport no.
                    <input type="text" id="agencyPassportNo" maxlength="80"></label>
            </div>
            <div class="agency-form-row">
                <label class="agency-field">Place of issue
                    <input type="text" id="agencyPassportPlace" maxlength="255"></label>
                <label class="agency-field">Date of issue
                    <input type="date" id="agencyPassportDate"></label>
            </div>
            <div class="agency-form-row">
                <label class="agency-field">Sending bank
                    <input type="text" id="agencyBank" maxlength="255"></label>
                <label class="agency-field">Account number
                    <input type="text" id="agencyAccountNo" maxlength="100"></label>
            </div>
            <label class="agency-field agency-field--full">License owner
                <input type="text" id="agencyLicenseOwner" maxlength="255"></label>
            <label class="agency-field agency-field--full">Notes
                <textarea id="agencyNotes" rows="3" maxlength="8000" placeholder="Optional notes"></textarea></label>
            <label class="agency-field agency-field--full"><span class="req-mark">*</span> Status
                <select id="agencyStatus">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select></label>
            <div class="form-actions">
                <button type="button" id="cancelAgencyBtn" class="muted-btn">Cancel</button>
                <button type="submit" class="neon-btn">Save Agency</button>
            </div>
        </form>
    </div>
</div>

<div id="workersModal" class="modal-wrap">
    <div class="modal-card glass-card modal-card--workers-sent">
        <div class="modal-header-row">
            <h3 id="workersModalTitle">Deployments</h3>
            <button id="closeWorkersModal" class="icon-btn">×</button>
        </div>
        <div class="workers-sent-toolbar">
            <input id="workersSentSearch" type="text" placeholder="Search name, passport, country, job…">
            <select id="workersSentStatusFilter">
                <option value="">All deployment statuses</option>
                <option value="processing">processing</option>
                <option value="deployed">deployed</option>
                <option value="returned">returned</option>
                <option value="issue">issue</option>
                <option value="transferred">transferred</option>
            </select>
            <select id="workersSentSort">
                <option value="name_asc">Name A-Z</option>
                <option value="name_desc">Name Z-A</option>
                <option value="contract_desc">Contract end (newest)</option>
                <option value="contract_asc">Contract end (oldest)</option>
            </select>
            <select id="workersSentPageSize">
                <option value="5">5 / page</option>
                <option value="10" selected>10 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
            </select>
            <button type="button" id="workersSentExportCsv" class="muted-btn">Export CSV</button>
        </div>
        <div class="glass-card table-shell table-shell--workers-sent">
            <table class="partnerships-table partnerships-table--workers-sent">
                <thead>
                    <tr>
                        <th>Worker Name</th>
                        <th>Passport</th>
                        <th>Country</th>
                        <th>Agency</th>
                        <th>Status</th>
                        <th>Contract &amp; timeline</th>
                        <th>Job Title</th>
                        <th>Salary</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="workersModalBody"></tbody>
            </table>
            <div class="table-pagination workers-sent-pagination">
                <button type="button" id="workersSentPrevPage" class="muted-btn">Prev</button>
                <span id="workersSentPageInfo">Page 1 / 1</span>
                <button type="button" id="workersSentNextPage" class="muted-btn">Next</button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

