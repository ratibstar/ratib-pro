<?php
$desc = rateb_locale() === 'ar' && !empty($entry['description_ar']) ? $entry['description_ar'] : $entry['description'];
$status = (string) ($entry['status'] ?? '');
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center">
        <span><?php echo Rateb\App\Core\View::escape($entry['entry_no'] ?? ''); ?></span>
        <span class="badge bg-<?php echo $status === 'posted' ? 'success' : ($status === 'void' ? 'secondary' : 'warning'); ?>">
            <?php echo __($status); ?>
        </span>
    </div>
    <div class="rateb-card-body">
        <p class="mb-1"><strong><?php echo __('evaluation_date'); ?>:</strong> <?php echo Rateb\App\Core\View::escape($entry['entry_date'] ?? ''); ?></p>
        <p class="mb-1"><strong><?php echo __('source_type'); ?>:</strong> <?php echo __((string) ($entry['source_type'] ?? '')); ?></p>
        <p class="mb-0"><?php echo Rateb\App\Core\View::escape($desc); ?></p>
    </div>
</div>
<div class="rateb-card">
    <div class="rateb-card-body p-0">
        <table class="table rateb-table mb-0">
            <thead>
            <tr>
                <th><?php echo __('code'); ?></th>
                <th><?php echo __('name'); ?></th>
                <th><?php echo __('cost_center'); ?></th>
                <th class="text-end"><?php echo __('debit'); ?></th>
                <th class="text-end"><?php echo __('credit'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($lines as $line) {
                $name = rateb_locale() === 'ar' && !empty($line['name_ar']) ? $line['name_ar'] : $line['name'];
                $ccName = '';
                if (!empty($line['cc_code'])) {
                    $ccName = $line['cc_code'] . ' — ' . (rateb_locale() === 'ar' && !empty($line['cc_name_ar']) ? $line['cc_name_ar'] : ($line['cc_name'] ?? ''));
                }
                ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape($line['code']); ?></td>
                <td><?php echo Rateb\App\Core\View::escape($name); ?></td>
                <td><?php echo $ccName !== '' ? Rateb\App\Core\View::escape($ccName) : '—'; ?></td>
                <td class="text-end"><?php echo number_format((float) $line['debit'], 2); ?></td>
                <td class="text-end"><?php echo number_format((float) $line['credit'], 2); ?></td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<div class="d-flex flex-wrap gap-2 mt-3">
    <a href="<?php echo rateb_app_url('journal-entries'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
    <?php if (($canManage ?? false) && $status === 'draft' && ($entry['source_type'] ?? '') === 'manual') { ?>
    <a href="<?php echo rateb_app_url('journal-entries/' . (int) $entry['id'] . '/edit'); ?>" class="btn btn-outline-primary">
        <i class="fas fa-edit"></i> <?php echo __('edit'); ?>
    </a>
    <form method="post" action="<?php echo rateb_app_url('journal-entries/' . (int) $entry['id'] . '/delete'); ?>" class="d-inline"
          onsubmit="return confirm('<?php echo __('bulk_confirm_delete_drafts'); ?>');">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <button type="submit" class="btn btn-outline-danger"><i class="fas fa-trash"></i> <?php echo __('delete'); ?></button>
    </form>
    <?php } ?>
    <?php if (($canApprove ?? false) && $status === 'draft') { ?>
    <form method="post" action="<?php echo rateb_app_url('journal-entries/' . (int) $entry['id'] . '/post'); ?>" class="d-inline">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> <?php echo __('approve_entry'); ?></button>
    </form>
    <?php } ?>
    <?php if (($canApprove ?? false) && $status === 'posted' && ($entry['source_type'] ?? '') === 'manual') { ?>
    <form method="post" action="<?php echo rateb_app_url('journal-entries/' . (int) $entry['id'] . '/void'); ?>" class="d-inline"
          onsubmit="return confirm('<?php echo __('journal_void_confirm'); ?>');">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <button type="submit" class="btn btn-outline-danger"><i class="fas fa-ban"></i> <?php echo __('void_entry'); ?></button>
    </form>
    <?php } elseif ($status === 'draft') { ?>
    <p class="text-muted small mb-0"><i class="fas fa-lock me-1"></i><?php echo __('accounting_perm_approve_hint'); ?></p>
    <?php } ?>
</div>
