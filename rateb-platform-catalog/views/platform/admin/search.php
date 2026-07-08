<div class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_search', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="admin-toolbar-spacer"></div>
        <button type="button" class="btn btn-sm btn-outline-primary" id="searchReindexBtn">Reindex</button>
    </div>
    <form id="searchForm" class="admin-toolbar">
        <input class="form-control form-control-sm" name="q" id="searchQuery" placeholder="<?= htmlspecialchars(catalog__('admin_search_placeholder', $locale), ENT_QUOTES, 'UTF-8') ?>" required>
        <button type="submit" class="btn btn-sm btn-primary">Search</button>
    </form>
    <form id="barcodeForm" class="admin-toolbar">
        <input class="form-control form-control-sm" name="barcode" id="searchBarcode" placeholder="Barcode">
        <button type="submit" class="btn btn-sm btn-outline-secondary">Barcode lookup</button>
    </form>
    <div id="searchResults"></div>
</div>
