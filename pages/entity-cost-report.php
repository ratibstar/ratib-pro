<?php
/**
 * Entity cost report page.
 *
 * Shows expenses grouped by entity type and by individual entity.
 */
require_once '../includes/config.php';
require_once '../includes/permissions.php';

rateb_staff_page_require_session();
rateb_staff_page_require_permission('view_chart_accounts');

$pageTitle = 'Entity Cost Report';
// API URL is derived from the current page URL in JS to avoid relative-path issues.

$v = time();
$pageCss = [
    asset('css/accounting/professional.css') . '?v=' . $v,
];
$pageJs = [];

include '../includes/header.php';
?>

<div class="main-content entity-cost-report-page" dir="ltr" lang="en">
    <div class="page-header">
        <h2><i class="fas fa-chart-pie"></i> Entity Cost Report</h2>
    </div>

    <div class="card glass-card mb-3">
        <div class="card-body">
            <form id="costReportFilter" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Entity type</label>
                    <select name="entity_type" class="form-select">
                        <option value="">All types</option>
                        <option value="agent">Agent</option>
                        <option value="subagent">SubAgent</option>
                        <option value="worker">Workforce</option>
                        <option value="partner_agency">Partner Agency</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">From</label>
                    <input type="date" name="from" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To</label>
                    <input type="date" name="to" class="form-control">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">Refresh</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card glass-card">
                <div class="card-body">
                    <h5 class="card-title">Total Expenses</h5>
                    <h3 id="totalExpenses">0.00 SAR</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card glass-card">
                <div class="card-body">
                    <h5 class="card-title">By Entity Type</h5>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover" id="byTypeTable">
                            <thead>
                                <tr>
                                    <th>Entity Type</th>
                                    <th>Entities</th>
                                    <th>Transactions</th>
                                    <th class="num">Total Expenses</th>
                                </tr>
                            </thead>
                            <tbody id="byTypeTbody">
                                <tr><td colspan="4" class="text-center">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card glass-card">
                <div class="card-body">
                    <h5 class="card-title">By Entity</h5>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover" id="byEntityTable">
                            <thead>
                                <tr>
                                    <th>Entity Type</th>
                                    <th>Entity Name</th>
                                    <th>Transactions</th>
                                    <th class="num">Total Expenses</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="byEntityTbody">
                                <tr><td colspan="5" class="text-center">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var typeLabels = {
        agent: 'Agent',
        subagent: 'SubAgent',
        worker: 'Workforce',
        partner_agency: 'Partner Agency'
    };

    function getApiUrl() {
        var url = new URL(window.location.href);
        url.pathname = url.pathname.replace(/\/pages\/[^\/]+$/, '/api/accounting/entity-cost-report.php');
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

    function loadReport() {
        var form = document.getElementById('costReportFilter');
        var fd = new FormData(form);
        var url = getApiUrl();
        fd.forEach(function(v, k) { if (v) url.searchParams.set(k, v); });

        document.getElementById('byTypeTbody').innerHTML = '<tr><td colspan="4" class="text-center">Loading…</td></tr>';
        document.getElementById('byEntityTbody').innerHTML = '<tr><td colspan="5" class="text-center">Loading…</td></tr>';

        fetch(url.toString())
            .then(parseJsonResponse)
            .then(function(data) {
                if (!data.success) {
                    var msg = data.message || 'Failed to load';
                    document.getElementById('byTypeTbody').innerHTML = '<tr><td colspan="4" class="text-center text-danger">' + escapeHtml(msg) + '</td></tr>';
                    document.getElementById('byEntityTbody').innerHTML = '<tr><td colspan="5" class="text-center text-danger">' + escapeHtml(msg) + '</td></tr>';
                    return;
                }

                document.getElementById('totalExpenses').textContent = (data.total_expenses || 0).toFixed(2) + ' SAR';

                var byType = data.by_type || {};
                var typeKeys = Object.keys(byType);
                if (typeKeys.length === 0) {
                    document.getElementById('byTypeTbody').innerHTML = '<tr><td colspan="4" class="text-center">No data.</td></tr>';
                } else {
                    document.getElementById('byTypeTbody').innerHTML = typeKeys.map(function(type) {
                        var t = byType[type];
                        return '<tr>' +
                            '<td>' + escapeHtml(typeLabels[type] || type) + '</td>' +
                            '<td>' + (t.entity_count || 0) + '</td>' +
                            '<td>' + (t.transaction_count || 0) + '</td>' +
                            '<td class="num">' + (t.total_expenses || 0).toFixed(2) + ' SAR</td>' +
                        '</tr>';
                    }).join('');
                }

                var entities = data.entities || [];
                if (entities.length === 0) {
                    document.getElementById('byEntityTbody').innerHTML = '<tr><td colspan="5" class="text-center">No data.</td></tr>';
                } else {
                    document.getElementById('byEntityTbody').innerHTML = entities.map(function(e) {
                        var expenseUrl = 'entity-expenses.php?entity_type=' + encodeURIComponent(e.entity_type) + '&entity_id=' + encodeURIComponent(e.entity_id);
                        return '<tr>' +
                            '<td>' + escapeHtml(typeLabels[e.entity_type] || e.entity_type) + '</td>' +
                            '<td>' + escapeHtml(e.entity_name) + '</td>' +
                            '<td>' + (e.transaction_count || 0) + '</td>' +
                            '<td class="num">' + (e.total_expenses || 0).toFixed(2) + ' SAR</td>' +
                            '<td><a class="btn btn-sm btn-outline-info" href="' + expenseUrl + '">View expenses</a></td>' +
                        '</tr>';
                    }).join('');
                }
            })
            .catch(function(e) {
                document.getElementById('byTypeTbody').innerHTML = '<tr><td colspan="4" class="text-center text-danger">' + escapeHtml(e.message) + '</td></tr>';
                document.getElementById('byEntityTbody').innerHTML = '<tr><td colspan="5" class="text-center text-danger">' + escapeHtml(e.message) + '</td></tr>';
            });
    }

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    document.getElementById('costReportFilter').addEventListener('submit', function(e) {
        e.preventDefault();
        loadReport();
    });

    loadReport();
})();
</script>

<?php include '../includes/footer.php'; ?>
