<?php
/** Journal entries — all sources */
$acctSvc = new \Rateb\App\Services\AccountingService();
$csrf = (string) ($csrf ?? '');
$canManage = $canManage ?? false;
$oversightOnly = !empty($oversightOnly);
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0"><i class="fas fa-book me-2 text-primary"></i><?php echo __('journal_entries'); ?></h5>
    <?php if ($canManage) { ?>
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
                    <th class="text-end"><?php echo __('actions'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($items)) { ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else { foreach ($items as $row) {
                    $desc = rateb_locale() === 'ar' && !empty($row['description_ar']) ? $row['description_ar'] : $row['description'];
                    $st = (string) ($row['status'] ?? '');
                    $isManual = ($row['source_type'] ?? '') === 'manual';
                    $isManualDraft = $isManual && $st === 'draft';
                    $submitted = $acctSvc->isSubmittedForApproval($row);
                    $displayStatus = $acctSvc->accountingRowDisplayStatus($row);
                    $id = (int) $row['id'];
                    ?>
                <tr>
                    <td class="fw-semibold"><?php echo Rateb\App\Core\View::escape($row['entry_no']); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['entry_date']); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) $desc); ?></td>
                    <td><?php echo __((string) ($row['source_type'] ?? '')); ?></td>
                    <td><?php echo __($displayStatus); ?></td>
                    <td class="text-end">
                        <?php Rateb\App\Core\View::partial('accounting-row-actions', [
                            'csrf' => $csrf,
                            'id' => $id,
                            'viewUrl' => rateb_app_url('journal-entries/' . $id),
                            'editUrl' => $isManualDraft ? rateb_app_url('journal-entries/' . $id . '/edit') : null,
                            'canEdit' => $canManage && $isManualDraft,
                            'canSubmit' => $canManage && $isManualDraft,
                            'canDelete' => $canManage && $isManualDraft && !$submitted,
                            'deleteUrl' => rateb_app_url('journal-entries/' . $id . '/delete'),
                            'submitUrl' => rateb_app_url('journal-entries/' . $id . '/submit-approval'),
                            'submitted' => $submitted && $isManualDraft,
                        ]); ?>
                    </td>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
