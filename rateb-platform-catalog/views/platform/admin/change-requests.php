<div class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_change_requests', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
        <select class="form-select form-select-sm" id="crStatus">
            <option value="">All</option>
            <option value="pending">pending</option>
            <option value="approved">approved</option>
            <option value="rejected">rejected</option>
            <option value="applied">applied</option>
        </select>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-admin-refresh="admin:page-refresh"><?= htmlspecialchars(catalog__('admin_refresh', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm btn-primary" id="crCreateToggle"><?= htmlspecialchars(catalog__('admin_create', $locale), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <div id="entityList"></div>
</div>
<div class="admin-panel mt-3" id="crCreatePanel" hidden>
    <form id="crCreateForm">
        <div class="admin-form-grid">
            <div><label class="form-label">Product UUID</label><input class="form-control" name="product_uuid" required></div>
            <div><label class="form-label">Request type</label><input class="form-control" name="request_type" value="update"></div>
        </div>
        <label class="form-label mt-2">Proposed changes JSON</label>
        <textarea class="form-control font-monospace" name="proposed_changes_json" rows="5">{"status":"draft"}</textarea>
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-sm btn-primary"><?= htmlspecialchars(catalog__('admin_save', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </form>
</div>
<div class="admin-panel mt-3" id="entityDetailPanel" hidden>
    <div id="entityDetail"></div>
    <div class="admin-form-actions" id="crActions"></div>
</div>
