<div class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_webhooks', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="admin-toolbar-spacer"></div>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-admin-refresh="admin:page-refresh"><?= htmlspecialchars(catalog__('admin_refresh', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm btn-primary" id="webhookCreateToggle"><?= htmlspecialchars(catalog__('admin_create', $locale), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <div id="entityList"></div>
</div>
<div class="admin-panel mt-3" id="webhookCreatePanel" hidden>
    <form id="webhookCreateForm">
        <div class="admin-form-grid">
            <div><label class="form-label"><?= htmlspecialchars(catalog__('admin_https_url', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="url" placeholder="https://" required></div>
            <div><label class="form-label"><?= htmlspecialchars(catalog__('admin_events_comma', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="events" value="product.updated"></div>
            <div><label class="form-label"><?= htmlspecialchars(catalog__('field_secret', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="secret"></div>
            <div><label class="form-label"><?= htmlspecialchars(catalog__('field_erp_company_id', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="erp_company_id"></div>
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-sm btn-primary"><?= htmlspecialchars(catalog__('admin_save', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </form>
</div>
<div class="admin-panel mt-3" id="entityDetailPanel" hidden>
    <div id="entityDetail"></div>
    <form id="webhookUpdateForm" class="mt-3" hidden>
        <div class="admin-form-grid">
            <div><label class="form-label"><?= htmlspecialchars(catalog__('admin_https_url', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="url" id="whUrl"></div>
            <div><label class="form-label"><?= htmlspecialchars(catalog__('admin_events_comma', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="events" id="whEvents"></div>
            <div><label class="form-label"><?= htmlspecialchars(catalog__('admin_active_flag', $locale), ENT_QUOTES, 'UTF-8') ?></label><input class="form-control" name="is_active" id="whActive" value="1"></div>
        </div>
        <input type="hidden" name="uuid" id="whUuid">
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-sm btn-primary"><?= htmlspecialchars(catalog__('admin_save', $locale), ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" class="btn btn-sm btn-outline-danger" id="webhookDeleteBtn"><?= htmlspecialchars(catalog__('admin_delete', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </form>
</div>
