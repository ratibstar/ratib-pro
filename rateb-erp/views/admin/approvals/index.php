<?php
/** @var array<int, array<string, mixed>> $items */
/** @var array<string, int> $summary */
/** @var array<string, string> $typeOptions */
/** @var array<int, array<string, mixed>> $companies */
/** @var array{company_id:int,status:string,date_from:string,date_to:string} $filters */
$typeFilter = (string) ($typeFilter ?? '');
$csrfToken = (string) ($csrf ?? '');
Rateb\App\Core\View::partial('admin-oversight-approvals-banner');
?>
<div class="row g-3">
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
                    <table class="table rateb-table rateb-oversight-table mb-0">
                        <thead>
                        <tr>
                            <th><?php echo __('companies'); ?></th>
                            <th><?php echo __('approval_type'); ?></th>
                            <th><?php echo __('reference'); ?></th>
                            <th><?php echo __('created_at'); ?></th>
                            <th class="rateb-actions-cell"><?php echo __('actions'); ?></th>
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
                            ?>
                        <tr>
                            <td><?php echo Rateb\App\Core\View::escape((string) ($row['company_name'] ?? '')); ?></td>
                            <td>
                                <?php echo Rateb\App\Core\View::escape((string) ($row['type_label'] ?? '')); ?>
                                <?php if (!empty($row['workflow_name'])) { ?>
                                <div class="small text-muted"><?php echo Rateb\App\Core\View::escape((string) $row['workflow_name']); ?></div>
                                <?php } ?>
                            </td>
                            <td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($row['reference'] ?? '')); ?></td>
                            <td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($row['submitted_at'] ?? '')); ?></td>
                            <td class="rateb-actions-cell">
                                <div class="rateb-actions d-flex flex-wrap gap-1 justify-content-end">
                                    <?php if (!empty($row['view_url'])) { ?>
                                    <a href="<?php echo Rateb\App\Core\View::escape((string) $row['view_url']); ?>" class="btn btn-sm btn-outline-info" target="_blank" rel="noopener" title="<?php echo __('view'); ?>">
                                        <i class="fas fa-eye"></i><span class="d-none d-xl-inline ms-1"><?php echo __('view'); ?></span>
                                    </a>
                                    <?php } ?>
                                    <form method="post" action="<?php echo rateb_url('admin/oversight/approvals/approve'); ?>" class="d-inline">
                                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrfToken); ?>">
                                        <input type="hidden" name="source_key" value="<?php echo Rateb\App\Core\View::escape($sourceKey); ?>">
                                        <input type="hidden" name="record_id" value="<?php echo $recordId; ?>">
                                        <input type="hidden" name="company_id" value="<?php echo $companyId; ?>">
                                        <input type="hidden" name="type_filter" value="<?php echo Rateb\App\Core\View::escape($typeFilter); ?>">
                                        <button type="submit" class="btn btn-sm btn-success" title="<?php echo __('approve'); ?>">
                                            <i class="fas fa-check"></i><span class="d-none d-xl-inline ms-1"><?php echo __('approve'); ?></span>
                                        </button>
                                    </form>
                                    <?php if ($canReject) { ?>
                                    <form method="post" action="<?php echo rateb_url('admin/oversight/approvals/reject'); ?>" class="d-inline" data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('confirm_reject')); ?>">
                                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrfToken); ?>">
                                        <input type="hidden" name="source_key" value="<?php echo Rateb\App\Core\View::escape($sourceKey); ?>">
                                        <input type="hidden" name="record_id" value="<?php echo $recordId; ?>">
                                        <input type="hidden" name="company_id" value="<?php echo $companyId; ?>">
                                        <input type="hidden" name="type_filter" value="<?php echo Rateb\App\Core\View::escape($typeFilter); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="<?php echo __('reject'); ?>">
                                            <i class="fas fa-times"></i><span class="d-none d-xl-inline ms-1"><?php echo __('reject'); ?></span>
                                        </button>
                                    </form>
                                    <?php } ?>
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
