<div class="modal fade" id="ratebEntityDocsModal" tabindex="-1" aria-labelledby="ratebEntityDocsModalLabel" aria-hidden="true">
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
