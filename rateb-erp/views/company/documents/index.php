<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? __('documents')); ?></div>
    <div class="rateb-card-body">
        <?php if ($canManage ?? rateb_can_manage_entity('documents')) { ?>
        <form method="post" action="<?php echo rateb_app_url('documents'); ?>" enctype="multipart/form-data" class="row g-3 mb-4">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="col-md-4">
                <label class="form-label"><?php echo __('title'); ?></label>
                <input class="form-control" name="title" required>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo __('entity_type'); ?></label>
                <select class="form-select" name="entity_type">
                    <option value="general">general</option>
                    <option value="contract">contract</option>
                    <option value="supplier">supplier</option>
                    <option value="device">device</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo __('entity_id'); ?></label>
                <input class="form-control" type="number" name="entity_id" value="0">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo __('file'); ?></label>
                <input class="form-control" type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><?php echo __('upload'); ?></button>
            </div>
        </form>
        <?php } ?>
        <div class="table-responsive">
            <table class="table rateb-table">
                <thead><tr><th><?php echo __('title'); ?></th><th><?php echo __('entity_type'); ?></th><th><?php echo __('file_name'); ?></th><th><?php echo __('created_at'); ?></th><th><?php echo __('actions'); ?></th></tr></thead>
                <tbody>
                <?php if (empty($items)) { ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else { foreach ($items as $row) { ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape($row['title'] ?? ''); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['entity_type'] ?? ''); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['file_name'] ?? ''); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['created_at'] ?? ''); ?></td>
                    <td>
                        <a href="<?php echo rateb_url('documents/download/' . (int) ($row['id'] ?? 0)); ?>" class="btn btn-sm btn-outline-primary">
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
