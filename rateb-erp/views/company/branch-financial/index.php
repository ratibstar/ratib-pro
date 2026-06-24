<?php declare(strict_types=1); ?>
<div class="rateb-page-header mb-3"><h1 class="h4 mb-0"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1></div>
<div class="row g-3">
    <div class="col-md-4"><a class="rateb-card d-block p-3 text-decoration-none" href="<?php echo rateb_url(rateb_app_route('branch-financial/profit-loss')); ?>"><?php echo __('branch_pl_report'); ?></a></div>
    <div class="col-md-4"><a class="rateb-card d-block p-3 text-decoration-none" href="<?php echo rateb_url(rateb_app_route('branch-financial/balance-sheet')); ?>"><?php echo __('branch_bs_report'); ?></a></div>
    <div class="col-md-4"><a class="rateb-card d-block p-3 text-decoration-none" href="<?php echo rateb_url(rateb_app_route('branch-financial/cash-flow')); ?>"><?php echo __('branch_cf_report'); ?></a></div>
    <?php if (!empty($canConsolidated)) { ?>
    <div class="col-md-4"><a class="rateb-card d-block p-3 text-decoration-none" href="<?php echo rateb_url(rateb_app_route('branch-financial/consolidated')); ?>"><?php echo __('consolidated_financial_reports'); ?></a></div>
    <?php } ?>
</div>
