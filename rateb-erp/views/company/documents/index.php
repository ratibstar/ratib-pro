<?php
use Rateb\App\Services\FormLookupService;

$docFields = FormLookupService::documentUploadFormFields();
$lookups = (new FormLookupService())->forFields($docFields);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? __('documents')); ?></div>
    <div class="rateb-card-body">
        <?php if ($canManage ?? rateb_can_manage_entity('documents')) { ?>
        <form method="post" action="<?php echo rateb_app_url('documents'); ?>" enctype="multipart/form-data" class="row g-3 mb-4">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="col-md-4">
                <label class="form-label"><?php echo __('title'); ?></label>
                <input class="form-control rateb-form-control" name="title" required>
            </div>
            <?php foreach ($docFields as $field) {
                Rateb\App\Core\View::partial('form-field', [
                    'field' => $field,
                    'value' => $field['default'] ?? '',
                    'lookups' => $lookups,
                ]);
            } ?>
            <div class="col-md-3">
                <label class="form-label"><?php echo __('file'); ?></label>
                <input class="form-control rateb-form-control" type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><?php echo __('upload'); ?></button>
            </div>
        </form>
        <?php } ?>
        <?php Rateb\App\Core\View::partial('table-search', ['mode' => 'client']); ?>
        <div class="table-responsive" data-rateb-table-search-host="1">
            <table class="table rateb-table mb-0">
                <thead><tr><th><?php echo __('title'); ?></th><th><?php echo __('entity_type'); ?></th><th><?php echo __('file_name'); ?></th><th><?php echo __('created_at'); ?></th><th><?php echo __('actions'); ?></th></tr></thead>
                <tbody>
                <?php if (empty($items)) { ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else { foreach ($items as $row) { ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape($row['title'] ?? ''); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape(__($row['entity_type'] ?? 'general')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['file_name'] ?? ''); ?></td>
                    <td><?php echo Rateb\App\Core\View::formatDate($row['created_at'] ?? ''); ?></td>
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
