<div class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_versions', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
    </div>
    <form id="versionsLoadForm" class="admin-toolbar">
        <input class="form-control form-control-sm" name="product_uuid" id="versionsProductUuid" placeholder="<?= htmlspecialchars(catalog__('field_product_uuid', $locale), ENT_QUOTES, 'UTF-8') ?>" required>
        <button type="submit" class="btn btn-sm btn-outline-secondary"><?= htmlspecialchars(catalog__('admin_load', $locale), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
    <div id="versionsList"></div>
    <form id="versionsCompareForm" class="admin-toolbar mt-3" hidden>
        <input class="form-control form-control-sm" name="left" placeholder="<?= htmlspecialchars(catalog__('admin_version_a', $locale), ENT_QUOTES, 'UTF-8') ?>" required>
        <input class="form-control form-control-sm" name="right" placeholder="<?= htmlspecialchars(catalog__('admin_version_b', $locale), ENT_QUOTES, 'UTF-8') ?>" required>
        <button type="submit" class="btn btn-sm btn-outline-secondary"><?= htmlspecialchars(catalog__('admin_compare', $locale), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
    <div id="versionsDetail"></div>
</div>
