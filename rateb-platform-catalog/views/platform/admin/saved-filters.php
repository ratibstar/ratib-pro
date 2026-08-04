<div class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_saved_filters', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
        <select class="form-select form-select-sm" id="sfEntityType">
            <option value=""><?= htmlspecialchars(catalog__('admin_all', $locale), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="product"><?= htmlspecialchars(catalog__('nav_products', $locale), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="category"><?= htmlspecialchars(catalog__('nav_categories', $locale), ENT_QUOTES, 'UTF-8') ?></option>
        </select>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-admin-refresh="admin:page-refresh"><?= htmlspecialchars(catalog__('admin_refresh', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm btn-primary" id="sfCreateToggle"><?= htmlspecialchars(catalog__('admin_create', $locale), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <div id="entityList"></div>
</div>
<div class="admin-panel mt-3" id="sfCreatePanel" hidden>
    <form id="sfCreateForm">
        <div class="admin-form-grid">
            <div><label class="form-label"><?= htmlspecialchars(catalog__('field_name', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="name" required></div>
            <div><label class="form-label"><?= htmlspecialchars(catalog__('field_entity_type', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="entity_type" value="product" required></div>
        </div>
        <label class="form-label mt-2"><?= htmlspecialchars(catalog__('admin_filter_json', $locale), ENT_QUOTES, 'UTF-8') ?></label>
        <textarea class="form-control font-monospace" name="filter_json" rows="4">{}</textarea>
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-sm btn-primary"><?= htmlspecialchars(catalog__('admin_save', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </form>
</div>
<div class="admin-panel mt-3" id="entityDetailPanel" hidden>
    <div id="entityDetail"></div>
</div>
