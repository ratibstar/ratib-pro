<div class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_collections', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="admin-toolbar-spacer"></div>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-admin-refresh="admin:page-refresh"><?= htmlspecialchars(catalog__('admin_refresh', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm btn-primary" id="collectionCreateToggle"><?= htmlspecialchars(catalog__('admin_create', $locale), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <div id="entityList"></div>
</div>
<div class="admin-panel mt-3" id="collectionCreatePanel" hidden>
    <form id="collectionCreateForm">
        <div class="admin-form-grid">
            <div><label class="form-label">Slug</label><input class="form-control" name="slug" required></div>
            <div><label class="form-label">Name</label><input class="form-control" name="name" required></div>
            <div><label class="form-label">Type</label><input class="form-control" name="collection_type" value="manual"></div>
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-sm btn-primary"><?= htmlspecialchars(catalog__('admin_save', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </form>
</div>
<div class="admin-panel mt-3" id="entityDetailPanel" hidden>
    <div id="entityDetail"></div>
    <h3 class="h6 mt-3">Products</h3>
    <div id="collectionProducts"></div>
</div>
