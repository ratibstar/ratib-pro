<?php
/** Company journal list */
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h5>
    <?php if ($canManage ?? false) { ?>
    <a href="<?php echo rateb_app_url('journal-entries/create'); ?>" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> <?php echo __('new_journal_entry'); ?>
    </a>
    <?php } ?>
</div>
<div class="rateb-card">
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('entry_no'); ?></th>
                    <th><?php echo __('evaluation_date'); ?></th>
                    <th><?php echo __('description'); ?></th>
                    <th><?php echo __('source_type'); ?></th>
                    <th><?php echo __('status'); ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($items)) { ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else { foreach ($items as $row) {
                    $desc = rateb_locale() === 'ar' && !empty($row['description_ar']) ? $row['description_ar'] : $row['description'];
                    $st = (string) ($row['status'] ?? '');
                    ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape($row['entry_no']); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['entry_date']); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($desc); ?></td>
                    <td><?php echo __((string) ($row['source_type'] ?? '')); ?></td>
                    <td><span class="badge bg-<?php echo $st === 'posted' ? 'success' : ($st === 'void' ? 'secondary' : 'warning'); ?>"><?php echo __($st); ?></span></td>
                    <td><a href="<?php echo rateb_app_url('journal-entries/' . (int) $row['id']); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
