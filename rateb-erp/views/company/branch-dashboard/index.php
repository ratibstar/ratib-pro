<?php declare(strict_types=1); ?>
<div class="rateb-page-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><?php echo Rateb\App\Core\View::escape($title ?? __('branch_dashboard')); ?></h1>
    <?php if (!empty($isHeadOffice)) {
        Rateb\App\Core\View::partial('branch-filter-switcher', ['branches' => $branches ?? [], 'activeFilter' => $activeFilter ?? 0]);
    } ?>
</div>

<div class="row g-3">
<?php foreach ($rows ?? [] as $row) { ?>
    <div class="col-md-6 col-xl-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-header d-flex justify-content-between">
                <span><?php echo Rateb\App\Core\View::escape(trim((string) ($row['name'] ?? ''))); ?></span>
                <small class="text-muted"><?php echo Rateb\App\Core\View::escape((string) ($row['code'] ?? '')); ?></small>
            </div>
            <div class="rateb-card-body small">
                <div class="d-flex justify-content-between"><span><?php echo __('sales_total'); ?></span><strong><?php echo number_format((float) ($row['sales_total'] ?? 0), 2); ?></strong></div>
                <div class="d-flex justify-content-between"><span><?php echo __('purchases_total'); ?></span><strong><?php echo number_format((float) ($row['purchases_total'] ?? 0), 2); ?></strong></div>
                <div class="d-flex justify-content-between"><span><?php echo __('expenses_total'); ?></span><strong><?php echo number_format((float) ($row['expenses_total'] ?? 0), 2); ?></strong></div>
                <div class="d-flex justify-content-between"><span><?php echo __('profit_total'); ?></span><strong class="text-success"><?php echo number_format((float) ($row['profit_total'] ?? 0), 2); ?></strong></div>
                <div class="d-flex justify-content-between"><span><?php echo __('employees_count'); ?></span><strong><?php echo (int) ($row['employees_count'] ?? 0); ?></strong></div>
                <div class="d-flex justify-content-between"><span><?php echo __('inventory_value'); ?></span><strong><?php echo number_format((float) ($row['inventory_value'] ?? 0), 2); ?></strong></div>
            </div>
        </div>
    </div>
<?php } ?>
</div>

<div class="mt-3 d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-primary btn-sm" href="<?php echo rateb_url(rateb_app_route('branch-dashboard/compare')); ?>"><?php echo __('branch_comparison'); ?></a>
    <a class="btn btn-outline-secondary btn-sm" href="<?php echo rateb_url(rateb_app_route('branch-dashboard/reports')); ?>"><?php echo __('branch_reports'); ?></a>
</div>
