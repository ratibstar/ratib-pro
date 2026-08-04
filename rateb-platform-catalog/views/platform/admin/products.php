<?php
$repairNotice = isset($repairNotice) ? (string) $repairNotice : '';
$repairUrl = (string) (preg_replace('/\?.*$/', '', (string) ($_SERVER['REQUEST_URI'] ?? '/admin/products')) ?: '/admin/products');
$repairUrl .= (str_contains($repairUrl, '?') ? '&' : '?') . 'repair_arabic=1&lang=' . rawurlencode((string) ($locale ?? 'ar'));
?>
<div class="admin-panel">
    <?php if ($repairNotice !== '') { ?>
    <div class="alert alert-success mb-3" role="status"><?= htmlspecialchars($repairNotice, ENT_QUOTES, 'UTF-8') ?></div>
    <?php } ?>
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_products', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="admin-toolbar-spacer"></div>
        <a class="btn btn-sm btn-warning" href="<?= htmlspecialchars($repairUrl, ENT_QUOTES, 'UTF-8') ?>">إصلاح العربي ????</a>
        <input type="search" class="form-control form-control-sm" id="productSku" placeholder="SKU">
        <select class="form-select form-select-sm" id="productStatus">
            <option value="">Status</option>
            <option value="draft">draft</option>
            <option value="pending_review">pending_review</option>
            <option value="approved">approved</option>
            <option value="published">published</option>
            <option value="archived">archived</option>
        </select>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="productFilterBtn"><?= htmlspecialchars(catalog__('admin_refresh', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm btn-primary" id="productCreateToggle"><?= htmlspecialchars(catalog__('admin_create', $locale), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <div id="productList"></div>
</div>

<div class="admin-panel mt-3" id="productCreatePanel" hidden>
    <h2 class="h5 mb-3"><?= htmlspecialchars(catalog__('admin_create', $locale), ENT_QUOTES, 'UTF-8') ?></h2>
    <form id="productCreateForm">
        <div class="admin-form-grid">
            <div><label class="form-label">SKU</label><input class="form-control" name="sku" required></div>
            <div><label class="form-label">Category UUID</label><input class="form-control" name="category_uuid" required></div>
            <div><label class="form-label">Unit UUID</label><input class="form-control" name="unit_uuid" required></div>
            <div><label class="form-label">Brand UUID</label><input class="form-control" name="brand_uuid"></div>
            <div><label class="form-label">Family UUID</label><input class="form-control" name="family_uuid"></div>
            <div><label class="form-label">Name (locale)</label><input class="form-control" name="name" required></div>
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary btn-sm"><?= htmlspecialchars(catalog__('admin_save', $locale), ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="productCreateCancel"><?= htmlspecialchars(catalog__('admin_cancel', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </form>
</div>

<div class="admin-panel mt-3" id="productDetailPanel" hidden>
    <div class="admin-toolbar">
        <h2 class="h5 mb-0" id="productDetailTitle"><?= htmlspecialchars(catalog__('admin_edit', $locale), ENT_QUOTES, 'UTF-8') ?></h2>
        <div class="admin-toolbar-spacer"></div>
        <button type="button" class="btn btn-sm btn-outline-danger" id="productDeleteBtn"><?= htmlspecialchars(catalog__('admin_delete', $locale), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <form id="productEditForm">
        <input type="hidden" name="uuid" id="productUuid">
        <input type="hidden" name="lock_version" id="productLockVersion">
        <div class="admin-form-grid">
            <div><label class="form-label">SKU</label><input class="form-control" name="sku" id="productEditSku"></div>
            <div><label class="form-label">Status</label><input class="form-control" name="status" id="productEditStatus" readonly></div>
            <div><label class="form-label">Name</label><input class="form-control" name="name" id="productEditName"></div>
            <div><label class="form-label">Primary barcode</label><input class="form-control" name="primary_barcode" id="productEditBarcode"></div>
            <div class="span-2"><label class="form-label">Short description</label><textarea class="form-control" name="short_description" id="productEditShort" rows="2"></textarea></div>
            <div class="span-2"><label class="form-label">Description</label><textarea class="form-control" name="description" id="productEditDescription" rows="3"></textarea></div>
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary btn-sm"><?= htmlspecialchars(catalog__('admin_save', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </form>
    <div class="mt-3" id="productDetailJson"></div>
</div>
