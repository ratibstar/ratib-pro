<?php declare(strict_types=1); $r=$report??[]; ?>
<h1 class="h4 mb-3"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1>
<form class="row g-2 mb-3" method="get"><div class="col-auto"><input type="date" name="from" value="<?php echo Rateb\App\Core\View::escape($from ?? ''); ?>" class="form-control form-control-sm"></div><div class="col-auto"><input type="date" name="to" value="<?php echo Rateb\App\Core\View::escape($to ?? ''); ?>" class="form-control form-control-sm"></div><div class="col-auto"><button class="btn btn-sm btn-primary"><?php echo __('show'); ?></button></div></form>
<table class="table table-sm table-striped"><thead><tr><th><?php echo __('date'); ?></th><th><?php echo __('entry_no'); ?></th><th><?php echo __('branch'); ?></th><th><?php echo __('code'); ?></th><th><?php echo __('debit'); ?></th><th><?php echo __('credit'); ?></th></tr></thead><tbody>
<?php foreach (($r['entries'] ?? []) as $line) { ?>
<tr><td><?php echo Rateb\App\Core\View::formatDate((string)($line['entry_date']??'')); ?></td><td><?php echo Rateb\App\Core\View::formatDate((string)($line['entry_no']??'')); ?></td><td><?php echo (int)($line['branch_id']??0); ?></td><td><?php echo Rateb\App\Core\View::formatDate((string)($line['account_code']??'')); ?></td><td><?php echo number_format((float)($line['debit']??0),2); ?></td><td><?php echo number_format((float)($line['credit']??0),2); ?></td></tr>
<?php } ?>
</tbody></table>
