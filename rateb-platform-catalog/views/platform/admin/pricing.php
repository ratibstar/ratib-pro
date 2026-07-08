<div class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_pricing', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
    </div>
    <form id="pricingLoadForm" class="admin-toolbar">
        <input class="form-control form-control-sm" name="product_uuid" id="pricingProductUuid" placeholder="Product UUID" required>
        <button type="submit" class="btn btn-sm btn-outline-secondary">Load</button>
    </form>
    <div id="pricingList"></div>
    <form id="pricingSaveForm" class="mt-3" hidden>
        <label class="form-label">Prices JSON</label>
        <textarea class="form-control font-monospace" name="prices_json" id="pricingJson" rows="10"></textarea>
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary btn-sm"><?= htmlspecialchars(catalog__('admin_save', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </form>
</div>
