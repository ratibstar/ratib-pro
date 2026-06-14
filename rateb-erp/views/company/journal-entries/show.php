<?php
$desc = rateb_locale() === 'ar' && !empty($entry['description_ar']) ? $entry['description_ar'] : $entry['description'];
$status = (string) ($entry['status'] ?? '');
$entryId = (int) ($entry['id'] ?? 0);
$isManual = ($entry['source_type'] ?? '') === 'manual';
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>

<?php if ($isManual) {
    Rateb\App\Core\View::partial('accounting-doc-workflow', [
        'status' => $status,
        'docType' => 'journal',
        'canManage' => ($canManage ?? false) && $status === 'draft',
        'canApprove' => ($canApprove ?? false) && in_array($status, ['draft', 'posted'], true),
        'csrf' => $csrf,
        'docId' => $entryId,
        'postUrl' => rateb_app_url('journal-entries/' . $entryId . '/post'),
        'voidUrl' => rateb_app_url('journal-entries/' . $entryId . '/void'),
        'editUrl' => rateb_app_url('journal-entries/' . $entryId . '/edit'),
        'deleteUrl' => rateb_app_url('journal-entries/' . $entryId . '/delete'),
        'listUrl' => rateb_app_url('journal-entries'),
    ]);
} ?>

<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-book me-2"></i><?php echo __('journal_entry_review'); ?> — <?php echo Rateb\App\Core\View::escape($entry['entry_no'] ?? ''); ?></span>
        <span class="badge bg-<?php echo $status === 'posted' ? 'success' : ($status === 'void' ? 'secondary' : 'warning'); ?>">
            <?php echo __($status); ?>
        </span>
    </div>
    <div class="rateb-card-body">
        <div class="row g-2">
            <div class="col-md-4">
                <span class="text-muted small d-block"><?php echo __('evaluation_date'); ?></span>
                <strong><?php echo Rateb\App\Core\View::escape($entry['entry_date'] ?? ''); ?></strong>
            </div>
            <div class="col-md-4">
                <span class="text-muted small d-block"><?php echo __('source_type'); ?></span>
                <strong><?php echo __((string) ($entry['source_type'] ?? '')); ?></strong>
            </div>
            <div class="col-md-4">
                <span class="text-muted small d-block"><?php echo __('description'); ?></span>
                <strong><?php echo Rateb\App\Core\View::escape($desc); ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('journal_lines'); ?></div>
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
            <?php
            $totalDr = 0.0;
            $totalCr = 0.0;
            foreach ($lines as $line) {
                $name = rateb_locale() === 'ar' && !empty($line['name_ar']) ? $line['name_ar'] : $line['name'];
                $ccName = '';
                if (!empty($line['cc_code'])) {
                    $ccName = $line['cc_code'] . ' — ' . (rateb_locale() === 'ar' && !empty($line['cc_name_ar']) ? $line['cc_name_ar'] : ($line['cc_name'] ?? ''));
                }
                $totalDr += (float) $line['debit'];
                $totalCr += (float) $line['credit'];
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
            <tfoot>
            <tr class="fw-semibold">
                <td colspan="3" class="text-end"><?php echo __('total'); ?></td>
                <td class="text-end"><?php echo number_format($totalDr, 2); ?></td>
                <td class="text-end"><?php echo number_format($totalCr, 2); ?></td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php if (!$isManual) { ?>
<div class="mt-3">
    <a href="<?php echo rateb_app_url('journal-entries'); ?>" class="btn btn-outline-secondary">
        <i class="fas fa-list"></i> <?php echo __('back_to_list'); ?>
    </a>
</div>
<?php } ?>
