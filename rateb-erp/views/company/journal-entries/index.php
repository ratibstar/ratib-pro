<?php
/** Company journal list — same structure as admin without company column */
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('entry_no'); ?></th>
                    <th><?php echo __('evaluation_date'); ?></th>
                    <th><?php echo __('description'); ?></th>
                    <th><?php echo __('source_type'); ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($items)) { ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else { foreach ($items as $row) {
                    $desc = rateb_locale() === 'ar' && !empty($row['description_ar']) ? $row['description_ar'] : $row['description'];
                    ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape($row['entry_no']); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['entry_date']); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($desc); ?></td>
                    <td><?php echo __( (string) ($row['source_type'] ?? '')); ?></td>
                    <td><a href="<?php echo rateb_url('company/journal-entries/' . (int) $row['id']); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
