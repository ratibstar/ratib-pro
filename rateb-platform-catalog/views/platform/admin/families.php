<div class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_families', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
        <span class="badge text-bg-secondary"><?= htmlspecialchars(catalog__('admin_read_only', $locale), ENT_QUOTES, 'UTF-8') ?></span>
        <div class="admin-toolbar-spacer"></div>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-admin-refresh="admin:page-refresh"><?= htmlspecialchars(catalog__('admin_refresh', $locale), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <div id="entityList"></div>
</div>
<div class="admin-panel mt-3" id="entityDetailPanel" hidden>
    <div id="entityDetail"></div>
    <h3 class="h6 mt-3">Family products</h3>
    <div id="familyProducts"></div>
</div>
