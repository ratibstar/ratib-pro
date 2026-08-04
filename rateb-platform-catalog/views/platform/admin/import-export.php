<div class="admin-split">
    <section class="admin-panel">
        <h1 class="h5"><?= htmlspecialchars(catalog__('nav_import_export', $locale), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars(catalog__('admin_import_batch', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
        <form id="importCreateForm" class="mt-3">
            <div class="admin-form-grid">
                <div><label class="form-label"><?= htmlspecialchars(catalog__('field_source_code', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="source_code" value="manual" required></div>
                <div><label class="form-label"><?= htmlspecialchars(catalog__('field_source_file_path', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="source_file_path"></div>
            </div>
            <label class="form-label mt-2"><?= htmlspecialchars(catalog__('admin_rows_json', $locale), ENT_QUOTES, 'UTF-8') ?></label>
            <textarea class="form-control font-monospace" name="rows_json" rows="6" required>[{"sku":"DEMO-1","name":"Demo"}]</textarea>
            <div class="admin-form-actions">
                <button type="submit" class="btn btn-sm btn-primary"><?= htmlspecialchars(catalog__('admin_create', $locale), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </form>
        <form id="importBatchForm" class="admin-toolbar mt-3">
            <input class="form-control form-control-sm" name="batch_uuid" id="importBatchUuid" placeholder="<?= htmlspecialchars(catalog__('field_batch_uuid', $locale), ENT_QUOTES, 'UTF-8') ?>" required>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="importValidateBtn"><?= htmlspecialchars(catalog__('admin_validate', $locale), ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="importPreviewBtn"><?= htmlspecialchars(catalog__('admin_preview', $locale), ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" class="btn btn-sm btn-primary" id="importCommitBtn"><?= htmlspecialchars(catalog__('admin_commit', $locale), ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" class="btn btn-sm btn-outline-danger" id="importRollbackBtn"><?= htmlspecialchars(catalog__('admin_rollback', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
        <div id="importResult"></div>
    </section>
    <section class="admin-panel">
        <h2 class="h5"><?= htmlspecialchars(catalog__('admin_bulk_jobs', $locale), ENT_QUOTES, 'UTF-8') ?></h2>
        <form id="bulkExportForm" class="mt-3">
            <label class="form-label"><?= htmlspecialchars(catalog__('admin_export_filter_json', $locale), ENT_QUOTES, 'UTF-8') ?></label>
            <textarea class="form-control font-monospace" name="payload" rows="4">{"limit":100}</textarea>
            <button type="submit" class="btn btn-sm btn-outline-secondary mt-2"><?= htmlspecialchars(catalog__('admin_export', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
        <form id="bulkPublishForm" class="mt-3">
            <label class="form-label"><?= htmlspecialchars(catalog__('admin_publish_uuids_json', $locale), ENT_QUOTES, 'UTF-8') ?></label>
            <textarea class="form-control font-monospace" name="payload" rows="3">{"product_uuids":[]}</textarea>
            <button type="submit" class="btn btn-sm btn-primary mt-2"><?= htmlspecialchars(catalog__('admin_bulk_publish', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
        <form id="bulkArchiveForm" class="mt-3">
            <label class="form-label"><?= htmlspecialchars(catalog__('admin_archive_uuids_json', $locale), ENT_QUOTES, 'UTF-8') ?></label>
            <textarea class="form-control font-monospace" name="payload" rows="3">{"product_uuids":[]}</textarea>
            <button type="submit" class="btn btn-sm btn-outline-danger mt-2"><?= htmlspecialchars(catalog__('admin_bulk_archive', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
        <div id="bulkResult" class="mt-3"></div>
    </section>
</div>
