<?php Rateb\App\Core\View::partial('admin-company-portal-banner'); ?>
<div class="row g-3">
    <?php Rateb\App\Core\View::partial('admin-oversight-filters', [
        'companies' => $companies ?? [],
        'filters' => $filters ?? [],
        'statusOptions' => $statusOptions ?? [],
        'formAction' => $formAction ?? rateb_url('admin/oversight/procurement'),
    ]); ?>
    <div class="col-md-6">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('purchase_requests'); ?> — <?php echo __('status'); ?></div>
            <div class="rateb-card-body d-flex flex-wrap gap-2">
                <?php if (empty($pr_stats)) { ?>
                <span class="text-muted"><?php echo __('no_records'); ?></span>
                <?php } else { foreach ($pr_stats as $st => $cnt) { ?>
                <span class="badge bg-secondary"><?php echo __( (string) $st); ?>: <?php echo (int) $cnt; ?></span>
                <?php } } ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('purchase_orders'); ?> — <?php echo __('status'); ?></div>
            <div class="rateb-card-body d-flex flex-wrap gap-2">
                <?php if (empty($po_stats)) { ?>
                <span class="text-muted"><?php echo __('no_records'); ?></span>
                <?php } else { foreach ($po_stats as $st => $cnt) { ?>
                <span class="badge bg-secondary"><?php echo __( (string) $st); ?>: <?php echo (int) $cnt; ?></span>
                <?php } } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('purchase_requests'); ?></div>
            <div class="rateb-card-body p-0">
                <?php Rateb\App\Core\View::partial('crud-index', [
                    'title' => '',
                    'items' => $purchase_requests ?? [],
                    'fields' => $prFields ?? [],
                    'csrf' => $csrf,
                    'routePrefix' => 'admin/oversight/procurement',
                    'bulkEnabled' => false,
                    'createEnabled' => false,
                    'actionsEnabled' => false,
                    'exportEnabled' => false,
                    'searchEnabled' => false,
                ]); ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('purchase_orders'); ?></div>
            <div class="rateb-card-body p-0">
                <?php Rateb\App\Core\View::partial('crud-index', [
                    'title' => '',
                    'items' => $purchase_orders ?? [],
                    'fields' => $poFields ?? [],
                    'csrf' => $csrf,
                    'routePrefix' => 'admin/oversight/procurement',
                    'bulkEnabled' => false,
                    'createEnabled' => false,
                    'actionsEnabled' => false,
                    'exportEnabled' => false,
                    'searchEnabled' => false,
                ]); ?>
            </div>
        </div>
    </div>
</div>
