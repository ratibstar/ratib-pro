<?php
/** @var array<int, array<string, mixed>> $items */
$items = $items ?? [];
$pendingCount = (int) ($pendingCount ?? count($items));
$routePrefix = $routePrefix ?? rateb_app_route('supplier-evaluations');
$csrf = $csrf ?? '';
$svc = new \Rateb\App\Services\SupplierEvaluationService();
?>
<div class="mb-3 d-flex flex-wrap gap-2 align-items-center justify-content-between">
    <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-right"></i> <?php echo __('supplier_evaluations'); ?>
    </a>
    <span class="badge bg-warning text-dark">
        <?php echo __('pending'); ?>: <?php echo $pendingCount; ?>
    </span>
</div>
<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-clipboard-check me-1"></i> <?php echo __('evaluation_approvals'); ?></span>
    </div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table rateb-eval-approvals-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('evaluation_no'); ?></th>
                    <th><?php echo __('suppliers'); ?></th>
                    <th><?php echo __('evaluation_date'); ?></th>
                    <th><?php echo __('evaluator_name'); ?></th>
                    <th><?php echo __('overall_score'); ?></th>
                    <th><?php echo __('evaluation_percent'); ?></th>
                    <th><?php echo __('supplier_rating_tier'); ?></th>
                    <th class="rateb-actions-cell"><?php echo __('actions'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($items === []) { ?>
                <tr><td colspan="8" class="text-center text-muted py-4"><?php echo __('no_pending_approvals'); ?></td></tr>
                <?php } else {
                    foreach ($items as $row) {
                        $id = (int) ($row['id'] ?? 0);
                        $tier = (string) ($row['rating_tier'] ?? 'weak');
                        ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['evaluation_no'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['supplier_name'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::formatDate((string) ($row['evaluation_date'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['evaluator_name'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['overall_score'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['score_percent'] ?? '')); ?>%</td>
                    <td><span class="badge bg-<?php echo Rateb\App\Core\View::escape($svc->tierBadgeClass($tier)); ?>"><?php echo Rateb\App\Core\View::escape($svc->tierLabel($tier)); ?></span></td>
                    <td class="rateb-actions-cell">
                        <div class="rateb-actions rateb-approval-actions">
                            <a href="<?php echo rateb_url($routePrefix . '/' . $id . '/edit'); ?>" class="btn btn-sm btn-outline-secondary"
                                title="<?php echo Rateb\App\Core\View::escape(__('view')); ?>"
                                aria-label="<?php echo Rateb\App\Core\View::escape(__('view')); ?>">
                                <i class="fas fa-eye"></i>
                            </a>
                            <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $id . '/approve'); ?>" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                                <button type="submit" class="btn btn-sm btn-success"
                                    title="<?php echo Rateb\App\Core\View::escape(__('approve_evaluation')); ?>"
                                    aria-label="<?php echo Rateb\App\Core\View::escape(__('approve_evaluation')); ?>">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                            <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $id . '/reject'); ?>" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                                <button type="submit" class="btn btn-sm btn-danger"
                                    title="<?php echo Rateb\App\Core\View::escape(__('reject_evaluation')); ?>"
                                    aria-label="<?php echo Rateb\App\Core\View::escape(__('reject_evaluation')); ?>">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php }
                } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
