<div class="acc-section" data-acc-page="projections">
    <div class="d-flex flex-wrap gap-2 mb-3">
        <select class="form-select form-select-sm w-auto acc-projection-type">
            <option value="trial_balance">Trial Balance</option>
            <option value="balance_sheet">Balance Sheet</option>
            <option value="profit_loss">Profit &amp; Loss</option>
            <option value="cashflow">Cash Flow</option>
        </select>
        <button type="button" class="btn btn-sm btn-primary acc-load-projection">Load</button>
        <button type="button" class="btn btn-sm btn-warning acc-rebuild-snapshot">Rebuild Snapshot</button>
    </div>
    <div class="alert alert-secondary acc-period-closure small"></div>
    <div class="table-responsive">
        <table class="table table-sm acc-projection-table"><thead><tr><th>Account</th><th>Data</th></tr></thead><tbody></tbody></table>
    </div>
</div>
