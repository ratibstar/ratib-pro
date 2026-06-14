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
        <?php if (!empty($item['document_path'])) { ?>
        <div class="alert alert-info py-2 small mb-3">
            <i class="fas fa-file"></i>
            <?php echo __('legacy_document_path'); ?>:
            <code><?php echo Rateb\App\Core\View::escape((string) $item['document_path']); ?></code>
        </div>
        <?php } ?>
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
                <?php } else { foreach ($documents as $doc) { ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape($doc['title'] ?? ''); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($doc['file_name'] ?? ''); ?></td>
                    <td><?php echo number_format((int) ($doc['file_size'] ?? 0) / 1024, 1); ?> KB</td>
                    <td><?php echo Rateb\App\Core\View::escape($doc['created_at'] ?? ''); ?></td>
                    <td>
                        <a href="<?php echo rateb_url('documents/download/' . (int) ($doc['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-download"></i> <?php echo __('download_file'); ?>
                        </a>
                    </td>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
