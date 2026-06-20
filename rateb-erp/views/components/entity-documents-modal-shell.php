<div class="modal fade rateb-entity-docs-modal" id="ratebEntityDocsModal" tabindex="-1" aria-labelledby="ratebEntityDocsModalLabel" aria-hidden="true" data-bs-focus="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ratebEntityDocsModalLabel">
                    <i class="fas fa-paperclip"></i>
                    <span data-entity-docs-title><?php echo __('entity_documents'); ?></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" data-entity-docs-body>
                <div class="text-center text-muted py-4"><?php echo __('loading'); ?>…</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('close'); ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade rateb-entity-edit-doc-modal" id="ratebEntityEditDocModal" tabindex="-1" aria-hidden="true" data-bs-focus="false">
    <div class="modal-dialog">
        <form method="post" class="modal-content" id="ratebEntityEditDocForm" enctype="multipart/form-data" action="">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape(\Rateb\App\Core\Csrf::token()); ?>">
            <input type="hidden" name="rateb_doc_modal" value="1">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> <?php echo __('edit_file'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label"><?php echo __('title'); ?></label>
                    <input class="form-control" name="doc_title" id="ratebEntityEditDocTitle" required>
                </div>
                <div class="mb-2">
                    <label class="form-label text-muted small"><?php echo __('current_file'); ?></label>
                    <div class="small text-muted" id="ratebEntityEditDocCurrent"></div>
                </div>
                <div class="mb-0">
                    <label class="form-label"><?php echo __('replace_file'); ?></label>
                    <input class="form-control" type="file" name="entity_attachment" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo __('save'); ?></button>
            </div>
        </form>
    </div>
</div>
