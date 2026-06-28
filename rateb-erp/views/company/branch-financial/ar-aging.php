<?php declare(strict_types=1); $r=$report??[]; $labels=['current'=>'Current','1_30'=>'1-30','31_60'=>'31-60','61_90'=>'61-90','over_90'=>'90+']; ?>
<h1 class="h4 mb-3"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1>
<p class="text-muted small"><?php echo __('as_of'); ?>: <?php echo Rateb\App\Core\View::escape((string)($r['as_of']??'')); ?></p>
<div class="table-responsive">
<table class="table table-sm rateb-table mb-0"><thead><tr><th><?php echo __('branch'); ?></th><?php foreach ($labels as $k=>$lbl) { ?><th><?php echo Rateb\App\Core\View::escape($lbl); ?></th><?php } ?><th><?php echo __('total'); ?></th></tr></thead><tbody>
<?php foreach (($r['branches'] ?? []) as $row) { ?><tr><td><?php echo Rateb\App\Core\View::escape((string)($row['branch_name']??'')); ?></td><?php foreach ($labels as $k=>$lbl) { ?><td><?php echo number_format((float)(($row['buckets'][$k]??0)),2); ?></td><?php } ?><td><?php echo number_format((float)($row['total']??0),2); ?></td></tr><?php } ?>
</tbody></table>
</div>
