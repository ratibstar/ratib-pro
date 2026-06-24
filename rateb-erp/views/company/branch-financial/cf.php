<?php declare(strict_types=1); ?>
<h1 class="h4 mb-3"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1>
<form method="get" class="rateb-card mb-3"><div class="rateb-card-body row g-2 align-items-end">
<div class="col-md-3"><label class="form-label"><?php echo __('branch'); ?></label><select name="branch_id" class="form-select"><?php foreach ($branches ?? [] as $b) { ?><option value="<?php echo (int)$b['id']; ?>" <?php echo (int)($branchId??0)===(int)$b['id']?'selected':''; ?>><?php echo Rateb\App\Core\View::escape((string)$b['name']); ?></option><?php } ?></select></div>
<div class="col-md-2"><label class="form-label"><?php echo __('from'); ?></label><input type="date" name="from" class="form-control" value="<?php echo Rateb\App\Core\View::escape($from ?? ''); ?>"></div>
<div class="col-md-2"><label class="form-label"><?php echo __('to'); ?></label><input type="date" name="to" class="form-control" value="<?php echo Rateb\App\Core\View::escape($to ?? ''); ?>"></div>
<div class="col-md-2"><button class="btn btn-primary"><?php echo __('show'); ?></button></div>
</div></form>
<?php if (!empty($report)) { $r=$report; ?>
<div class="row g-3">
<div class="col-md-3"><div class="rateb-card p-3"><?php echo __('operating'); ?>: <strong><?php echo number_format((float)($r['operating']??0),2); ?></strong></div></div>
<div class="col-md-3"><div class="rateb-card p-3"><?php echo __('investing'); ?>: <strong><?php echo number_format((float)($r['investing']??0),2); ?></strong></div></div>
<div class="col-md-3"><div class="rateb-card p-3"><?php echo __('financing'); ?>: <strong><?php echo number_format((float)($r['financing']??0),2); ?></strong></div></div>
<div class="col-md-3"><div class="rateb-card p-3"><?php echo __('net_cash_flow'); ?>: <strong><?php echo number_format((float)($r['net_cash_flow']??0),2); ?></strong></div></div>
</div>
<?php } ?>
