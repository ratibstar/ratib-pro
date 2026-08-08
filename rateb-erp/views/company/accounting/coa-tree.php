<?php
/** Full COA tree — hierarchical view with balances */
$tree = $tree ?? [];
$typeTotals = $typeTotals ?? [];
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0"><i class="fas fa-sitemap me-2 text-primary"></i><?php echo __('coa_full_tree'); ?></h5>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?php echo rateb_app_url('chart-of-accounts'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-list"></i> <?php echo __('chart_of_accounts'); ?>
        </a>
        <?php if ($createEnabled ?? false) { ?>
        <a href="<?php echo rateb_app_url('chart-of-accounts/create'); ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> <?php echo __('add_account'); ?>
        </a>
        <?php } ?>
    </div>
</div>

<?php if (!empty($typeTotals)) { ?>
<div class="row g-2 mb-3">
    <?php foreach ($typeTotals as $type => $total) { ?>
    <div class="col-md col-6">
        <div class="rateb-approval-stat rateb-approval-stat-total py-2 px-3">
            <div class="rateb-approval-stat-label small"><?php echo __($type); ?></div>
            <div class="rateb-approval-stat-value" style="font-size:1.1rem"><?php echo number_format((float) $total, 2); ?></div>
        </div>
    </div>
    <?php } ?>
</div>
<?php } ?>

<?php
Rateb\App\Core\View::partial('coa-tree', array_merge(get_defined_vars(), [
    'fullTreeMode' => true,
    'treeTitle' => __('coa_full_tree'),
]));
?>
<p class="text-center text-muted small mt-3 mb-0"><?php echo __('developed_by_rateb_tech'); ?></p>
