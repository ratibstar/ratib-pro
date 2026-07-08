<div class="admin-split">
    <section class="admin-panel">
        <h1 class="h5"><?= htmlspecialchars(catalog__('nav_import_export', $locale), ENT_QUOTES, 'UTF-8') ?> — Import batch</h1>
        <form id="importCreateForm" class="mt-3">
            <div class="admin-form-grid">
                <div><label class="form-label">Source code</label><input class="form-control" name="source_code" value="manual" required></div>
                <div><label class="form-label">Source file path</label><input class="form-control" name="source_file_path"></div>
            </div>
            <label class="form-label mt-2">Rows JSON array</label>
            <textarea class="form-control font-monospace" name="rows_json" rows="6" required>[{"sku":"DEMO-1","name":"Demo"}]</textarea>
            <div class="admin-form-actions">
                <button type="submit" class="btn btn-sm btn-primary"><?= htmlspecialchars(catalog__('admin_create', $locale), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </form>
        <form id="importBatchForm" class="admin-toolbar mt-3">
            <input class="form-control form-control-sm" name="batch_uuid" id="importBatchUuid" placeholder="Batch UUID" required>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="importValidateBtn">Validate</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="importPreviewBtn">Preview</button>
            <button type="button" class="btn btn-sm btn-primary" id="importCommitBtn">Commit</button>
            <button type="button" class="btn btn-sm btn-outline-danger" id="importRollbackBtn">Rollback</button>
        </form>
        <div id="importResult"></div>
    </section>
    <section class="admin-panel">
        <h2 class="h5">Bulk jobs</h2>
        <form id="bulkExportForm" class="mt-3">
            <label class="form-label">Export filter JSON</label>
            <textarea class="form-control font-monospace" name="payload" rows="4">{"limit":100}</textarea>
            <button type="submit" class="btn btn-sm btn-outline-secondary mt-2">Export</button>
        </form>
        <form id="bulkPublishForm" class="mt-3">
            <label class="form-label">Publish product UUIDs JSON</label>
            <textarea class="form-control font-monospace" name="payload" rows="3">{"product_uuids":[]}</textarea>
            <button type="submit" class="btn btn-sm btn-primary mt-2">Bulk publish</button>
        </form>
        <form id="bulkArchiveForm" class="mt-3">
            <label class="form-label">Archive product UUIDs JSON</label>
            <textarea class="form-control font-monospace" name="payload" rows="3">{"product_uuids":[]}</textarea>
            <button type="submit" class="btn btn-sm btn-outline-danger mt-2">Bulk archive</button>
        </form>
        <div id="bulkResult" class="mt-3"></div>
    </section>
</div>
