<div class="acc-section" data-acc-page="drift">
    <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" class="btn btn-sm btn-primary acc-run-drift">Run Detection</button>
        <select class="form-select form-select-sm w-auto acc-filter-severity">
            <option value="">All severity</option>
            <option value="high">High</option>
            <option value="medium">Medium</option>
            <option value="low">Low</option>
        </select>
    </div>
    <canvas id="acc-chart-drift-severity" height="120" class="mb-3"></canvas>
    <div class="table-responsive">
        <table class="table table-sm acc-drift-table"><thead><tr><th>ID</th><th>Period</th><th>Severity</th><th>Summary</th><th></th></tr></thead><tbody></tbody></table>
    </div>
</div>
