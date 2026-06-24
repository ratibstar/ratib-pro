<?php
/** @var bool $pendingOnly */
Rateb\App\Core\View::partial('admin-company-portal-banner');
?>
<div class="row g-3">
    <?php Rateb\App\Core\View::partial('admin-oversight-filters', [
        'companies' => $companies ?? [],
        'filters' => $filters ?? [],
        'statusOptions' => $statusOptions ?? [],
        'formAction' => $formAction ?? rateb_url('admin/oversight/supplier-evaluations'),
    ]); ?>
    <div class="col-12">
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo rateb_url('admin/oversight/supplier-evaluations'); ?>" class="btn btn-sm <?php echo empty($pendingOnly) ? 'btn-primary' : 'btn-outline-primary'; ?>">
                <?php echo __('all_companies'); ?> / <?php echo __('all_statuses'); ?>
            </a>
            <a href="<?php echo rateb_url('admin/oversight/supplier-evaluations?pending=1'); ?>" class="btn btn-sm <?php echo !empty($pendingOnly) ? 'btn-warning' : 'btn-outline-warning'; ?>">
                <?php echo __('manager_approval_pending'); ?>
            </a>
            <a href="<?php echo rateb_url('admin/oversight/approvals'); ?>" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-check-double"></i> <?php echo __('approvals_oversight'); ?>
            </a>
        </div>
    </div>
    <div class="col-12">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? __('supplier_evaluations_oversight')); ?></div>
            <div class="rateb-card-body p-0">
                <div class="table-responsive rateb-oversight-table-wrap">
                    <table class="table rateb-table rateb-oversight-table mb-0">
                        <thead>
                        <tr>
                            <th><?php echo __('companies'); ?></th>
                            <th><?php echo __('suppliers'); ?></th>
                            <th><?php echo __('evaluation_no'); ?></th>
                            <th><?php echo __('evaluation_date'); ?></th>
                            <th><?php echo __('overall_score'); ?></th>
                            <th><?php echo __('manager_approval'); ?></th>
                            <th><?php echo __('status'); ?></th>
                            <th><?php echo __('actions'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($items)) { ?>
                        <tr><td colspan="8" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                        <?php } else { foreach ($items as $row) {
                            $companyId = (int) ($row['company_id'] ?? 0);
                            $entityId = (int) ($row['id'] ?? 0);
                            $approval = (string) ($row['manager_approval'] ?? 'pending');
                            $viewUrl = rateb_url(rateb_app_route('supplier-evaluations/' . $entityId));
                            if ($companyId > 0) {
                                $viewUrl .= (str_contains($viewUrl, '?') ? '&' : '?') . 'company_id=' . $companyId;
                            }
                            ?>
                        <tr>
                            <td><?php echo Rateb\App\Core\View::escape($row['company_name'] ?? ''); ?></td>
                            <td><?php echo Rateb\App\Core\View::escape($row['supplier_name'] ?? ''); ?></td>
                            <td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape($row['evaluation_no'] ?? ('#' . $entityId)); ?></td>
                            <td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape($row['evaluation_date'] ?? ''); ?></td>
                            <td><strong><?php echo Rateb\App\Core\View::escape($row['overall_score'] ?? ''); ?></strong></td>
                            <td><span class="badge bg-<?php echo $approval === 'pending' ? 'warning text-dark' : ($approval === 'approved' ? 'success' : 'danger'); ?>"><?php echo __('manager_approval_' . $approval); ?></span></td>
                            <td><?php echo __((string) ($row['status'] ?? '')); ?></td>
                            <td>
                                <a href="<?php echo Rateb\App\Core\View::escape($viewUrl); ?>" class="btn btn-sm btn-outline-info" target="_blank" rel="noopener">
                                    <i class="fas fa-eye"></i> <?php echo __('view'); ?>
                                </a>
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
