<div class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_media', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
    </div>
    <form id="mediaLoadForm" class="admin-toolbar">
        <input class="form-control form-control-sm" name="product_uuid" id="mediaProductUuid" placeholder="Product UUID" required>
        <button type="submit" class="btn btn-sm btn-outline-secondary">Load</button>
    </form>
    <div class="admin-split mt-3">
        <section>
            <h2 class="h6">Images</h2>
            <div id="mediaImages"></div>
            <form id="mediaImageForm" class="mt-2" enctype="multipart/form-data">
                <input type="file" class="form-control form-control-sm" name="file" accept="image/*" required>
                <button type="submit" class="btn btn-sm btn-primary mt-2"><?= htmlspecialchars(catalog__('admin_create', $locale), ENT_QUOTES, 'UTF-8') ?></button>
            </form>
        </section>
        <section>
            <h2 class="h6">Files</h2>
            <div id="mediaFiles"></div>
            <form id="mediaFileForm" class="mt-2" enctype="multipart/form-data">
                <input type="file" class="form-control form-control-sm" name="file" required>
                <button type="submit" class="btn btn-sm btn-primary mt-2"><?= htmlspecialchars(catalog__('admin_create', $locale), ENT_QUOTES, 'UTF-8') ?></button>
            </form>
        </section>
    </div>
    <div class="mt-3">
        <h2 class="h6">Videos</h2>
        <div id="mediaVideos"></div>
        <form id="mediaVideoForm" class="admin-form-grid mt-2">
            <div><label class="form-label">URL</label><input class="form-control" name="url" required></div>
            <div><label class="form-label">Title</label><input class="form-control" name="title"></div>
            <div class="admin-form-actions"><button type="submit" class="btn btn-sm btn-primary"><?= htmlspecialchars(catalog__('admin_create', $locale), ENT_QUOTES, 'UTF-8') ?></button></div>
        </form>
    </div>
    <div class="mt-3">
        <h2 class="h6">Asset types</h2>
        <div id="assetTypes"></div>
    </div>
</div>
