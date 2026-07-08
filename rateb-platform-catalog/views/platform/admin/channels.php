<div class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_channels', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="admin-toolbar-spacer"></div>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-admin-refresh="admin:page-refresh"><?= htmlspecialchars(catalog__('admin_refresh', $locale), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <div id="entityList"></div>
</div>
<div class="admin-panel mt-3">
    <h2 class="h5 mb-3">Product channel assignments</h2>
    <form id="channelAssignForm" class="admin-toolbar">
        <input class="form-control form-control-sm" name="product_uuid" placeholder="Product UUID" required>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="channelLoadBtn">Load</button>
    </form>
    <div id="channelProductDetail"></div>
    <form id="channelReplaceForm" class="mt-3" hidden>
        <label class="form-label">Channel UUIDs (comma-separated) or JSON array</label>
        <textarea class="form-control" name="channels" id="channelReplaceInput" rows="4"></textarea>
        <input type="hidden" name="product_uuid" id="channelProductUuid">
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary btn-sm"><?= htmlspecialchars(catalog__('admin_save', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </form>
</div>
