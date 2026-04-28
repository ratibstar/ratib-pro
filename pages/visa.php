<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/visa.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/visa.php`.
 */
require_once __DIR__ . '/../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . pageUrl('login.php'));
    exit();
}

$pageTitle = "Visa Management";
$pageCss = [
    asset('css/dashboard.css')
];
$pageJs = [
    asset('js/dashboard.js'),
    asset('js/visa.js')
];

// Visa statistics will be loaded via JavaScript from API

include '../includes/header.php';
?>

<div class="main-content">
    <div class="header-bar">
        <h1>🛂 Visa Management</h1>
        <div class="flashing-text" id="flashingText">System Ready</div>
    </div>

    <div class="content-section">
        <!-- Visa Applications Card -->
        <div class="system-card">
            <h2>📋 Visa Applications</h2>
            <div class="status-info">
                <p class="count">Total: <span id="totalApplications">Loading...</span></p>
                <p class="count">Pending: <span id="pendingApplications">Loading...</span></p>
                <p class="count">Approved: <span id="approvedApplications">Loading...</span></p>
                <p class="count">Rejected: <span id="rejectedApplications">Loading...</span></p>
            </div>
            <div class="card-actions">
                <button id="addVisaApplicationBtn" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New
                </button>
                <button id="viewAllApplicationsBtn" class="btn btn-secondary">
                    <i class="fas fa-list"></i> View All
                </button>
            </div>
        </div>

        <!-- Visa Types Card -->
        <div class="system-card">
            <h2>📄 Visa Types</h2>
            <div class="status-info">
                <p class="status">Status: <span class="status-indicator active">Active</span></p>
                <p class="system-type">Tourist, Business, Work, Student</p>
            </div>
            <div class="card-actions">
                <button id="manageVisaTypesBtn" class="btn btn-primary">
                    <i class="fas fa-cog"></i> Manage Types
                </button>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="system-card">
            <h2>⚡ Quick Actions</h2>
            <div class="quick-actions">
                <button id="bulkApproveBtn" class="btn btn-success">
                    <i class="fas fa-check"></i> Bulk Approve
                </button>
                <button id="bulkRejectBtn" class="btn btn-danger">
                    <i class="fas fa-times"></i> Bulk Reject
                </button>
                <button id="exportApplicationsBtn" class="btn btn-info">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div class="activities-section">
        <h2>📜 Recent Activities</h2>
        <div class="activity-list">
            <p class="no-activities">No recent activities</p>
        </div>
    </div>
</div>

<!-- Add Visa Application Modal -->
<div id="addVisaModal" class="modal d-none">
    <div class="modal-content">
        <div class="modal-header">
            <h2>📝 Add New Visa Application</h2>
            <span class="close" data-modal="addVisaModal">&times;</span>
        </div>
        <div class="modal-body">
            <form id="addVisaForm">
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" id="applicant_name" name="applicant_name" placeholder="Full Name" required>
                    </div>
                    <div class="form-group">
                        <input type="text" id="passport_number" name="passport_number" placeholder="Passport Number" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <div class="nationality-container">
                            <input type="text" id="nationality_search" placeholder="Search Nationality..." class="nationality-search">
                            <div id="nationality_dropdown" class="nationality-dropdown">
                            </div>
                            <select id="nationality" name="nationality" required class="nationality-select">
                            <option value="">Select Nationality</option>
                            <option value="Afghan">Afghan</option>
                            <option value="Albanian">Albanian</option>
                            <option value="Algerian">Algerian</option>
                            <option value="American">American</option>
                            <option value="Argentinian">Argentinian</option>
                            <option value="Armenian">Armenian</option>
                            <option value="Australian">Australian</option>
                            <option value="Austrian">Austrian</option>
                            <option value="Azerbaijani">Azerbaijani</option>
                            <option value="Bahraini">Bahraini</option>
                            <option value="Bangladeshi">Bangladeshi</option>
                            <option value="Belgian">Belgian</option>
                            <option value="Brazilian">Brazilian</option>
                            <option value="British">British</option>
                            <option value="Bulgarian">Bulgarian</option>
                            <option value="Burmese">Burmese</option>
                            <option value="Cambodian">Cambodian</option>
                            <option value="Canadian">Canadian</option>
                            <option value="Chilean">Chilean</option>
                            <option value="Chinese">Chinese</option>
                            <option value="Colombian">Colombian</option>
                            <option value="Croatian">Croatian</option>
                            <option value="Cypriot">Cypriot</option>
                            <option value="Czech">Czech</option>
                            <option value="Danish">Danish</option>
                            <option value="Dutch">Dutch</option>
                            <option value="Ecuadorian">Ecuadorian</option>
                            <option value="Egyptian">Egyptian</option>
                            <option value="Estonian">Estonian</option>
                            <option value="Ethiopian">Ethiopian</option>
                            <option value="Filipino">Filipino</option>
                            <option value="Finnish">Finnish</option>
                            <option value="French">French</option>
                            <option value="Georgian">Georgian</option>
                            <option value="German">German</option>
                            <option value="Ghanaian">Ghanaian</option>
                            <option value="Greek">Greek</option>
                            <option value="Hungarian">Hungarian</option>
                            <option value="Icelandic">Icelandic</option>
                            <option value="Indian">Indian</option>
                            <option value="Indonesian">Indonesian</option>
                            <option value="Iranian">Iranian</option>
                            <option value="Iraqi">Iraqi</option>
                            <option value="Irish">Irish</option>
                            <option value="Israeli">Israeli</option>
                            <option value="Italian">Italian</option>
                            <option value="Jamaican">Jamaican</option>
                            <option value="Japanese">Japanese</option>
                            <option value="Jordanian">Jordanian</option>
                            <option value="Kazakhstani">Kazakhstani</option>
                            <option value="Kenyan">Kenyan</option>
                            <option value="Korean">Korean</option>
                            <option value="Kuwaiti">Kuwaiti</option>
                            <option value="Latvian">Latvian</option>
                            <option value="Lebanese">Lebanese</option>
                            <option value="Lithuanian">Lithuanian</option>
                            <option value="Luxembourgish">Luxembourgish</option>
                            <option value="Malaysian">Malaysian</option>
                            <option value="Maltese">Maltese</option>
                            <option value="Mexican">Mexican</option>
                            <option value="Moroccan">Moroccan</option>
                            <option value="Nepalese">Nepalese</option>
                            <option value="New Zealander">New Zealander</option>
                            <option value="Nigerian">Nigerian</option>
                            <option value="Norwegian">Norwegian</option>
                            <option value="Omani">Omani</option>
                            <option value="Pakistani">Pakistani</option>
                            <option value="Peruvian">Peruvian</option>
                            <option value="Polish">Polish</option>
                            <option value="Portuguese">Portuguese</option>
                            <option value="Qatari">Qatari</option>
                            <option value="Romanian">Romanian</option>
                            <option value="Russian">Russian</option>
                            <option value="Saudi">Saudi</option>
                            <option value="Singaporean">Singaporean</option>
                            <option value="Slovak">Slovak</option>
                            <option value="Slovenian">Slovenian</option>
                            <option value="South African">South African</option>
                            <option value="Spanish">Spanish</option>
                            <option value="Sri Lankan">Sri Lankan</option>
                            <option value="Sudanese">Sudanese</option>
                            <option value="Swedish">Swedish</option>
                            <option value="Swiss">Swiss</option>
                            <option value="Syrian">Syrian</option>
                            <option value="Thai">Thai</option>
                            <option value="Tunisian">Tunisian</option>
                            <option value="Turkish">Turkish</option>
                            <option value="Ukrainian">Ukrainian</option>
                            <option value="UAE">UAE</option>
                            <option value="Uruguayan">Uruguayan</option>
                            <option value="Venezuelan">Venezuelan</option>
                            <option value="Vietnamese">Vietnamese</option>
                            <option value="Yemeni">Yemeni</option>
                            <option value="Zimbabwean">Zimbabwean</option>
                            <option value="Other">Other</option>
                        </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <input type="date" id="date_of_birth" name="date_of_birth" placeholder="Date of Birth" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <select id="gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <select id="visa_type_id" name="visa_type_id" required>
                            <option value="">Select Visa Type</option>
                            <option value="1">Tourist</option>
                            <option value="2">Business</option>
                            <option value="3">Work</option>
                            <option value="4">Student</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <input type="tel" id="contact_number" name="phone" placeholder="Phone Number" required>
                    </div>
                    <div class="form-group">
                        <input type="email" id="email" name="email" placeholder="Email Address">
                    </div>
                </div>
                
                <div class="form-group">
                    <textarea id="address" name="notes" rows="2" placeholder="Full Address (City, Country)"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <select id="agent_id" name="agent_id">
                            <option value="">Select Agent</option>
                            <option value="1">Agent 1 - John Smith</option>
                            <option value="2">Agent 2 - Sarah Johnson</option>
                            <option value="3">Agent 3 - Ahmed Hassan</option>
                            <option value="4">Agent 4 - Maria Garcia</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <select id="duration" name="duration" required>
                            <option value="">Select Duration</option>
                            <option value="1">1 Month</option>
                            <option value="3">3 Months</option>
                            <option value="6">6 Months</option>
                            <option value="12">12 Months</option>
                            <option value="24">24 Months</option>
                            <option value="36">36 Months</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <select id="status" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="in_review">In Review</option>
                            <option value="on_hold">On Hold</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <select id="priority" name="priority">
                            <option value="">Select Priority</option>
                            <option value="low">Low</option>
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Add Application</button>
                    <button type="button" class="btn btn-secondary" data-modal="addVisaModal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Visa Application Modal -->
<div id="editVisaModal" class="modal d-none">
    <div class="modal-content">
        <div class="modal-header">
            <h2>✏️ Edit Visa Application</h2>
            <span class="close" data-modal="editVisaModal">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editVisaForm">
                <input type="hidden" id="edit_visa_id" name="visa_id">
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" id="edit_applicant_name" name="applicant_name" placeholder="Full Name" required>
                    </div>
                    <div class="form-group">
                        <input type="text" id="edit_passport_number" name="passport_number" placeholder="Passport Number" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <div class="nationality-container">
                            <input type="text" id="edit_nationality_search" placeholder="Search Nationality..." class="nationality-search">
                            <div id="edit_nationality_dropdown" class="nationality-dropdown">
                            </div>
                            <select id="edit_nationality" name="nationality" required class="nationality-select">
                            <option value="">Select Nationality</option>
                            <option value="Afghan">Afghan</option>
                            <option value="Albanian">Albanian</option>
                            <option value="Algerian">Algerian</option>
                            <option value="American">American</option>
                            <option value="Argentinian">Argentinian</option>
                            <option value="Armenian">Armenian</option>
                            <option value="Australian">Australian</option>
                            <option value="Austrian">Austrian</option>
                            <option value="Azerbaijani">Azerbaijani</option>
                            <option value="Bahraini">Bahraini</option>
                            <option value="Bangladeshi">Bangladeshi</option>
                            <option value="Belgian">Belgian</option>
                            <option value="Brazilian">Brazilian</option>
                            <option value="British">British</option>
                            <option value="Bulgarian">Bulgarian</option>
                            <option value="Burmese">Burmese</option>
                            <option value="Cambodian">Cambodian</option>
                            <option value="Canadian">Canadian</option>
                            <option value="Chilean">Chilean</option>
                            <option value="Chinese">Chinese</option>
                            <option value="Colombian">Colombian</option>
                            <option value="Croatian">Croatian</option>
                            <option value="Cypriot">Cypriot</option>
                            <option value="Czech">Czech</option>
                            <option value="Danish">Danish</option>
                            <option value="Dutch">Dutch</option>
                            <option value="Ecuadorian">Ecuadorian</option>
                            <option value="Egyptian">Egyptian</option>
                            <option value="Estonian">Estonian</option>
                            <option value="Ethiopian">Ethiopian</option>
                            <option value="Filipino">Filipino</option>
                            <option value="Finnish">Finnish</option>
                            <option value="French">French</option>
                            <option value="Georgian">Georgian</option>
                            <option value="German">German</option>
                            <option value="Ghanaian">Ghanaian</option>
                            <option value="Greek">Greek</option>
                            <option value="Hungarian">Hungarian</option>
                            <option value="Icelandic">Icelandic</option>
                            <option value="Indian">Indian</option>
                            <option value="Indonesian">Indonesian</option>
                            <option value="Iranian">Iranian</option>
                            <option value="Iraqi">Iraqi</option>
                            <option value="Irish">Irish</option>
                            <option value="Israeli">Israeli</option>
                            <option value="Italian">Italian</option>
                            <option value="Jamaican">Jamaican</option>
                            <option value="Japanese">Japanese</option>
                            <option value="Jordanian">Jordanian</option>
                            <option value="Kazakhstani">Kazakhstani</option>
                            <option value="Kenyan">Kenyan</option>
                            <option value="Korean">Korean</option>
                            <option value="Kuwaiti">Kuwaiti</option>
                            <option value="Latvian">Latvian</option>
                            <option value="Lebanese">Lebanese</option>
                            <option value="Lithuanian">Lithuanian</option>
                            <option value="Luxembourgish">Luxembourgish</option>
                            <option value="Malaysian">Malaysian</option>
                            <option value="Maltese">Maltese</option>
                            <option value="Mexican">Mexican</option>
                            <option value="Moroccan">Moroccan</option>
                            <option value="Nepalese">Nepalese</option>
                            <option value="New Zealander">New Zealander</option>
                            <option value="Nigerian">Nigerian</option>
                            <option value="Norwegian">Norwegian</option>
                            <option value="Omani">Omani</option>
                            <option value="Pakistani">Pakistani</option>
                            <option value="Peruvian">Peruvian</option>
                            <option value="Polish">Polish</option>
                            <option value="Portuguese">Portuguese</option>
                            <option value="Qatari">Qatari</option>
                            <option value="Romanian">Romanian</option>
                            <option value="Russian">Russian</option>
                            <option value="Saudi">Saudi</option>
                            <option value="Singaporean">Singaporean</option>
                            <option value="Slovak">Slovak</option>
                            <option value="Slovenian">Slovenian</option>
                            <option value="South African">South African</option>
                            <option value="Spanish">Spanish</option>
                            <option value="Sri Lankan">Sri Lankan</option>
                            <option value="Sudanese">Sudanese</option>
                            <option value="Swedish">Swedish</option>
                            <option value="Swiss">Swiss</option>
                            <option value="Syrian">Syrian</option>
                            <option value="Thai">Thai</option>
                            <option value="Tunisian">Tunisian</option>
                            <option value="Turkish">Turkish</option>
                            <option value="Ukrainian">Ukrainian</option>
                            <option value="UAE">UAE</option>
                            <option value="Uruguayan">Uruguayan</option>
                            <option value="Venezuelan">Venezuelan</option>
                            <option value="Vietnamese">Vietnamese</option>
                            <option value="Yemeni">Yemeni</option>
                            <option value="Zimbabwean">Zimbabwean</option>
                            <option value="Other">Other</option>
                        </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_date_of_birth">Date of Birth:</label>
                        <input type="date" id="edit_date_of_birth" name="date_of_birth" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_gender">Gender:</label>
                        <select id="edit_gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <select id="edit_visa_type_id" name="visa_type_id" required>
                            <option value="">Select Visa Type</option>
                            <option value="1">Tourist</option>
                            <option value="2">Business</option>
                            <option value="3">Work</option>
                            <option value="4">Student</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <input type="tel" id="edit_contact_number" name="phone" placeholder="Phone Number" required>
                    </div>
                    <div class="form-group">
                        <input type="email" id="edit_email" name="email" placeholder="Email Address">
                    </div>
                </div>
                
                <div class="form-group">
                    <textarea id="edit_address" name="notes" rows="2" placeholder="Full Address (City, Country)"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <select id="edit_agent_id" name="agent_id">
                            <option value="">Select Agent</option>
                            <option value="1">Agent 1 - John Smith</option>
                            <option value="2">Agent 2 - Sarah Johnson</option>
                            <option value="3">Agent 3 - Ahmed Hassan</option>
                            <option value="4">Agent 4 - Maria Garcia</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <select id="edit_duration" name="duration" required>
                            <option value="">Select Duration</option>
                            <option value="1">1 Month</option>
                            <option value="3">3 Months</option>
                            <option value="6">6 Months</option>
                            <option value="12">12 Months</option>
                            <option value="24">24 Months</option>
                            <option value="36">36 Months</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <select id="edit_status" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="in_review">In Review</option>
                            <option value="on_hold">On Hold</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <select id="edit_priority" name="priority">
                            <option value="">Select Priority</option>
                            <option value="low">Low</option>
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_notes">Notes:</label>
                    <textarea id="edit_notes" name="notes" rows="3"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Application</button>
                    <button type="button" class="btn btn-secondary" data-modal="editVisaModal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteVisaModal" class="modal d-none">
    <div class="modal-content">
        <div class="modal-header">
            <h2>🗑️ Delete Visa Application</h2>
            <span class="close" data-modal="deleteVisaModal">&times;</span>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete this visa application?</p>
            <div class="form-actions">
                <button id="confirmDeleteVisaBtn" class="btn btn-danger">Delete</button>
                <button data-modal="deleteVisaModal" class="btn btn-secondary">Cancel</button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

