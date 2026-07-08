<div class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_seo', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
    </div>
    <form id="seoLoadForm" class="admin-toolbar">
        <input class="form-control form-control-sm" name="product_uuid" id="seoProductUuid" placeholder="Product UUID" required>
        <button type="submit" class="btn btn-sm btn-outline-secondary">Load</button>
    </form>
    <form id="seoSaveForm" hidden>
        <div class="admin-form-grid">
            <div><label class="form-label">Meta title</label><input class="form-control" name="meta_title" id="seoMetaTitle"></div>
            <div><label class="form-label">Slug</label><input class="form-control" name="slug" id="seoSlug"></div>
            <div class="span-2"><label class="form-label">Meta description</label><textarea class="form-control" name="meta_description" id="seoMetaDescription" rows="3"></textarea></div>
            <div><label class="form-label">Canonical URL</label><input class="form-control" name="canonical_url" id="seoCanonical"></div>
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-sm btn-primary"><?= htmlspecialchars(catalog__('admin_save', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </form>
    <div id="seoDetail" class="mt-3"></div>
</div>
