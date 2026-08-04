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
        <a class="btn btn-sm btn-warning" href="<?= htmlspecialchars($repairUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(catalog__('admin_repair_arabic', $locale), ENT_QUOTES, 'UTF-8') ?></a>
        <input type="search" class="form-control form-control-sm" id="productSku" placeholder="<?= htmlspecialchars(catalog__('field_sku', $locale), ENT_QUOTES, 'UTF-8') ?>">
        <select class="form-select form-select-sm" id="productCategory" aria-label="<?= htmlspecialchars(catalog__('admin_filter_category', $locale), ENT_QUOTES, 'UTF-8') ?>">
            <option value=""><?= htmlspecialchars(catalog__('admin_all_categories', $locale), ENT_QUOTES, 'UTF-8') ?></option>
        </select>
        <select class="form-select form-select-sm" id="productStatus" aria-label="<?= htmlspecialchars(catalog__('field_status', $locale), ENT_QUOTES, 'UTF-8') ?>">
            <option value=""><?= htmlspecialchars(catalog__('field_status', $locale), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="draft"><?= htmlspecialchars(catalog__('status_draft', $locale), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="pending_review"><?= htmlspecialchars(catalog__('status_pending_review', $locale), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="approved"><?= htmlspecialchars(catalog__('status_approved', $locale), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="published"><?= htmlspecialchars(catalog__('status_published', $locale), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="archived"><?= htmlspecialchars(catalog__('status_archived', $locale), ENT_QUOTES, 'UTF-8') ?></option>
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
            <div><label class="form-label"><?= htmlspecialchars(catalog__('field_sku', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="sku" required></div>
            <div>
                <label class="form-label"><?= htmlspecialchars(catalog__('field_category', $locale), ENT_QUOTES, 'UTF-8') ?></label>
                <select class="form-select" name="category_uuid" id="productCreateCategory" required>
                    <option value=""><?= htmlspecialchars(catalog__('admin_all_categories', $locale), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </div>
            <div><label class="form-label"><?= htmlspecialchars(catalog__('field_unit', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="unit_uuid" required></div>
            <div><label class="form-label"><?= htmlspecialchars(catalog__('field_brand', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="brand_uuid"></div>
            <div><label class="form-label"><?= htmlspecialchars(catalog__('field_family', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="family_uuid"></div>
            <div><label class="form-label"><?= htmlspecialchars(catalog__('field_name', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="name" required></div>
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
    <div class="mb-3" id="productDetailMeta"></div>
    <form id="productEditForm">
        <input type="hidden" name="uuid" id="productUuid">
        <input type="hidden" name="lock_version" id="productLockVersion">
        <div class="admin-form-grid">
            <div><label class="form-label"><?= htmlspecialchars(catalog__('field_sku', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="sku" id="productEditSku"></div>
            <div><label class="form-label"><?= htmlspecialchars(catalog__('field_status', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="status" id="productEditStatus" readonly></div>
            <div><label class="form-label"><?= htmlspecialchars(catalog__('field_name', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="name" id="productEditName"></div>
            <div><label class="form-label"><?= htmlspecialchars(catalog__('field_barcode', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="primary_barcode" id="productEditBarcode"></div>
            <div class="span-2"><label class="form-label"><?= htmlspecialchars(catalog__('field_short_description', $locale), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" name="short_description" id="productEditShort" rows="2"></textarea></div>
            <div class="span-2"><label class="form-label"><?= htmlspecialchars(catalog__('field_description', $locale), ENT_QUOTES, 'UTF-8') ?></label><textarea class="form-control" name="description" id="productEditDescription" rows="3"></textarea></div>
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary btn-sm"><?= htmlspecialchars(catalog__('admin_save', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </form>
</div>
