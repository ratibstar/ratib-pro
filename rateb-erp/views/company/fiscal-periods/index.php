<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted small mb-0"><?php echo __('fiscal_periods_help'); ?></p>
    <?php if ($canManage ?? false) { ?>
    <a href="<?php echo rateb_app_url('fiscal-periods/create'); ?>" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> <?php echo __('new_fiscal_period'); ?>
    </a>
    <?php } ?>
</div>
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
                <td><?php echo Rateb\App\Core\View::formatDate($row['start_date']); ?></td>
                <td><?php echo Rateb\App\Core\View::formatDate($row['end_date']); ?></td>
                <td><span class="badge bg-<?php echo $st === 'open' ? 'success' : 'secondary'; ?>"><?php echo __($st); ?></span></td>
                <td class="text-nowrap">
                    <a href="<?php echo rateb_app_url('fiscal-periods/' . (int) $row['id']); ?>" class="btn btn-sm btn-outline-info" title="<?php echo __('view'); ?>"><i class="fas fa-eye"></i></a>
                    <?php if (($canManage ?? false) && $st === 'open') { ?>
                    <form method="post" action="<?php echo rateb_app_url('fiscal-periods/' . (int) $row['id'] . '/delete'); ?>" class="d-inline"
                          data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('bulk_confirm_delete')); ?>">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </form>
                    <?php } ?>
                    <?php if (($canPost ?? false) && $st === 'open') { ?>
                    <form method="post" action="<?php echo rateb_app_url('fiscal-periods/' . (int) $row['id'] . '/close'); ?>" class="d-inline"
                          data-rateb-confirm="<?php echo Rateb\App\Core\View::escape(__('fiscal_period_close_confirm')); ?>">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                        <div class="form-check form-check-inline mb-1">
                            <input class="form-check-input" type="checkbox" name="with_closing_entry" value="1" id="close_<?php echo (int) $row['id']; ?>">
                            <label class="form-check-label small" for="close_<?php echo (int) $row['id']; ?>"><?php echo __('year_end_closing_entry'); ?></label>
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-warning d-block"><?php echo __('close_period'); ?></button>
                    </form>
                    <?php } elseif (($canPost ?? false) && $st === 'closed') { ?>
                    <form method="post" action="<?php echo rateb_app_url('fiscal-periods/' . (int) $row['id'] . '/reopen'); ?>" class="d-inline"
                          data-rateb-confirm="<?php echo Rateb\App\Core\View::escape(__('fiscal_period_reopen_confirm')); ?>">
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
