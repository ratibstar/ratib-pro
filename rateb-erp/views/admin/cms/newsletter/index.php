<?php
/** @var array<int, array<string, mixed>> $campaigns */
/** @var array<int, array<string, mixed>> $items */
?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="<?php echo rateb_url('admin/cms/newsletter/export'); ?>" class="btn btn-outline-primary btn-sm"><?php echo __('cms_export_csv'); ?></a>
    <a href="<?php echo rateb_url('admin/cms/newsletter/campaign'); ?>" class="btn btn-primary btn-sm"><?php echo __('cms_new_campaign'); ?></a>
</div>

<div class="rateb-card mb-4">
    <div class="rateb-card-header"><?php echo __('cms_import_subscribers'); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo rateb_url('admin/cms/newsletter/import'); ?>" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="mb-3">
                <label class="form-label"><?php echo __('cms_csv_file'); ?></label>
                <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv">
            </div>
            <div class="mb-3">
                <label class="form-label"><?php echo __('cms_csv_paste'); ?></label>
                <textarea name="csv_text" class="form-control" rows="4" placeholder="email,name,segment"></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><?php echo __('cms_import'); ?></button>
        </form>
    </div>
</div>

<?php if (!empty($campaigns)) { ?>
<div class="rateb-card mb-4">
    <div class="rateb-card-header"><?php echo __('cms_campaigns'); ?></div>
    <div class="rateb-card-body table-responsive">
        <table class="table table-sm">
            <thead><tr><th>ID</th><th><?php echo __('subject'); ?></th><th><?php echo __('status'); ?></th><th><?php echo __('cms_sent_count'); ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($campaigns as $c) { ?>
            <tr>
                <td><?php echo (int) ($c['id'] ?? 0); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($c['subject_en'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($c['status'] ?? '')); ?></td>
                <td><?php echo (int) ($c['sent_count'] ?? 0); ?></td>
                <td class="text-nowrap">
                    <a href="<?php echo rateb_url('admin/cms/newsletter/campaign?id=' . (int) $c['id']); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('edit'); ?></a>
                    <?php if (($c['status'] ?? '') !== 'sent') { ?>
                    <form method="post" action="<?php echo rateb_url('admin/cms/newsletter/campaign/send'); ?>" class="d-inline">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                        <input type="hidden" name="id" value="<?php echo (int) ($c['id'] ?? 0); ?>">
                        <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('<?php echo __('cms_confirm_send'); ?>')"><?php echo __('cms_send_campaign'); ?></button>
                    </form>
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<?php } ?>

<?php Rateb\App\Core\View::partial('crud-index', get_defined_vars()); ?>
