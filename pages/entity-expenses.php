<?php
/**
 * Unified entity expenses page.
 *
 * Query params:
 *   entity_type = agent | subagent | worker | partner_agency
 *   entity_id   = int
 *
 * Allows staff to view, add, edit, and delete expenses linked to a specific
 * entity. Uses the existing api/accounting/entity-transactions.php API.
 */
require_once '../includes/config.php';
require_once '../includes/permissions.php';

rateb_staff_page_require_session();
rateb_staff_page_require_permission('view_chart_accounts');

$entityType = strtolower(trim((string) ($_GET['entity_type'] ?? '')));
$entityId = (int) ($_GET['entity_id'] ?? 0);

$allowedTypes = ['agent', 'subagent', 'worker', 'partner_agency'];
if (!in_array($entityType, $allowedTypes, true) || $entityId <= 0) {
    http_response_code(400);
    $pageTitle = 'Invalid entity';
    include '../includes/header.php';
    echo '<div class="main-content"><div class="alert alert-danger">Entity type and ID are required.</div></div>';
    include '../includes/footer.php';
    exit;
}

$entityName = '';
if (isset($conn) && $conn instanceof mysqli) {
    $map = [
        'agent' => ['table' => 'agents', 'name_col' => 'agent_name'],
        'subagent' => ['table' => 'subagents', 'name_col' => 'subagent_name'],
        'worker' => ['table' => 'workers', 'name_col' => 'worker_name'],
        'partner_agency' => ['table' => 'partner_agencies', 'name_col' => 'name'],
    ];
    $m = $map[$entityType];
    $cols = $m['name_col'];
    if (!empty($m['fallback'])) {
        $cols .= ', ' . $m['fallback'];
    }
    $sql = "SELECT {$cols} FROM {$m['table']} WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $entityId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (is_array($row)) {
            $entityName = trim((string) ($row[$m['name_col']] ?? ''));
            if ($entityName === '' && !empty($m['fallback'])) {
                $entityName = trim((string) ($row[$m['fallback']] ?? ''));
            }
        }
    }
}
if ($entityName === '') {
    $entityName = ucfirst($entityType) . ' #' . $entityId;
}

$labels = [
    'agent' => 'Agent',
    'subagent' => 'SubAgent',
    'worker' => 'Workforce',
    'partner_agency' => 'Partner Agency',
];
$entityLabel = $labels[$entityType] ?? ucfirst($entityType);

$pageTitle = $entityLabel . ' Expenses — ' . $entityName;
$backUrl = htmlspecialchars(rateb_nav_url($entityType === 'partner_agency' ? 'partner-agencies.php' : ($entityType . '.php')), ENT_QUOTES, 'UTF-8');
// API URL is derived from the current page URL in JS to avoid relative-path issues.
$canCreate = hasPermission('add_journal_entry');
$canUpdate = hasPermission('edit_journal_entry');
$canDelete = hasPermission('delete_journal_entry');

$v = time();
$pageCss = [
    asset('css/accounting/professional.css') . '?v=' . $v,
];
$pageJs = [];

include '../includes/header.php';
?>

<style>
.entity-expenses-page {
    padding: 1.5rem;
}

/* Desktop: account for fixed sidebar */
@media (min-width: 769px) {
    .entity-expenses-page {
        margin-left: 240px;
        width: calc(100% - 240px);
        max-width: calc(100% - 240px);
    }
}

/* Mobile/Tablet: full width */
@media (max-width: 768px) {
    .entity-expenses-page {
        margin-left: 0;
        width: 100%;
        max-width: 100%;
        padding: 0.75rem;
    }
}

.entity-expenses-page .page-header h2 {
    color: #f8fafc;
}

.entity-expenses-page .card {
    background-color: #1e293b;
    color: #e2e8f0;
    border: 1px solid #334155;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
}

.entity-expenses-page .card-title {
    color: #f8fafc;
}

.entity-expenses-page .form-label {
    color: #cbd5e1;
}

.entity-expenses-page .form-control {
    background-color: #0f172a;
    color: #f8fafc;
    border-color: #334155;
}

.entity-expenses-page .form-control:focus {
    background-color: #0f172a;
    color: #f8fafc;
    border-color: #3b82f6;
    box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
}

.entity-expenses-page .form-control::placeholder {
    color: #64748b;
}

.entity-expenses-page .btn-outline-secondary {
    color: #cbd5e1;
    border-color: #475569;
}

