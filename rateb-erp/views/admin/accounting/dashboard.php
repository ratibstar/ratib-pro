<?php $trial = $trial ?? []; ?>
<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="<?php echo rateb_url('admin/chart-of-accounts'); ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-list"></i> <?php echo __('chart_of_accounts'); ?></a>
    <a href="<?php echo rateb_url('admin/journal-entries'); ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-book"></i> <?php echo __('journal_entries'); ?></a>
    <form method="post" action="<?php echo rateb_url('admin/accounting/sync'); ?>" class="d-inline">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-sync"></i> <?php echo __('accounting_sync'); ?></button>
    </form>
</div>
<p class="text-muted small mb-3"><?php echo __('accounting_sync_help'); ?></p>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('trial_balance'); ?></div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('code'); ?></th>
                    <th><?php echo __('name'); ?></th>
                    <th><?php echo __('account_type'); ?></th>
                    <th class="text-end"><?php echo __('debit'); ?></th>
                    <th class="text-end"><?php echo __('credit'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($trial)) { ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else { foreach ($trial as $row) {
                    $name = rateb_locale() === 'ar' && !empty($row['name_ar']) ? $row['name_ar'] : $row['name'];
                    ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape($row['code']); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($name); ?></td>
                    <td><?php echo __( (string) ($row['account_type'] ?? '')); ?></td>
                    <td class="text-end"><?php echo number_format((float) $row['total_debit'], 2); ?></td>
                    <td class="text-end"><?php echo number_format((float) $row['total_credit'], 2); ?></td>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
