<div class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_audit_logs', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
    </div>
    <div class="alert alert-info mb-0" role="status">
        <?= htmlspecialchars(catalog__('admin_no_api', $locale), ENT_QUOTES, 'UTF-8') ?>
    </div>
    <p class="admin-muted mt-3 mb-0">Audit events are written by domain services (`AuditEventService`) but no `/catalog/audit*` HTTP route exists in architecture v1.3.1. Use product versions, workflow history, and change requests for governance trails available via current APIs.</p>
</div>
