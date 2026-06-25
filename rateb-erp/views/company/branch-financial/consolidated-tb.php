<?php declare(strict_types=1); $r=$report??[]; ?>
<h1 class="h4 mb-3"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1>
<table class="table table-sm table-striped"><thead><tr><th><?php echo __('code'); ?></th><th><?php echo __('name'); ?></th><th><?php echo __('debit'); ?></th><th><?php echo __('credit'); ?></th></tr></thead><tbody>
<?php foreach (($r['lines'] ?? []) as $line) { ?>
<tr><td><?php echo Rateb\App\Core\View::escape((string)($line['code']??'')); ?></td><td><?php echo Rateb\App\Core\View::escape((string)($line['name']??'')); ?></td><td><?php echo number_format((float)($line['total_debit']??0),2); ?></td><td><?php echo number_format((float)($line['total_credit']??0),2); ?></td></tr>
<?php } ?>
</tbody></table>
