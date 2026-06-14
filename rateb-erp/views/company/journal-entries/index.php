<?php
/** Company journal list */
$bulkManage = ($canManage ?? false);
$bulkApprove = ($canApprove ?? false);
$bulkAny = $bulkManage || $bulkApprove;
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
    <?php if ($bulkAny) { ?>
    <div class="rateb-bulk-bar<?php echo empty($items) ? ' d-none' : ''; ?>" data-rateb-bulk-bar>
        <span class="rateb-bulk-count" data-rateb-bulk-count data-label="<?php echo Rateb\App\Core\View::escape(__('bulk_selected')); ?>" data-hint="<?php echo Rateb\App\Core\View::escape(__('bulk_select_rows_hint')); ?>"><?php echo __('bulk_select_rows_hint'); ?></span>
        <?php if ($bulkManage) { ?>
        <form method="post" action="<?php echo rateb_app_url('journal-entries/bulk-delete'); ?>" class="d-inline" data-rateb-bulk-form="delete"
              data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('bulk_confirm_delete_drafts')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> <?php echo __('bulk_delete_drafts'); ?></button>
        </form>
        <?php } ?>
        <?php if ($bulkApprove) { ?>
        <form method="post" action="<?php echo rateb_app_url('journal-entries/bulk-approve'); ?>" class="d-inline" data-rateb-bulk-form="approve">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> <?php echo __('bulk_approve'); ?></button>
        </form>
        <form method="post" action="<?php echo rateb_app_url('journal-entries/bulk-void'); ?>" class="d-inline" data-rateb-bulk-form="void"
              data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('bulk_confirm_void')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <button type="submit" class="btn btn-warning btn-sm"><i class="fas fa-ban"></i> <?php echo __('bulk_void'); ?></button>
        </form>
        <?php } ?>
    </div>
    <?php } ?>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0" data-rateb-bulk-table="<?php echo $bulkAny ? '1' : '0'; ?>">
                <thead>
                <tr>
                    <?php if ($bulkAny) { ?>
                    <th class="rateb-bulk-th"><input type="checkbox" class="form-check-input" data-rateb-select-all title="<?php echo __('select_all'); ?>"></th>
                    <?php } ?>
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
                <tr><td colspan="<?php echo $bulkAny ? 7 : 6; ?>" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else { foreach ($items as $row) {
                    $desc = rateb_locale() === 'ar' && !empty($row['description_ar']) ? $row['description_ar'] : $row['description'];
                    $st = (string) ($row['status'] ?? '');
                    $source = (string) ($row['source_type'] ?? '');
                    $isManual = $source === 'manual';
                    $isDraftManual = $st === 'draft' && $isManual;
                    $isPostedManual = $st === 'posted' && $isManual;
                    $canSelect = ($bulkManage && $isDraftManual)
                        || ($bulkApprove && $st === 'draft')
                        || ($bulkApprove && $isPostedManual);
                    ?>
                <tr>
                    <?php if ($bulkAny) { ?>
                    <td class="rateb-bulk-td">
                        <?php if ($canSelect) { ?>
                        <input type="checkbox" class="form-check-input" data-rateb-row-check value="<?php echo (int) $row['id']; ?>">
                        <?php } ?>
                    </td>
                    <?php } ?>
                    <td><?php echo Rateb\App\Core\View::escape($row['entry_no']); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['entry_date']); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($desc); ?></td>
                    <td><?php echo __($source); ?></td>
                    <td><span class="badge bg-<?php echo $st === 'posted' ? 'success' : ($st === 'void' ? 'secondary' : 'warning'); ?>"><?php echo __($st); ?></span></td>
                    <td class="text-nowrap">
                        <a href="<?php echo rateb_app_url('journal-entries/' . (int) $row['id']); ?>" class="btn btn-sm btn-outline-primary" title="<?php echo __('view'); ?>"><i class="fas fa-eye"></i></a>
                        <?php if (($canManage ?? false) && $isDraftManual) { ?>
                        <a href="<?php echo rateb_app_url('journal-entries/' . (int) $row['id'] . '/edit'); ?>" class="btn btn-sm btn-outline-secondary" title="<?php echo __('edit'); ?>"><i class="fas fa-edit"></i></a>
                        <?php } ?>
                        <?php if (($canApprove ?? false) && $isPostedManual) { ?>
                        <form method="post" action="<?php echo rateb_app_url('journal-entries/' . (int) $row['id'] . '/void'); ?>" class="d-inline"
                              onsubmit="return confirm('<?php echo __('journal_void_confirm'); ?>');">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="<?php echo __('void_entry'); ?>"><i class="fas fa-ban"></i></button>
                        </form>
                        <?php } ?>
                    </td>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
