<div class="acc-section" data-acc-page="replay">
    <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" class="btn btn-sm btn-outline-primary acc-replay-preview" data-mode="failed">Preview Failed</button>
        <button type="button" class="btn btn-sm btn-outline-warning acc-replay-run" data-mode="failed">Replay Failed</button>
        <button type="button" class="btn btn-sm btn-outline-secondary acc-replay-preview" data-mode="period">Preview Period</button>
        <button type="button" class="btn btn-sm btn-warning acc-replay-run" data-mode="period">Replay Period</button>
    </div>
    <div class="acc-replay-progress d-none mb-3">
        <div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div></div>
    </div>
    <pre class="acc-replay-result bg-dark text-light p-3 rounded small"></pre>
    <h6 class="mt-3">Replay History</h6>
    <div class="table-responsive">
        <table class="table table-sm acc-replay-history"><thead><tr><th>Time</th><th>UUID</th><th>Action</th><th>Status</th></tr></thead><tbody></tbody></table>
    </div>
</div>
