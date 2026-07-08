<div class="admin-panel">
    <div class="admin-toolbar">
        <h1 class="h4 mb-0"><?= htmlspecialchars(catalog__('nav_workflow', $locale), ENT_QUOTES, 'UTF-8') ?></h1>
    </div>
    <form id="workflowLoadForm" class="admin-toolbar">
        <input class="form-control form-control-sm" name="product_uuid" id="workflowProductUuid" placeholder="Product UUID" required>
        <button type="submit" class="btn btn-sm btn-outline-secondary">Load</button>
    </form>
    <div class="admin-form-actions" id="workflowActions" hidden>
        <button type="button" class="btn btn-sm btn-outline-primary" data-wf="submit">Submit</button>
        <button type="button" class="btn btn-sm btn-success" data-wf="approve">Approve</button>
        <button type="button" class="btn btn-sm btn-outline-danger" data-wf="reject">Reject</button>
        <button type="button" class="btn btn-sm btn-primary" data-wf="publish">Publish</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-wf="archive">Archive</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-wf="restore">Restore</button>
    </div>
    <div class="admin-split mt-3">
        <section>
            <h2 class="h6">History</h2>
            <div id="workflowHistory"></div>
        </section>
        <section>
            <h2 class="h6">Comments</h2>
            <div id="workflowComments"></div>
            <form id="workflowCommentForm" class="mt-2" hidden>
                <textarea class="form-control" name="body" rows="3" required></textarea>
                <button type="submit" class="btn btn-sm btn-primary mt-2"><?= htmlspecialchars(catalog__('admin_save', $locale), ENT_QUOTES, 'UTF-8') ?></button>
            </form>
        </section>
    </div>
</div>
