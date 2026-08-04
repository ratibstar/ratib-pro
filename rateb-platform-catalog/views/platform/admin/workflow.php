<div class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_workflow', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
    </div>
    <form id="workflowLoadForm" class="admin-toolbar">
        <input class="form-control form-control-sm" name="product_uuid" id="workflowProductUuid" placeholder="<?= htmlspecialchars(catalog__('field_product_uuid', $locale), ENT_QUOTES, 'UTF-8') ?>" required>
        <button type="submit" class="btn btn-sm btn-outline-secondary"><?= htmlspecialchars(catalog__('admin_load', $locale), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
    <div class="admin-form-actions" id="workflowActions" hidden>
        <button type="button" class="btn btn-sm btn-outline-primary" data-wf="submit"><?= htmlspecialchars(catalog__('wf_submit', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm btn-success" data-wf="approve"><?= htmlspecialchars(catalog__('wf_approve', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm btn-outline-danger" data-wf="reject"><?= htmlspecialchars(catalog__('wf_reject', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm btn-primary" data-wf="publish"><?= htmlspecialchars(catalog__('wf_publish', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-wf="archive"><?= htmlspecialchars(catalog__('wf_archive', $locale), ENT_QUOTES, 'UTF-8') ?></button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-wf="restore"><?= htmlspecialchars(catalog__('wf_restore', $locale), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <div class="admin-split mt-3">
        <section>
            <h2 class="h6"><?= htmlspecialchars(catalog__('admin_history', $locale), ENT_QUOTES, 'UTF-8') ?></h2>
            <div id="workflowHistory"></div>
        </section>
        <section>
            <h2 class="h6"><?= htmlspecialchars(catalog__('admin_comments', $locale), ENT_QUOTES, 'UTF-8') ?></h2>
            <div id="workflowComments"></div>
            <form id="workflowCommentForm" class="mt-2" hidden>
                <textarea class="form-control" name="body" rows="3" required></textarea>
                <button type="submit" class="btn btn-sm btn-primary mt-2"><?= htmlspecialchars(catalog__('admin_save', $locale), ENT_QUOTES, 'UTF-8') ?></button>
            </form>
        </section>
    </div>
</div>
