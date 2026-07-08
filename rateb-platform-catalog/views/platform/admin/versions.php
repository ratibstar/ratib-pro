<div class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_versions', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
    </div>
    <form id="versionsLoadForm" class="admin-toolbar">
        <input class="form-control form-control-sm" name="product_uuid" id="versionsProductUuid" placeholder="Product UUID" required>
        <button type="submit" class="btn btn-sm btn-outline-secondary">Load</button>
    </form>
    <div id="versionsList"></div>
    <form id="versionsCompareForm" class="admin-toolbar mt-3" hidden>
        <input class="form-control form-control-sm" name="left" placeholder="Version A" required>
        <input class="form-control form-control-sm" name="right" placeholder="Version B" required>
        <button type="submit" class="btn btn-sm btn-outline-secondary">Compare</button>
    </form>
    <div id="versionsDetail"></div>
</div>
