<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<p class="text-muted small mb-3"><?php echo __('fiscal_periods_help'); ?></p>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('fiscal_periods'); ?></div>
    <div class="rateb-card-body p-0">
        <table class="table rateb-table mb-0">
            <thead>
            <tr>
                <th><?php echo __('name'); ?></th>
                <th><?php echo __('date_from'); ?></th>
                <th><?php echo __('date_to'); ?></th>
                <th><?php echo __('status'); ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($items)) { ?>
            <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
            <?php } else { foreach ($items as $row) {
                $st = (string) ($row['status'] ?? '');
                ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape($row['name']); ?></td>
                <td><?php echo Rateb\App\Core\View::escape($row['start_date']); ?></td>
                <td><?php echo Rateb\App\Core\View::escape($row['end_date']); ?></td>
                <td><span class="badge bg-<?php echo $st === 'open' ? 'success' : 'secondary'; ?>"><?php echo __($st); ?></span></td>
                <td>
                    <?php if (($canPost ?? false) && $st === 'open') { ?>
                    <form method="post" action="<?php echo rateb_app_url('fiscal-periods/' . (int) $row['id'] . '/close'); ?>" class="d-inline"
                          onsubmit="return confirm('<?php echo __('fiscal_period_close_confirm'); ?>');">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-warning"><?php echo __('close_period'); ?></button>
                    </form>
                    <?php } elseif (($canPost ?? false) && $st === 'closed') { ?>
                    <form method="post" action="<?php echo rateb_app_url('fiscal-periods/' . (int) $row['id'] . '/reopen'); ?>" class="d-inline"
                          onsubmit="return confirm('<?php echo __('fiscal_period_reopen_confirm'); ?>');">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-success"><?php echo __('reopen_period'); ?></button>
                    </form>
                    <?php } ?>
                </td>
            </tr>
            <?php } } ?>
            </tbody>
        </table>
    </div>
</div>
