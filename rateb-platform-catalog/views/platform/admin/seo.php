<div class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_seo', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
    </div>
    <form id="seoLoadForm" class="admin-toolbar">
        <input class="form-control form-control-sm" name="product_uuid" id="seoProductUuid" placeholder="<?= htmlspecialchars(catalog__('field_product_uuid', $locale), ENT_QUOTES, 'UTF-8') ?>" required>
        <button type="submit" class="btn btn-sm btn-outline-secondary"><?= htmlspecialchars(catalog__('admin_load', $locale), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
    <form id="seoSaveForm" hidden>
        <div class="admin-form-grid">
            <div><label class="form-label"><?= htmlspecialchars(catalog__('field_meta_title', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="meta_title" id="seoMetaTitle"></div>
            <div><label class="form-label"><?= htmlspecialchars(catalog__('field_slug', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="slug" id="seoSlug"></div>
            <div class="span-2"><label class="form-label"><?= htmlspecialchars(catalog__('field_meta_description', $locale), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" name="meta_description" id="seoMetaDescription" rows="3"></textarea></div>
            <div><label class="form-label"><?= htmlspecialchars(catalog__('field_canonical', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="canonical_url" id="seoCanonical"></div>
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-sm btn-primary"><?= htmlspecialchars(catalog__('admin_save', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </form>
    <div id="seoDetail" class="mt-3"></div>
</div>
