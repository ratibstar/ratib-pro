<div class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_duplicates', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
        <select class="form-select form-select-sm" id="dupStatus">
            <option value="">All</option>
            <option value="open">open</option>
            <option value="resolved">resolved</option>
        </select>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-admin-refresh="admin:page-refresh"><?= htmlspecialchars(catalog__('admin_refresh', $locale), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <div id="entityList"></div>
</div>
<div class="admin-panel mt-3" id="entityDetailPanel" hidden>
    <div id="entityDetail"></div>
    <form id="dupResolveForm" class="mt-3" hidden>
        <div class="admin-form-grid">
            <div><label class="form-label">Resolution</label><input class="form-control" name="resolution" value="merge" required></div>
            <div><label class="form-label">Keep product UUID</label><input class="form-control" name="keep_product_uuid"></div>
        </div>
        <input type="hidden" name="uuid" id="dupUuid">
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-sm btn-primary">Resolve</button>
        </div>
    </form>
</div>
<div class="admin-panel mt-3">
    <h2 class="h5">Duplicate rules</h2>
    <div id="dupRules"></div>
</div>
