<?php declare(strict_types=1); $r=$report??[]; $type=$type??'pl'; ?>
<h1 class="h4 mb-3"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1>
<div class="btn-group mb-3">
<a class="btn btn-sm btn-outline-secondary <?php echo $type==='pl'?'active':''; ?>" href="<?php echo rateb_url(rateb_app_route('branch-financial/consolidated?type=pl')); ?>">P&amp;L</a>
<a class="btn btn-sm btn-outline-secondary <?php echo $type==='bs'?'active':''; ?>" href="<?php echo rateb_url(rateb_app_route('branch-financial/consolidated?type=bs')); ?>"><?php echo __('balance_sheet'); ?></a>
<a class="btn btn-sm btn-outline-secondary <?php echo $type==='cf'?'active':''; ?>" href="<?php echo rateb_url(rateb_app_route('branch-financial/consolidated?type=cf')); ?>"><?php echo __('cash_flow'); ?></a>
</div>
<?php if ($type === 'pl') { ?>
<div class="row g-3 mb-3"><div class="col-md-4"><?php echo __('revenue'); ?>: <strong><?php echo number_format((float)($r['revenue']??0),2); ?></strong></div>
<div class="col-md-4"><?php echo __('expenses_total'); ?>: <strong><?php echo number_format((float)($r['expenses']??0),2); ?></strong></div>
<div class="col-md-4"><?php echo __('profit_total'); ?>: <strong><?php echo number_format((float)($r['net']??0),2); ?></strong></div></div>
<?php } elseif ($type === 'bs') { ?>
<div class="row g-3 mb-3"><div class="col-md-4"><?php echo __('assets'); ?>: <strong><?php echo number_format((float)($r['assets']??0),2); ?></strong></div>
<div class="col-md-4"><?php echo __('liabilities'); ?>: <strong><?php echo number_format((float)($r['liabilities']??0),2); ?></strong></div>
<div class="col-md-4"><?php echo __('equity'); ?>: <strong><?php echo number_format((float)($r['equity']??0),2); ?></strong></div></div>
<?php } else { ?>
<div class="mb-3"><?php echo __('net_cash_flow'); ?>: <strong><?php echo number_format((float)($r['net_cash_flow']??0),2); ?></strong></div>
<?php } ?>
<?php if (!empty($r['elimination'])) { ?><div class="alert alert-secondary small"><?php echo __('elimination_applied'); ?>: <?php echo json_encode($r['elimination'], JSON_UNESCAPED_UNICODE); ?></div><?php } ?>
<?php if (!empty($interBranch)) { ?>
<h2 class="h6"><?php echo __('interbranch_balances'); ?></h2>
<table class="table table-sm"><thead><tr><th><?php echo __('branch'); ?></th><th>1350</th><th>2150</th></tr></thead><tbody>
<?php foreach ($interBranch as $row) { ?><tr><td><?php echo Rateb\App\Core\View::escape((string)($row['branch_name']??'')); ?></td><td><?php echo number_format((float)($row['due_from']??0),2); ?></td><td><?php echo number_format((float)($row['due_to']??0),2); ?></td></tr><?php } ?>
</tbody></table>
<?php } ?>
