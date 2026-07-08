<div class="admin-split">
    <section class="admin-panel">
        <h1 class="h5"><?= htmlspecialchars(catalog__('nav_settings', $locale), ENT_QUOTES, 'UTF-8') ?> — RBAC</h1>
        <button type="button" class="btn btn-sm btn-outline-secondary mb-3" data-admin-refresh="admin:page-refresh"><?= htmlspecialchars(catalog__('admin_refresh', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        <div id="rolesList"></div>
        <form id="userRolesForm" class="mt-3">
            <label class="form-label">User UUID</label>
            <div class="admin-toolbar">
                <input class="form-control form-control-sm" name="user_uuid" id="settingsUserUuid" required>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="loadUserRolesBtn">Load roles</button>
            </div>
            <label class="form-label mt-2">Role UUIDs (comma-separated)</label>
            <textarea class="form-control" name="role_uuids" id="settingsRoleUuids" rows="3"></textarea>
            <button type="submit" class="btn btn-sm btn-primary mt-2"><?= htmlspecialchars(catalog__('admin_save', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
        <div id="userRolesResult" class="mt-3"></div>
    </section>
    <section class="admin-panel">
        <h2 class="h5">Completeness rules</h2>
        <div id="completenessRules"></div>
        <form id="completenessForm" class="mt-3" hidden>
            <div class="admin-form-grid">
                <div><label class="form-label">Code</label><input class="form-control" name="code" id="crCode" readonly></div>
                <div><label class="form-label">Weight</label><input class="form-control" name="weight" id="crWeight"></div>
                <div><label class="form-label">Active (1/0)</label><input class="form-control" name="is_active" id="crActive"></div>
            </div>
            <div class="admin-form-actions">
                <button type="submit" class="btn btn-sm btn-primary"><?= htmlspecialchars(catalog__('admin_save', $locale), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </form>
        <p class="admin-muted mt-3 mb-0"><?= htmlspecialchars(catalog__('admin_no_api', $locale), ENT_QUOTES, 'UTF-8') ?> — general settings key/value store is not exposed; manage RBAC + completeness via existing admin APIs.</p>
    </section>
</div>
