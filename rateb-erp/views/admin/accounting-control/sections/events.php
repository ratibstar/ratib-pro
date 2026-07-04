<div class="acc-section" data-acc-page="events">
    <div class="row g-2 mb-2 acc-extra-filters">
        <div class="col-md-2"><input type="text" class="form-control form-control-sm acc-filter-uuid" placeholder="UUID"></div>
        <div class="col-md-2">
            <select class="form-select form-select-sm acc-filter-status">
                <option value="">Status</option>
                <option value="pending">pending</option>
                <option value="processed">processed</option>
                <option value="failed">failed</option>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm acc-filter-system">
                <option value="">System</option>
                <option value="rateb-erp">rateb-erp</option>
                <option value="main-site">main-site</option>
                <option value="control-panel">control-panel</option>
                <option value="ledger">ledger</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover acc-data-table">
            <thead><tr>
                <th>UUID</th><th>System</th><th>Type</th><th>Status</th><th>Company</th><th>Branch</th><th>Created</th><th></th>
            </tr></thead>
            <tbody></tbody>
        </table>
    </div>
    <nav class="acc-pagination"></nav>
</div>
