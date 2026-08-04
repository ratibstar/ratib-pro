<div class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_categories', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
        <span class="badge text-bg-secondary"><?= htmlspecialchars(catalog__('admin_read_only', $locale), ENT_QUOTES, 'UTF-8') ?></span>
        <div class="admin-toolbar-spacer"></div>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-admin-refresh="admin:page-refresh"><?= htmlspecialchars(catalog__('admin_refresh', $locale), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <div id="entityList"></div>
</div>
<div class="admin-panel mt-3" id="entityDetailPanel" hidden>
    <h2 class="h5 mb-3"><?= htmlspecialchars(catalog__('admin_details', $locale), ENT_QUOTES, 'UTF-8') ?></h2>
    <div id="entityDetail"></div>
    <form id="schemaForm" class="mt-3" hidden>
        <h3 class="h6"><?= htmlspecialchars(catalog__('admin_attribute_schema', $locale), ENT_QUOTES, 'UTF-8') ?></h3>
        <label class="form-label"><?= htmlspecialchars(catalog__('admin_schema_json', $locale), ENT_QUOTES, 'UTF-8') ?></label>
        <textarea class="form-control font-monospace" name="schema_json" id="schemaJson" rows="10"></textarea>
        <input type="hidden" name="uuid" id="schemaUuid">
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary btn-sm"><?= htmlspecialchars(catalog__('admin_save', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </form>
</div>
