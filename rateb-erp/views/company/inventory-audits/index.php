<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo Rateb\App\Core\View::escape($title ?? __('inventory_audits')); ?></span>
        <a href="<?php echo rateb_url('company/inventory-audits/create'); ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> <?php echo __('create'); ?></a>
    </div>
    <div class="rateb-card-body">
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $items ?? [],
            'columns' => [
                ['name' => 'audit_no', 'label' => 'audit_no'],
                ['name' => 'audit_date', 'label' => 'audit_date'],
                ['name' => 'status', 'label' => 'status'],
                ['name' => 'created_at', 'label' => 'created_at'],
            ],
        ]); ?>
        <?php if (!empty($items)) { ?>
        <div class="mt-3 d-flex flex-wrap gap-1">
            <?php foreach ($items as $row) { ?>
            <a class="btn btn-sm btn-outline-primary" href="<?php echo rateb_url('company/inventory-audits/' . (int) $row['id']); ?>"><?php echo __('view'); ?> <?php echo Rateb\App\Core\View::escape($row['audit_no'] ?? ''); ?></a>
            <?php } ?>
        </div>
        <?php } ?>
    </div>
</div>
