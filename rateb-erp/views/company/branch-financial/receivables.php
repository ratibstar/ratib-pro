<?php declare(strict_types=1); $r=$report??[]; ?>
<h1 class="h4 mb-3"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1>
<table class="table table-sm"><thead><tr><th><?php echo __('branch'); ?></th><th><?php echo __('open'); ?></th><th><?php echo __('count'); ?></th></tr></thead><tbody>
<?php foreach (($r['rows'] ?? []) as $row) { ?><tr><td><?php echo Rateb\App\Core\View::escape((string)($row['branch_name']??'')); ?></td><td><?php echo number_format((float)($row['open_total']??0),2); ?></td><td><?php echo (int)($row['row_count']??0); ?></td></tr><?php } ?>
</tbody><tfoot><tr><th><?php echo __('total'); ?></th><th><?php echo number_format((float)($r['grand_open']??0),2); ?></th><th></th></tr></tfoot></table>
