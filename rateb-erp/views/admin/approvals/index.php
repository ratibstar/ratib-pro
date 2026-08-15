<?php
/** @var array<int, array<string, mixed>> $items */
/** @var array<string, int> $summary */
/** @var array<string, string> $typeOptions */
/** @var array<int, array<string, mixed>> $companies */
/** @var array{company_id:int,status:string,date_from:string,date_to:string} $filters */
$typeFilter = (string) ($typeFilter ?? '');
$hrType = (string) ($hrType ?? '');
$hrTypeOptions = $hrTypeOptions ?? \Rateb\App\Services\ApprovalOversightService::hrTypeOptions();
$isHrPage = $typeFilter === 'hr';
$csrfToken = (string) ($csrf ?? '');
$canBulk = true;
$colSpan = 6;
Rateb\App\Core\View::partial('admin-oversight-approvals-banner');
$approvalsConfig = [
    'csrf' => $csrfToken,
    'canBulk' => $canBulk,
    'detailUrl' => rateb_url('admin/oversight/approvals/detail'),
    'decideUrl' => rateb_url('admin/oversight/approvals/decide'),
    'bulkDecideUrl' => rateb_url('admin/oversight/approvals/bulk-decide'),
    'approveUrl' => rateb_url('admin/oversight/approvals/approve'),
    'rejectUrl' => rateb_url('admin/oversight/approvals/reject'),
    'undoUrl' => rateb_url('admin/oversight/approvals/undo'),
    'typeFilter' => $typeFilter,
    'hrType' => $hrType,
    'companyFilter' => (int) ($filters['company_id'] ?? 0),
    'labels' => [
        'view' => __('view'),
        'edit' => __('edit'),
        'approve' => __('approve'),
        'reject' => __('reject'),
        'undo' => __('undo'),
        'loading' => __('loading'),
        'confirm_reject' => __('confirm_reject'),
        'confirm_undo' => __('confirm_undo'),
        'confirm_approve' => __('confirm_approve'),
        'confirm_edit' => __('confirm_edit_oversight'),
        'confirm_open_ops' => __('confirm_open_ops'),
        'open_in_ops' => __('open_in_operations'),
        'approval_detail' => __('approval_detail'),
        'close' => __('close'),
        'error' => __('system_error_generic'),
        'already_processed' => __('manager_approval_already_processed'),
        'no_records' => __('no_records'),
        'bulk_none_selected' => __('bulk_none_selected'),
        'bulk_approve' => __('bulk_approve'),
        'bulk_reject' => __('bulk_reject'),
        'bulk_confirm_reject' => __('bulk_confirm_reject_oversight'),
        'bulk_confirm_approve_count' => __('bulk_confirm_approve_count'),
        'select_all' => __('select_all'),
    ],
];
?>
<div class="row g-3" id="rateb-approvals-oversight">
    <div class="col-12">
        <form method="get" action="<?php echo Rateb\App\Core\View::escape($formAction ?? rateb_url('admin/oversight/approvals')); ?>" class="rateb-card">
            <div class="rateb-card-body">
                <div class="row g-2 align-items-end">
                    <?php if (($companies ?? []) !== []) { ?>
                    <div class="col-md-3">
                        <label class="form-label rateb-form-label"><?php echo __('companies'); ?></label>
                        <select class="form-select" name="company_id">
                            <option value=""><?php echo __('all_companies'); ?></option>
                            <?php foreach ($companies as $c) { ?>
                            <option value="<?php echo (int) ($c['id'] ?? 0); ?>"<?php echo (int) ($filters['company_id'] ?? 0) === (int) ($c['id'] ?? 0) ? ' selected' : ''; ?>>
                                <?php echo Rateb\App\Core\View::escape((string) ($c['name'] ?? '')); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </div>
                    <?php } ?>
                    <?php if ($isHrPage) { ?>
                    <input type="hidden" name="type" value="hr">
                    <?php if ($hrType !== '') { ?>
                    <input type="hidden" name="hr_type" value="<?php echo Rateb\App\Core\View::escape($hrType); ?>">
                    <?php } ?>
                    <?php } else { ?>
                    <div class="col-md-3">
                        <label class="form-label rateb-form-label"><?php echo __('approval_category'); ?></label>
                        <select class="form-select" name="type">
                            <?php foreach ($typeOptions as $value => $labelKey) { ?>
                            <option value="<?php echo Rateb\App\Core\View::escape($value); ?>"<?php echo $typeFilter === (string) $value ? ' selected' : ''; ?>>
                                <?php echo __($labelKey); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </div>
                    <?php } ?>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><?php echo __('filter'); ?></button>
                    </div>
                    <div class="col-md-2">
                        <?php if ($isHrPage) { ?>
                        <a href="<?php echo rateb_url('admin/oversight/approvals'); ?>" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-check-double"></i> <?php echo __('approvals_oversight'); ?>
                        </a>
                        <?php } else { ?>
                        <a href="<?php echo rateb_url('admin/oversight/workflows'); ?>" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-diagram-project"></i> <?php echo __('workflow_definitions'); ?>
                        </a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="col-12">
        <?php
        $companyIdQ = (int) ($filters['company_id'] ?? 0);
        $approvalsBase = rateb_url('admin/oversight/approvals');
        $companiesBase = rateb_url('admin/oversight/companies-approvals');
        $hrBase = rateb_url('admin/oversight/hr-approvals');
        $hrTotal = 0;
        foreach (\Rateb\App\Services\ApprovalOversightService::hrSourceKeys() as $hk) {
            $hrTotal += (int) ($summary[$hk] ?? 0);
        }
        if ($isHrPage) {
            $tabQs = $companyIdQ > 0 ? ['company_id' => $companyIdQ] : [];
            $allHref = $hrBase . ($tabQs !== [] ? '?' . http_build_query($tabQs) : '');
            ?>
        <nav class="rateb-hr-approval-tabs" aria-label="<?php echo Rateb\App\Core\View::escape(__('hr_approvals_oversight')); ?>">
            <a href="<?php echo Rateb\App\Core\View::escape($allHref); ?>"
               class="rateb-hr-approval-tab<?php echo $hrType === '' ? ' is-active' : ''; ?>"
               data-rateb-soft-nav="1"
               data-summary-card="hr_total">
                <span><?php echo __('all'); ?></span>
                <span class="rateb-hr-approval-tab-badge" data-summary-count><?php echo $hrTotal; ?></span>
            </a>
            <?php foreach ($hrTypeOptions as $tabVal => $tabMeta) {
                $tabCount = (int) ($summary[$tabMeta['source']] ?? 0);
                $hrefQs = $tabQs;
                $hrefQs['hr_type'] = $tabVal;
                $tabHref = $hrBase . '?' . http_build_query($hrefQs);
                $tabActive = $hrType === $tabVal;
                ?>
            <a href="<?php echo Rateb\App\Core\View::escape($tabHref); ?>"
               class="rateb-hr-approval-tab<?php echo $tabActive ? ' is-active' : ''; ?>"
               data-rateb-soft-nav="1"
               data-summary-card="<?php echo Rateb\App\Core\View::escape((string) $tabMeta['source']); ?>">
                <span><?php echo __($tabMeta['label']); ?></span>
                <span class="rateb-hr-approval-tab-badge" data-summary-count><?php echo $tabCount; ?></span>
            </a>
            <?php } ?>
        </nav>
        <?php } else { ?>
        <div class="row g-2">
            <?php
            $cards = [
                ['key' => 'total', 'label' => 'approvals_total_pending', 'class' => 'primary', 'type' => '', 'href' => $approvalsBase],
                ['key' => 'company_registration', 'label' => 'companies_approvals_oversight', 'class' => 'info', 'type' => 'companies', 'href' => $companiesBase],
                ['key' => 'workflow_instance', 'label' => 'approval_category_workflow', 'class' => 'warning', 'type' => 'workflow', 'href' => $approvalsBase],
                ['key' => 'journal_entry', 'label' => 'approval_category_accounting', 'class' => 'info', 'type' => 'accounting', 'href' => $approvalsBase],
                ['key' => 'supplier_evaluation', 'label' => 'approval_category_manager', 'class' => 'secondary', 'type' => 'manager', 'href' => $approvalsBase],
                ['key' => 'hr_total', 'label' => 'hr_approvals_oversight', 'class' => 'success', 'type' => 'hr', 'href' => $hrBase],
            ];
            foreach ($cards as $card) {
                $count = (int) ($summary[$card['key']] ?? 0);
                if ($card['key'] === 'journal_entry') {
                    $count = (int) (($summary['journal_entry'] ?? 0) + ($summary['cash_voucher'] ?? 0));
                }
                if ($card['key'] === 'supplier_evaluation') {
                    $managerKeys = ['supplier_evaluation', 'contract_renewal', 'asset_maintenance', 'asset_assignment', 'device_maintenance', 'device_spare_part', 'inventory_audit'];
                    $count = 0;
                    foreach ($managerKeys as $mk) {
                        $count += (int) ($summary[$mk] ?? 0);
                    }
                }
                if ($card['key'] === 'hr_total') {
                    $count = $hrTotal;
                }
                $qs = [];
                if ($card['type'] !== '' && $card['type'] !== 'hr') {
                    $qs['type'] = $card['type'];
                }
                if ($companyIdQ > 0) {
                    $qs['company_id'] = $companyIdQ;
                }
                $cardHref = $card['href'] . ($qs !== [] ? '?' . http_build_query($qs) : '');
                $isActive = ($card['type'] === '' && $typeFilter === '')
                    || ($card['type'] !== '' && $typeFilter === $card['type']);
                ?>
            <div class="col-6 col-md">
                <a href="<?php echo Rateb\App\Core\View::escape($cardHref); ?>"
                   class="rateb-card rateb-approval-summary-card border-<?php echo $card['class']; ?><?php echo $isActive ? ' is-active' : ''; ?> text-decoration-none d-block"
                   data-rateb-soft-nav="1"
                   data-summary-card="<?php echo Rateb\App\Core\View::escape($card['key']); ?>">
                    <div class="rateb-card-body py-3 text-center">
                        <div class="h4 mb-0 rateb-ltr-num" data-summary-count><?php echo $count; ?></div>
                        <div class="small text-muted"><?php echo __($card['label']); ?></div>
                    </div>
                </a>
            </div>
            <?php } ?>
        </div>
        <?php } ?>
    </div>

    <div class="col-12">
        <div class="rateb-card">
            <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><?php echo $isHrPage ? __('hr_approvals_oversight') : __('approvals_oversight'); ?></span>
                <span class="text-muted small"><?php echo $isHrPage ? __('hr_approvals_oversight_hint') : __('admin_oversight_approvals_hint'); ?></span>
            </div>
            <?php if (!empty($items)) { ?>
            <div class="rateb-bulk-bar d-none" data-rateb-bulk-bar>
                <span class="rateb-bulk-count" data-rateb-bulk-count data-label="<?php echo Rateb\App\Core\View::escape(__('bulk_selected')); ?>">0 <?php echo __('bulk_selected'); ?></span>
                <button type="button" class="btn btn-success btn-sm" data-oversight-bulk="approve">
                    <i class="fas fa-check"></i> <?php echo __('bulk_approve'); ?>
                </button>
                <button type="button" class="btn btn-danger btn-sm" data-oversight-bulk="reject">
                    <i class="fas fa-times"></i> <?php echo __('bulk_reject'); ?>
                </button>
            </div>
            <?php } ?>
            <div class="rateb-card-body p-0">
                <div class="table-responsive rateb-oversight-table-wrap">
                    <table class="table rateb-table rateb-oversight-table rateb-approvals-table rateb-approvals-table--bulk mb-0" data-rateb-bulk-table="1">
                        <colgroup>
                            <col class="rateb-col-bulk-check">
                            <col class="rateb-col-approval-company">
                            <col class="rateb-col-approval-type">
                            <col class="rateb-col-approval-ref">
                            <col class="rateb-col-approval-date">
                            <col class="rateb-col-approval-actions">
                        </colgroup>
                        <thead>
                        <tr>
                            <th class="rateb-bulk-th">
                                <?php if (!empty($items)) { ?>
                                <input type="checkbox" class="form-check-input" data-rateb-select-all title="<?php echo Rateb\App\Core\View::escape(__('select_all')); ?>" aria-label="<?php echo Rateb\App\Core\View::escape(__('select_all')); ?>">
                                <?php } ?>
                            </th>
                            <th><?php echo __('companies'); ?></th>
                            <th><?php echo __('approval_type'); ?></th>
                            <th><?php echo __('reference'); ?></th>
                            <th><?php echo __('created_at'); ?></th>
                            <th class="rateb-th-actions"><?php echo __('actions'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($items)) { ?>
                        <tr><td colspan="<?php echo $colSpan; ?>" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                        <?php } else { foreach ($items as $row) {
                            $sourceKey = (string) ($row['source_key'] ?? '');
                            $recordId = (int) ($row['record_id'] ?? 0);
                            $companyId = (int) ($row['company_id'] ?? 0);
                            $canReject = !empty($row['can_reject']);
                            $viewUrl = (string) ($row['view_url'] ?? '');
                            $editUrl = (string) ($row['edit_url'] ?? '');
                            $rowKey = $sourceKey . '-' . $recordId;
                            ?>
                        <tr class="rateb-approval-data-row" data-approval-row="<?php echo Rateb\App\Core\View::escape($rowKey); ?>"
                            data-source-key="<?php echo Rateb\App\Core\View::escape($sourceKey); ?>"
                            data-record-id="<?php echo $recordId; ?>"
                            data-company-id="<?php echo $companyId; ?>"
                            data-view-url="<?php echo Rateb\App\Core\View::escape($viewUrl); ?>"
                            data-edit-url="<?php echo Rateb\App\Core\View::escape($editUrl); ?>"
                            data-can-reject="<?php echo $canReject ? '1' : '0'; ?>"
                            data-can-undo="<?php echo \Rateb\App\Services\ApprovalOversightService::canUndo($sourceKey) ? '1' : '0'; ?>">
                            <td class="rateb-bulk-td">
                                <input type="checkbox" class="form-check-input" data-rateb-row-check value="<?php echo Rateb\App\Core\View::escape($rowKey); ?>" aria-label="<?php echo Rateb\App\Core\View::escape(__('select_all')); ?>">
                            </td>
                            <td class="rateb-approval-cell-clip"><?php echo Rateb\App\Core\View::escape((string) ($row['company_name'] ?? '')); ?></td>
                            <td class="rateb-approval-cell-clip">
                                <?php echo Rateb\App\Core\View::escape((string) ($row['type_label'] ?? '')); ?>
                                <?php if (!empty($row['workflow_name'])) { ?>
                                <div class="small text-muted"><?php echo Rateb\App\Core\View::escape((string) $row['workflow_name']); ?></div>
                                <?php } ?>
                            </td>
                            <td class="rateb-ltr-num rateb-approval-cell-ref"><?php echo Rateb\App\Core\View::escape((string) ($row['reference'] ?? '')); ?></td>
                            <td class="rateb-ltr-num rateb-approval-cell-date"><?php echo Rateb\App\Core\View::formatDate((string) ($row['submitted_at'] ?? '')); ?></td>
                            <td class="rateb-actions-cell">
                                <div class="rateb-approval-ops">
                                    <button type="button" class="rateb-approval-btn rateb-approval-btn-view" data-action="view" title="<?php echo __('view'); ?>">
                                        <i class="fas fa-eye"></i><span><?php echo __('view'); ?></span>
                                    </button>
                                    <?php if ($editUrl !== '') { ?>
                                    <a href="<?php echo Rateb\App\Core\View::escape($editUrl); ?>" class="rateb-approval-btn rateb-approval-btn-edit" data-rateb-edit-link="1" title="<?php echo __('edit'); ?>">
                                        <i class="fas fa-edit"></i><span><?php echo __('edit'); ?></span>
                                    </a>
                                    <?php } ?>
                                    <button type="button" class="rateb-approval-btn rateb-approval-btn-approve" data-action="approve" title="<?php echo __('approve'); ?>">
                                        <i class="fas fa-check"></i><span><?php echo __('approve'); ?></span>
                                    </button>
                                    <?php if ($canReject) { ?>
                                    <button type="button" class="rateb-approval-btn rateb-approval-btn-reject" data-action="reject" title="<?php echo __('reject'); ?>">
                                        <i class="fas fa-times"></i><span><?php echo __('reject'); ?></span>
                                    </button>
                                    <?php } ?>
                                    <?php if ($viewUrl !== '') { ?>
                                    <a href="<?php echo Rateb\App\Core\View::escape($viewUrl); ?>" class="rateb-approval-btn rateb-approval-btn-link" target="_blank" rel="noopener" title="<?php echo __('open_in_operations'); ?>">
                                        <i class="fas fa-external-link-alt"></i><span><?php echo __('open_in_operations'); ?></span>
                                    </a>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>
                        <tr class="rateb-approval-detail-row d-none" data-detail-for="<?php echo Rateb\App\Core\View::escape($rowKey); ?>" data-approval-row="<?php echo Rateb\App\Core\View::escape($rowKey); ?>">
                            <td colspan="<?php echo $colSpan; ?>" class="p-0">
                                <div class="rateb-approval-detail-pane">
                                    <div class="rateb-approval-detail-loading text-muted py-3 px-3">
                                        <i class="fas fa-spinner fa-spin me-2"></i><?php echo __('loading'); ?>
                                    </div>
                                    <div class="rateb-approval-detail-body d-none"></div>
                                </div>
                            </td>
                        </tr>
                        <?php } } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script type="application/json" id="rateb-approvals-config-json"><?php echo json_encode($approvalsConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
