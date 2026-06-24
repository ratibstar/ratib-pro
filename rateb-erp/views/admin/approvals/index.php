<?php
/** @var array<int, array<string, mixed>> $items */
/** @var array<string, int> $summary */
/** @var array<string, string> $typeOptions */
/** @var array<int, array<string, mixed>> $companies */
/** @var array{company_id:int,status:string,date_from:string,date_to:string} $filters */
$typeFilter = (string) ($typeFilter ?? '');
$csrfToken = (string) ($csrf ?? '');
Rateb\App\Core\View::partial('admin-oversight-approvals-banner');
$approvalsConfig = [
    'csrf' => $csrfToken,
    'detailUrl' => rateb_url('admin/oversight/approvals/detail'),
    'decideUrl' => rateb_url('admin/oversight/approvals/decide'),
    'typeFilter' => $typeFilter,
    'labels' => [
        'view' => __('view'),
        'approve' => __('approve'),
        'reject' => __('reject'),
        'undo' => __('undo'),
        'loading' => __('loading'),
        'confirm_reject' => __('confirm_reject'),
        'confirm_undo' => __('confirm_undo'),
        'open_in_ops' => __('open_in_operations'),
        'approval_detail' => __('approval_detail'),
        'close' => __('close'),
        'error' => __('system_error_generic'),
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
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><?php echo __('filter'); ?></button>
                    </div>
                    <div class="col-md-2">
                        <a href="<?php echo rateb_url('admin/oversight/workflows'); ?>" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-diagram-project"></i> <?php echo __('workflow_definitions'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="col-12">
        <div class="row g-2">
            <?php
            $cards = [
                ['key' => 'total', 'label' => 'approvals_total_pending', 'class' => 'primary'],
                ['key' => 'workflow_instance', 'label' => 'approval_category_workflow', 'class' => 'warning'],
                ['key' => 'journal_entry', 'label' => 'approval_category_accounting', 'class' => 'info'],
                ['key' => 'supplier_evaluation', 'label' => 'approval_category_manager', 'class' => 'secondary'],
                ['key' => 'hr_leave', 'label' => 'approval_category_hr', 'class' => 'success'],
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
                if ($card['key'] === 'hr_leave') {
                    $hrKeys = ['hr_leave', 'hr_permission', 'hr_request', 'hr_payroll'];
                    $count = 0;
                    foreach ($hrKeys as $hk) {
                        $count += (int) ($summary[$hk] ?? 0);
                    }
                }
                ?>
            <div class="col-6 col-md">
                <div class="rateb-card border-<?php echo $card['class']; ?>">
                    <div class="rateb-card-body py-3 text-center">
                        <div class="h4 mb-0 rateb-ltr-num"><?php echo $count; ?></div>
                        <div class="small text-muted"><?php echo __($card['label']); ?></div>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>

    <div class="col-12">
        <div class="rateb-card">
            <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><?php echo __('approvals_oversight'); ?></span>
                <span class="text-muted small"><?php echo __('admin_oversight_approvals_hint'); ?></span>
            </div>
            <div class="rateb-card-body p-0">
                <div class="table-responsive rateb-oversight-table-wrap">
                    <table class="table rateb-table rateb-oversight-table rateb-approvals-table mb-0">
                        <colgroup>
                            <col class="rateb-col-approval-company">
                            <col class="rateb-col-approval-type">
                            <col class="rateb-col-approval-ref">
                            <col class="rateb-col-approval-date">
                            <col class="rateb-col-approval-actions">
                        </colgroup>
                        <thead>
                        <tr>
                            <th><?php echo __('companies'); ?></th>
                            <th><?php echo __('approval_type'); ?></th>
                            <th><?php echo __('reference'); ?></th>
                            <th><?php echo __('created_at'); ?></th>
                            <th class="rateb-th-actions"><?php echo __('actions'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($items)) { ?>
                        <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                        <?php } else { foreach ($items as $row) {
                            $sourceKey = (string) ($row['source_key'] ?? '');
                            $recordId = (int) ($row['record_id'] ?? 0);
                            $companyId = (int) ($row['company_id'] ?? 0);
                            $canReject = !empty($row['can_reject']);
                            $rowKey = $sourceKey . '-' . $recordId;
                            ?>
                        <tr class="rateb-approval-data-row" data-approval-row="<?php echo Rateb\App\Core\View::escape($rowKey); ?>"
                            data-source-key="<?php echo Rateb\App\Core\View::escape($sourceKey); ?>"
                            data-record-id="<?php echo $recordId; ?>"
                            data-company-id="<?php echo $companyId; ?>"
                            data-can-reject="<?php echo $canReject ? '1' : '0'; ?>">
                            <td class="rateb-approval-cell-clip"><?php echo Rateb\App\Core\View::escape((string) ($row['company_name'] ?? '')); ?></td>
                            <td class="rateb-approval-cell-clip">
                                <?php echo Rateb\App\Core\View::escape((string) ($row['type_label'] ?? '')); ?>
                                <?php if (!empty($row['workflow_name'])) { ?>
                                <div class="small text-muted"><?php echo Rateb\App\Core\View::escape((string) $row['workflow_name']); ?></div>
                                <?php } ?>
                            </td>
                            <td class="rateb-ltr-num rateb-approval-cell-ref"><?php echo Rateb\App\Core\View::escape((string) ($row['reference'] ?? '')); ?></td>
                            <td class="rateb-ltr-num rateb-approval-cell-date"><?php echo Rateb\App\Core\View::escape((string) ($row['submitted_at'] ?? '')); ?></td>
                            <td class="rateb-actions-cell">
                                <div class="rateb-approval-ops">
                                    <button type="button" class="rateb-approval-btn rateb-approval-btn-view" data-action="view" title="<?php echo __('view'); ?>">
                                        <i class="fas fa-eye"></i><span><?php echo __('view'); ?></span>
                                    </button>
                                    <button type="button" class="rateb-approval-btn rateb-approval-btn-approve" data-action="approve" title="<?php echo __('approve'); ?>">
                                        <i class="fas fa-check"></i><span><?php echo __('approve'); ?></span>
                                    </button>
                                    <?php if ($canReject) { ?>
                                    <button type="button" class="rateb-approval-btn rateb-approval-btn-reject" data-action="reject" title="<?php echo __('reject'); ?>">
                                        <i class="fas fa-times"></i><span><?php echo __('reject'); ?></span>
                                    </button>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>
                        <tr class="rateb-approval-detail-row d-none" data-detail-for="<?php echo Rateb\App\Core\View::escape($rowKey); ?>" data-approval-row="<?php echo Rateb\App\Core\View::escape($rowKey); ?>">
                            <td colspan="5" class="p-0">
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