.entity-expenses-page .btn-outline-secondary:hover {
    background-color: #334155;
    color: #f8fafc;
}

.entity-expenses-page .text-muted {
    color: #94a3b8 !important;
}

.entity-expenses-page .table-dark {
    --bs-table-bg: #0f172a;
    --bs-table-color: #e2e8f0;
    --bs-table-border-color: #334155;
}

.entity-expenses-page .table-dark th,
.entity-expenses-page .table-dark td {
    border-color: #334155;
}

.entity-expenses-page .table-dark thead th {
    background-color: #1e293b;
    color: #f8fafc;
}

.entity-expenses-page .table-dark tbody tr:hover {
    background-color: #1e293b;
}

/* Ensure the row containing the two cards stacks properly on smaller screens */
.entity-expenses-page .row > [class*="col-"] {
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}
</style>

<div class="main-content entity-expenses-page" dir="ltr" lang="en">
    <div class="container-fluid">
        <div class="page-header">
            <a href="<?php echo $backUrl; ?>" class="btn btn-sm btn-outline-secondary">← Back to <?php echo htmlspecialchars($entityLabel); ?></a>
            <h2 class="mt-2"><?php echo htmlspecialchars($entityLabel); ?> Expenses: <?php echo htmlspecialchars($entityName); ?></h2>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <div class="card glass-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Add Expense</h5>
                        <form id="expenseForm">
                            <input type="hidden" name="entity_type" value="<?php echo htmlspecialchars($entityType); ?>">
                            <input type="hidden" name="entity_id" value="<?php echo (int) $entityId; ?>">
                            <input type="hidden" name="transaction_type" value="Expense">
                            <input type="hidden" name="entry_type" value="Manual">
                            <div class="mb-2">
                                <label class="form-label">Date</label>
                                <input type="date" name="transaction_date" class="form-control" lang="en" dir="ltr" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Description</label>
                                <input type="text" name="description" class="form-control" required placeholder="Select or type..." list="descriptionOptions">
                                <datalist id="descriptionOptions">
                                    <option value="Office rent">
                                    <option value="Commission">
                                    <option value="Travel">
                                    <option value="Salary">
                                    <option value="Utilities">
                                    <option value="Marketing">
                                    <option value="Supplies">
                                    <option value="Maintenance">
                                    <option value="Insurance">
                                    <option value="Other">
                                </datalist>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Amount</label>
                                <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required placeholder="0.00">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Category</label>
                                <input type="text" name="category" class="form-control" placeholder="Select or type..." list="categoryOptions">
                                <datalist id="categoryOptions">
                                    <option value="Operational">
                                    <option value="Administrative">
                                    <option value="Marketing">
                                    <option value="Travel">
                                    <option value="Payroll">
                                    <option value="Utilities">
                                    <option value="Maintenance">
                                    <option value="Insurance">
                                    <option value="Other">
                                </datalist>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Reference</label>
                                <input type="text" name="reference_number" class="form-control" placeholder="Optional">
                            </div>
                            <button type="submit" class="btn btn-primary" <?php echo $canCreate ? '' : 'disabled'; ?>>
                                <i class="fas fa-plus"></i> Add Expense
                            </button>
                            <?php if (!$canCreate): ?>
                                <small class="text-muted d-block mt-1">You do not have permission to add expenses.</small>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card glass-card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Expense History</h5>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover" id="expensesTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Reference</th>
                                        <th>Category</th>
                                        <th class="num">Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="expensesTbody">
                                    <tr><td colspan="7" class="text-center">Loading…</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div id="expensesSummary" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-2">
            <div class="col-12">
                <div class="card glass-card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-link"></i> Accounting Integration</h5>
                        <p class="mb-2">Every expense saved here is automatically linked to the Accounting module:</p>
                        <ul class="mb-3">
                            <li><strong>Financial transaction entry</strong> (قيد) — stored in the general ledger.</li>
                            <li><strong>Accounts payable voucher / Bill</strong> (سند) — created for each expense.</li>
                            <li><strong>Entry approval</strong> — pending approval workflow.</li>
                            <li><strong>Cost reports</strong> — aggregated in the Entity Cost Report.</li>
                        </ul>
                        <a href="<?php echo htmlspecialchars(rateb_nav_url('accounting.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-info">Open Accounting</a>
                        <a href="<?php echo htmlspecialchars(rateb_nav_url('entity-cost-report.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-info">View Cost Report</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var entityType = <?php echo json_encode($entityType); ?>;
    var entityId = <?php echo (int) $entityId; ?>;
    var canDelete = <?php echo json_encode($canDelete); ?>;
    var canUpdate = <?php echo json_encode($canUpdate); ?>;

    function getApiUrl() {
        var url = new URL(window.location.href);
        url.pathname = url.pathname.replace(/\/pages\/[^\/]+$/, '/api/accounting/entity-transactions.php');
        return url;
    }

    function parseJsonResponse(r) {
        var contentType = r.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            return r.text().then(function(text) {
                throw new Error('Server returned non-JSON response: ' + text.slice(0, 100));
            });
        }
        return r.json();
    }

    function loadExpenses() {
        var tbody = document.getElementById('expensesTbody');
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">Loading…</td></tr>';
        var url = getApiUrl();
        url.searchParams.set('entity_type', entityType);
        url.searchParams.set('entity_id', entityId);
        fetch(url.toString())
            .then(parseJsonResponse)
            .then(function(data) {
                if (!data.success) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">' + escapeHtml(data.message || 'Failed to load') + '</td></tr>';
                    return;
                }
                var rows = data.transactions || [];
                var summary = data.summary || {};
                if (rows.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center">No expenses recorded yet. Add your first expense using the form on the left.</td></tr>';
                } else {
                    tbody.innerHTML = rows.map(function(t) {
                        var amount = parseFloat(t.total_amount || t.debit_amount || 0).toFixed(2);
                        var currency = t.currency || 'SAR';
                        var date = t.transaction_date || '';
                        var desc = t.description || '';
                        var ref = t.reference_number || '';
                        var cat = t.category || '';
                        var status = t.status || 'Posted';
                        var actions = '';
                        if (canDelete) {
                            actions += '<button type="button" class="btn btn-sm btn-outline-danger btn-delete-expense" data-id="' + (t.id || '') + '">Delete</button>';
                        }
                        return '<tr data-id="' + (t.id || '') + '">' +
                            '<td>' + escapeHtml(date) + '</td>' +
                            '<td>' + escapeHtml(desc) + '</td>' +
                            '<td>' + escapeHtml(ref) + '</td>' +
                            '<td>' + escapeHtml(cat) + '</td>' +
                            '<td class="num">' + escapeHtml(amount + ' ' + currency) + '</td>' +
                            '<td>' + escapeHtml(status) + '</td>' +
                            '<td>' + actions + '</td>' +
                        '</tr>';
                    }).join('');
                }
                var total = parseFloat(summary.total_debit || summary.total_expenses || 0).toFixed(2);
                document.getElementById('expensesSummary').innerHTML =
                    '<strong>Total expenses:</strong> ' + escapeHtml(total + ' ' + (summary.currency || 'SAR')) +
                    ' <span class="text-muted">(' + (summary.count || rows.length) + ' records)</span>';
            })
            .catch(function(e) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error: ' + escapeHtml(e.message) + '</td></tr>';
            });
    }

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    document.getElementById('expenseForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var form = e.target;
        var fd = new FormData(form);
        var payload = {};
        fd.forEach(function(v, k) { payload[k] = v; });
        payload.amount = parseFloat(payload.amount) || 0;
        payload.debit = payload.amount;
        payload.debit_amount = payload.amount;
        payload.total_amount = payload.amount;

        fetch(getApiUrl().toString(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(parseJsonResponse)
        .then(function(data) {
            if (data.success) {
                form.reset();
                document.querySelector('input[name="transaction_date"]').value = new Date().toISOString().split('T')[0];
                loadExpenses();
            } else {
                alert(data.message || 'Failed to add expense');
            }
        })
        .catch(function(e) {
            alert('Error: ' + e.message);
        });
    });

    document.getElementById('expensesTbody').addEventListener('click', function(e) {
        if (!e.target.classList.contains('btn-delete-expense')) return;
        var id = e.target.getAttribute('data-id');
        if (!id || !confirm('Delete this expense?')) return;
        var url = getApiUrl();
        url.searchParams.set('id', id);
        url.searchParams.set('entity_type', entityType);
        url.searchParams.set('entity_id', entityId);
        fetch(url.toString(), { method: 'DELETE' })
        .then(parseJsonResponse)
        .then(function(data) {
            if (data.success) {
                loadExpenses();
            } else {
                alert(data.message || 'Failed to delete');
            }
        })
        .catch(function(e) {
            alert('Error: ' + e.message);
        });
    });

    loadExpenses();
})();
</script>

<?php include '../includes/footer.php'; ?>
