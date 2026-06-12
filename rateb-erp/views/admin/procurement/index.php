<div class="row g-3">
    <?php if (!empty($companies)) { ?>
    <div class="col-12">
        <form method="get" class="row g-2">
            <div class="col-md-4">
                <select class="form-select" name="company_id" onchange="this.form.submit()">
                    <option value=""><?php echo __('all_companies'); ?></option>
                    <?php foreach ($companies as $c) { ?>
                    <option value="<?php echo (int) $c['id']; ?>"<?php echo (int)($_GET['company_id'] ?? 0) === (int)$c['id'] ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($c['name'] ?? ''); ?></option>
                    <?php } ?>
                </select>
            </div>
        </form>
    </div>
    <?php } ?>
    <div class="col-md-6">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('purchase_requests'); ?> — <?php echo __('status'); ?></div>
            <div class="rateb-card-body d-flex flex-wrap gap-2">
                <?php if (empty($pr_stats)) { ?>
                <span class="text-muted"><?php echo __('no_records'); ?></span>
                <?php } else { foreach ($pr_stats as $st => $cnt) { ?>
                <span class="badge bg-secondary"><?php echo Rateb\App\Core\View::escape($st); ?>: <?php echo (int) $cnt; ?></span>
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
                <span class="badge bg-secondary"><?php echo Rateb\App\Core\View::escape($st); ?>: <?php echo (int) $cnt; ?></span>
                <?php } } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('purchase_requests'); ?></div>
            <div class="rateb-card-body p-0">
                <?php Rateb\App\Core\View::partial('crud-index', ['title' => '', 'items' => $purchase_requests ?? [], 'csrf' => $csrf, 'routePrefix' => 'admin/procurement', 'bulkEnabled' => false, 'createEnabled' => false, 'actionsEnabled' => false]); ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('purchase_orders'); ?></div>
            <div class="rateb-card-body p-0">
                <?php Rateb\App\Core\View::partial('crud-index', ['title' => '', 'items' => $purchase_orders ?? [], 'csrf' => $csrf, 'routePrefix' => 'admin/procurement', 'bulkEnabled' => false, 'createEnabled' => false, 'actionsEnabled' => false]); ?>
            </div>
        </div>
    </div>
</div>
