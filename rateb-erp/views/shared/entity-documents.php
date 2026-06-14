<?php
/** @var string $title */
/** @var array<string, mixed> $item */
/** @var string $entityType */
/** @var int $entityId */
/** @var int $companyId */
/** @var array<int, array<string, mixed>> $documents */
/** @var string $routePrefix */
/** @var string $backLabel */
/** @var string $csrf */
/** @var bool $canManage */
$backLabel = (string) ($backLabel ?? __($entityName ?? 'record'));
?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
            <i class="fas fa-paperclip"></i>
            <?php echo Rateb\App\Core\View::escape($title ?? __('entity_documents')); ?>
        </span>
        <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-right"></i> <?php echo Rateb\App\Core\View::escape($backLabel); ?>
        </a>
    </div>
    <div class="rateb-card-body">
        <?php if ($canManage ?? false) { ?>
        <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $entityId . '/documents'); ?>" enctype="multipart/form-data" class="row g-3 mb-4">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
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
                    <th><?php echo __('actions'); ?></th>
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
                    <td class="text-nowrap">
                        <div class="btn-group btn-group-sm" role="group">
                            <a href="<?php echo rateb_url('documents/view/' . $docId); ?>" class="btn btn-outline-secondary" target="_blank" rel="noopener" title="<?php echo __('view_file'); ?>">
                                <i class="fas fa-eye"></i> <?php echo __('view_file'); ?>
                            </a>
                            <a href="<?php echo rateb_url('documents/download/' . $docId); ?>" class="btn btn-outline-primary" title="<?php echo __('download_file'); ?>">
                                <i class="fas fa-download"></i> <?php echo __('download_file'); ?>
                            </a>
                            <?php if ($canManage ?? false) { ?>
                            <button type="button" class="btn btn-outline-warning js-edit-doc"
                                    data-doc-id="<?php echo $docId; ?>"
                                    data-doc-title="<?php echo Rateb\App\Core\View::escape($doc['title'] ?? ''); ?>"
                                    data-doc-file="<?php echo Rateb\App\Core\View::escape($doc['file_name'] ?? ''); ?>"
                                    title="<?php echo __('edit_file'); ?>">
                                <i class="fas fa-edit"></i> <?php echo __('edit_file'); ?>
                            </button>
                            <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $entityId . '/documents/' . $docId . '/delete'); ?>" class="d-inline" onsubmit="return confirm('<?php echo Rateb\App\Core\View::escape(__('confirm_delete_file')); ?>');">
                                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                                <button type="submit" class="btn btn-outline-danger" title="<?php echo __('delete_file'); ?>">
                                    <i class="fas fa-trash"></i> <?php echo __('delete_file'); ?>
                                </button>
                            </form>
                            <?php } ?>
                        </div>
                    </td>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($canManage ?? false) { ?>
<div class="modal fade" id="editDocModal" tabindex="-1" aria-labelledby="editDocModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" id="editDocForm" action="" enctype="multipart/form-data" class="modal-content">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="modal-header">
                <h5 class="modal-title" id="editDocModalLabel"><i class="fas fa-edit"></i> <?php echo __('edit_file'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label"><?php echo __('title'); ?></label>
                    <input class="form-control" name="doc_title" id="editDocTitle" required>
                </div>
                <div class="mb-2">
                    <label class="form-label text-muted small"><?php echo __('current_file'); ?></label>
                    <div id="editDocCurrentFile" class="small text-muted"></div>
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
<script>
(function () {
    var modalEl = document.getElementById('editDocModal');
    if (!modalEl) return;
    var form = document.getElementById('editDocForm');
    var titleInput = document.getElementById('editDocTitle');
    var fileLabel = document.getElementById('editDocCurrentFile');
    var baseUrl = <?php echo json_encode(rateb_url($routePrefix . '/' . $entityId . '/documents/')); ?>;
    document.querySelectorAll('.js-edit-doc').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var docId = btn.getAttribute('data-doc-id');
            titleInput.value = btn.getAttribute('data-doc-title') || '';
            fileLabel.textContent = btn.getAttribute('data-doc-file') || '';
            form.action = baseUrl + docId;
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    });
})();
</script>
<?php } ?>
