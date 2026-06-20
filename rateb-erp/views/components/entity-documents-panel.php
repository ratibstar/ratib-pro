<?php
/** @var array<string, mixed> $item */
/** @var string $entityType */
/** @var int $entityId */
/** @var int $companyId */
/** @var array<int, array<string, mixed>> $documents */
/** @var string $routePrefix */
/** @var string $backLabel */
/** @var string $csrf */
/** @var bool $canManage */
/** @var bool $modalMode */
$backLabel = (string) ($backLabel ?? __($entityName ?? 'record'));
$modalMode = !empty($modalMode);
$panelId = 'rateb-doc-panel-' . (int) $entityId;
?>
<div class="rateb-entity-docs-panel" id="<?php echo Rateb\App\Core\View::escape($panelId); ?>" data-entity-id="<?php echo (int) $entityId; ?>">
    <?php if ($canManage ?? false) { ?>
    <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $entityId . '/documents'); ?>"
          enctype="multipart/form-data" class="row g-3 mb-4" data-entity-docs-upload>
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <?php if ($modalMode) { ?>
        <input type="hidden" name="rateb_doc_modal" value="1">
        <?php } ?>
        <div class="col-md-5">
            <label class="form-label"><?php echo __('title'); ?></label>
            <input class="form-control" name="doc_title" value="<?php echo Rateb\App\Core\View::escape($backLabel); ?>">
        </div>
        <div class="col-md-5">
            <label class="form-label"><?php echo __('file'); ?></label>
            <input class="form-control" type="file" name="entity_attachment" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-upload"></i> <?php echo __('upload'); ?></button>
        </div>
    </form>
    <?php } ?>
    <div class="table-responsive">
        <table class="table rateb-table mb-0">
            <thead>
            <tr>
                <th><?php echo __('title'); ?></th>
                <th><?php echo __('file_name'); ?></th>
                <th><?php echo __('file_size'); ?></th>
                <th><?php echo __('created_at'); ?></th>
                <th class="rateb-doc-actions-th"><?php echo __('actions'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($documents)) { ?>
            <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_files'); ?></td></tr>
            <?php } else { foreach ($documents as $doc) {
                $docId = (int) ($doc['id'] ?? 0);
            ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape($doc['title'] ?? ''); ?></td>
                <td><?php echo Rateb\App\Core\View::escape($doc['file_name'] ?? ''); ?></td>
                <td><?php echo number_format((int) ($doc['file_size'] ?? 0) / 1024, 1); ?> KB</td>
                <td><?php echo Rateb\App\Core\View::escape($doc['created_at'] ?? ''); ?></td>
                <td class="rateb-actions rateb-doc-actions">
                    <a href="<?php echo rateb_url('documents/view/' . $docId); ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" title="<?php echo __('view_file'); ?>">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="<?php echo rateb_url('documents/download/' . $docId); ?>" class="btn btn-sm btn-outline-primary" title="<?php echo __('download_file'); ?>">
                        <i class="fas fa-download"></i>
                    </a>
                    <?php if ($canManage ?? false) { ?>
                    <button type="button" class="btn btn-sm btn-outline-warning js-edit-doc"
                            data-doc-id="<?php echo $docId; ?>"
                            data-doc-title="<?php echo Rateb\App\Core\View::escape($doc['title'] ?? ''); ?>"
                            data-doc-file="<?php echo Rateb\App\Core\View::escape($doc['file_name'] ?? ''); ?>"
                            title="<?php echo __('edit_file'); ?>">
                        <i class="fas fa-edit"></i>
                    </button>
                    <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $entityId . '/documents/' . $docId . '/delete'); ?>"
                          class="d-inline" data-entity-docs-delete
                          data-confirm="<?php echo Rateb\App\Core\View::escape(__('confirm_delete_file')); ?>">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                        <?php if ($modalMode) { ?>
                        <input type="hidden" name="rateb_doc_modal" value="1">
                        <?php } ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="<?php echo __('delete_file'); ?>">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                    <?php } ?>
                </td>
            </tr>
            <?php } } ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($canManage ?? false) { ?>
<div class="modal fade rateb-edit-doc-modal" id="ratebEditDocModal-<?php echo (int) $entityId; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content rateb-edit-doc-form" action="" enctype="multipart/form-data"
              data-route-prefix="<?php echo Rateb\App\Core\View::escape(rateb_url($routePrefix . '/' . $entityId . '/documents/')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <?php if ($modalMode) { ?>
            <input type="hidden" name="rateb_doc_modal" value="1">
            <?php } ?>
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> <?php echo __('edit_file'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label"><?php echo __('title'); ?></label>
                    <input class="form-control rateb-edit-doc-title" name="doc_title" required>
                </div>
                <div class="mb-2">
                    <label class="form-label text-muted small"><?php echo __('current_file'); ?></label>
                    <div class="small text-muted rateb-edit-doc-current"></div>
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
<?php } ?>

<?php if (!$modalMode && ($canManage ?? false)) { ?>
<script>
(function () {
    var panel = document.getElementById(<?php echo json_encode($panelId); ?>);
    if (!panel) {
        return;
    }
    var editModalEl = panel.parentElement.querySelector('.rateb-edit-doc-modal');
    if (!editModalEl) {
        return;
    }
    var form = editModalEl.querySelector('.rateb-edit-doc-form');
    var titleInput = editModalEl.querySelector('.rateb-edit-doc-title');
    var fileLabel = editModalEl.querySelector('.rateb-edit-doc-current');
    panel.querySelectorAll('.js-edit-doc').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var docId = btn.getAttribute('data-doc-id');
            titleInput.value = btn.getAttribute('data-doc-title') || '';
            fileLabel.textContent = btn.getAttribute('data-doc-file') || '';
            form.action = form.getAttribute('data-route-prefix') + docId;
            bootstrap.Modal.getOrCreateInstance(editModalEl).show();
        });
    });
})();
</script>
<?php } ?>
