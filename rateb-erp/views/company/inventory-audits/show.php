<?php $audit = $audit ?? []; $lines = $lines ?? []; ?>
<div class="rateb-card mb-4">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo Rateb\App\Core\View::escape($audit['audit_no'] ?? __('inventory_audits')); ?></span>
        <?php if (($audit['status'] ?? '') !== 'completed' && ($canManage ?? rateb_can_manage_entity('inventory-audits'))) { ?>
        <form method="post" action="<?php echo rateb_app_url('inventory-audits/' . (int) $audit['id'] . '/reconcile'); ?>" class="d-inline" data-rateb-confirm="<?php echo Rateb\App\Core\View::escape(__('reconcile_confirm')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <button type="submit" class="btn btn-warning btn-sm"><i class="fas fa-balance-scale"></i> <?php echo __('stock_reconciliation'); ?></button>
        </form>
        <?php } ?>
    </div>
    <div class="rateb-card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-3"><span class="text-muted"><?php echo __('status'); ?>:</span> <?php echo Rateb\App\Core\View::escape($audit['status'] ?? ''); ?></div>
            <div class="col-md-3"><span class="text-muted"><?php echo __('audit_date'); ?>:</span> <?php echo Rateb\App\Core\View::escape($audit['audit_date'] ?? ''); ?></div>
            <div class="col-md-6"><span class="text-muted"><?php echo __('notes'); ?>:</span> <?php echo Rateb\App\Core\View::escape($audit['notes'] ?? '—'); ?></div>
        </div>
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $lines,
            'columns' => [
                ['name' => 'item_name', 'label' => 'item_name'],
                ['name' => 'sku', 'label' => 'sku'],
                ['name' => 'system_qty', 'label' => 'system_qty'],
                ['name' => 'counted_qty', 'label' => 'counted_qty'],
                ['name' => 'variance', 'label' => 'variance'],
            ],
        ]); ?>
    </div>
</div>
<a href="<?php echo rateb_app_url('inventory-audits'); ?>" class="btn btn-outline-secondary"><?php echo __('back_to_list'); ?></a>
