<?php
/** Entry approval table — manual journal drafts only */
$acctSvc = new \Rateb\App\Services\AccountingService();
$bulkManage = $canManage ?? false;
$bulkApprove = ($canApprove ?? false) && empty($oversightOnly);
$bulkAny = $bulkManage || $bulkApprove;
$statusFilter = (string) ($statusFilter ?? 'all');
$dateFrom = (string) ($dateFrom ?? '');
$dateTo = (string) ($dateTo ?? '');
$perPage = (int) ($perPage ?? 10);
$stats = $stats ?? ['total' => 0, 'pending' => 0, 'approved' => 0];
$listUrl = rateb_app_url('accounting/entry-approval');
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>

<div class="rateb-entry-approval">
    <div class="rateb-entry-approval-head d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h5 class="mb-0"><i class="fas fa-check-double me-2 text-primary"></i><?php echo __('entry_approval'); ?></h5>
        <?php if ($canManage) { ?>
        <a href="<?php echo rateb_app_url('journal-entries/create'); ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> <?php echo __('new_journal_entry'); ?>
        </a>
        <?php } ?>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="rateb-approval-stat rateb-approval-stat-total">
                <div class="rateb-approval-stat-label"><?php echo __('total_entries'); ?></div>
                <div class="rateb-approval-stat-value"><?php echo (int) $stats['total']; ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="rateb-approval-stat rateb-approval-stat-pending">
                <div class="rateb-approval-stat-label"><?php echo __('pending'); ?></div>
                <div class="rateb-approval-stat-value"><?php echo (int) $stats['pending']; ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="rateb-approval-stat rateb-approval-stat-approved">
                <div class="rateb-approval-stat-label"><?php echo __('approved'); ?></div>
                <div class="rateb-approval-stat-value"><?php echo (int) $stats['approved']; ?></div>
            </div>
        </div>
    </div>

    <div class="rateb-card mb-3">
        <div class="rateb-card-body py-3">
            <form method="get" action="<?php echo $listUrl; ?>" class="row g-2 align-items-end">
                <?php if (!empty($_GET['company_id'])) { ?>
                <input type="hidden" name="company_id" value="<?php echo (int) $_GET['company_id']; ?>">
                <?php } ?>
                <div class="col-md-2">
                    <label class="form-label small mb-1"><?php echo __('status'); ?></label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="all"<?php echo $statusFilter === 'all' ? ' selected' : ''; ?>><?php echo __('all'); ?></option>
                        <option value="pending"<?php echo $statusFilter === 'pending' ? ' selected' : ''; ?>><?php echo __('pending'); ?></option>
                        <option value="approved"<?php echo $statusFilter === 'approved' ? ' selected' : ''; ?>><?php echo __('approved'); ?></option>
                        <option value="rejected"<?php echo $statusFilter === 'rejected' ? ' selected' : ''; ?>><?php echo __('rejected'); ?></option>
                        <option value="void"<?php echo $statusFilter === 'void' ? ' selected' : ''; ?>><?php echo __('void'); ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1"><?php echo __('date_from'); ?></label>
                    <input type="date" name="from" class="form-control form-control-sm" value="<?php echo Rateb\App\Core\View::escape($dateFrom); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1"><?php echo __('date_to'); ?></label>
                    <input type="date" name="to" class="form-control form-control-sm" value="<?php echo Rateb\App\Core\View::escape($dateTo); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1"><?php echo __('show'); ?></label>
                    <select name="show" class="form-select form-select-sm">
                        <?php foreach ([10, 25, 50, 100] as $n) { ?>
                        <option value="<?php echo $n; ?>"<?php echo $perPage === $n ? ' selected' : ''; ?>><?php echo $n; ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> <?php echo __('filter'); ?></button>
                    <a href="<?php echo $listUrl . (!empty($_GET['company_id']) ? '?company_id=' . (int) $_GET['company_id'] : ''); ?>" class="btn btn-outline-secondary btn-sm"><?php echo __('clear'); ?></a>
                </div>
            </form>
        </div>
    </div>

    <div class="rateb-card">
        <?php if ($bulkAny) { ?>
        <div class="rateb-bulk-bar<?php echo empty($items) ? ' d-none' : ''; ?>" data-rateb-bulk-bar>
            <span class="rateb-bulk-count" data-rateb-bulk-count data-label="<?php echo Rateb\App\Core\View::escape(__('bulk_selected')); ?>">0 <?php echo __('bulk_selected'); ?></span>
            <?php if ($bulkApprove) { ?>
            <form method="post" action="<?php echo rateb_app_url('journal-entries/bulk-approve'); ?>" class="d-inline" data-rateb-bulk-form="approve">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> <?php echo __('bulk_approve'); ?></button>
            </form>
            <form method="post" action="<?php echo rateb_app_url('journal-entries/bulk-reject'); ?>" class="d-inline flex-wrap gap-1 align-items-center" data-rateb-bulk-form="reject"
                  data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('bulk_confirm_reject')); ?>">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <input type="text" name="reject_reason" class="form-control form-control-sm d-inline-block" style="width:10rem" placeholder="<?php echo Rateb\App\Core\View::escape(__('reject_reason')); ?>">
                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times"></i> <?php echo __('bulk_reject'); ?></button>
            </form>
            <form method="post" action="<?php echo rateb_app_url('journal-entries/bulk-void'); ?>" class="d-inline" data-rateb-bulk-form="undo"
                  data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('bulk_confirm_undo')); ?>">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <button type="submit" class="btn btn-warning btn-sm"><i class="fas fa-undo"></i> <?php echo __('bulk_undo'); ?></button>
            </form>
            <?php } ?>
        </div>
        <?php } ?>
        <div class="rateb-card-body p-0">
            <div class="table-responsive">
                <table class="table rateb-table rateb-approval-table mb-0" data-rateb-bulk-table="<?php echo $bulkAny ? '1' : '0'; ?>">
                    <thead>
                    <tr>
                        <th><?php echo __('entry_no'); ?></th>
                        <th><?php echo __('evaluation_date'); ?></th>
                        <th><?php echo __('status'); ?></th>
                        <th><?php echo __('reject_reason'); ?></th>
                        <th><?php echo __('created_at'); ?></th>
                        <?php if ($bulkAny) { ?>
                        <th class="rateb-bulk-th text-center"><input type="checkbox" class="form-check-input" data-rateb-select-all></th>
                        <?php } ?>
                        <th class="text-end rateb-accounting-actions-col"><?php echo __('actions'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($items)) { ?>
                    <tr><td colspan="<?php echo $bulkAny ? 7 : 6; ?>" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                    <?php } else { foreach ($items as $row) {
                        $st = (string) ($row['status'] ?? '');
                        $isManual = ($row['source_type'] ?? '') === 'manual';
                        $isPending = $st === 'draft';
                        $isApproved = $st === 'posted';
                        $isRejected = $st === 'rejected';
                        $submitted = $acctSvc->isSubmittedForApproval($row);
                        $displayStatus = $acctSvc->accountingRowDisplayStatus($row);
                        $badgeClass = $displayStatus === 'awaiting_oversight_approval' ? 'info'
                            : ($isPending ? 'warning' : ($isApproved ? 'success' : ($isRejected ? 'danger' : 'secondary')));
                        $canSelect = ($bulkApprove && $isPending && $isManual)
                            || ($bulkApprove && $isApproved && $isManual)
                            || ($bulkManage && $isPending && $isManual && !$submitted);
                        $rejectReason = trim((string) ($row['reject_reason'] ?? ''));
                        $id = (int) $row['id'];
                        ?>
                    <tr>
                        <td class="fw-semibold"><?php echo Rateb\App\Core\View::escape($row['entry_no']); ?></td>
                        <td><?php echo Rateb\App\Core\View::escape($row['entry_date']); ?></td>
                        <td><span class="badge bg-<?php echo $badgeClass; ?>"><?php echo __($displayStatus); ?></span></td>
                        <td class="small text-muted"><?php echo $rejectReason !== '' ? Rateb\App\Core\View::escape($rejectReason) : '—'; ?></td>
                        <td class="small"><?php echo Rateb\App\Core\View::escape((string) ($row['created_at'] ?? '')); ?></td>
                        <?php if ($bulkAny) { ?>
                        <td class="rateb-bulk-td text-center">
                            <?php if ($canSelect) { ?>
                            <input type="checkbox" class="form-check-input" data-rateb-row-check value="<?php echo (int) $row['id']; ?>">
                            <?php } ?>
                        </td>
                        <?php } ?>
                        <td class="text-end text-nowrap rateb-approval-actions rateb-accounting-actions-col rateb-actions-cell">
                            <?php Rateb\App\Core\View::partial('accounting-row-actions', [
                                'csrf' => $csrf,
                                'id' => $id,
                                'viewUrl' => rateb_app_url('journal-entries/' . $id),
                                'editUrl' => ($bulkManage && $isPending && $isManual) ? rateb_app_url('journal-entries/' . $id . '/edit') : null,
                                'canEdit' => $bulkManage && $isPending && $isManual,
                                'canSubmit' => $bulkManage && $isPending && $isManual,
                                'canDelete' => $bulkManage && $isPending && $isManual && !$submitted,
                                'deleteUrl' => rateb_app_url('journal-entries/' . $id . '/delete'),
                                'submitUrl' => rateb_app_url('journal-entries/' . $id . '/submit-approval'),
                                'submitted' => $submitted && $isPending && $isManual,
                                'redirectTo' => rateb_app_url('journal-entries/' . $id),
                            ]); ?>
                            <?php if ($bulkApprove && $isPending && $isManual && $submitted) { ?>
                            <form method="post" action="<?php echo rateb_app_url('journal-entries/' . $id . '/post'); ?>" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                                <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> <?php echo __('approve'); ?></button>
                            </form>
                            <form method="post" action="<?php echo rateb_app_url('journal-entries/' . $id . '/reject'); ?>" class="d-inline"
                                  onsubmit="return confirm('<?php echo __('bulk_confirm_reject'); ?>');">
                                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                                <input type="text" name="reject_reason" class="form-control form-control-sm d-inline-block mb-1" style="width:7rem" placeholder="<?php echo Rateb\App\Core\View::escape(__('reject_reason')); ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-times"></i> <?php echo __('reject'); ?></button>
                            </form>
                            <?php } elseif ($bulkApprove && $isApproved && $isManual) { ?>
                            <form method="post" action="<?php echo rateb_app_url('journal-entries/' . $id . '/void'); ?>" class="d-inline"
                                  onsubmit="return confirm('<?php echo __('bulk_confirm_undo'); ?>');">
                                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                                <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-undo"></i> <?php echo __('undo'); ?></button>
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
</div>
