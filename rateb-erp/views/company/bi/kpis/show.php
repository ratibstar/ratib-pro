<?php declare(strict_types=1); /** @var array<string,mixed> $item */ /** @var list<string> $transitions */ /** @var bool $canTransition */ ?>
<div class="container-fluid py-3">
<h1 class="h3 mb-3"><?php echo htmlspecialchars((string)($title??''), ENT_QUOTES,'UTF-8'); ?></h1>
<div class="card mb-4"><div class="card-body">
<p><strong><?php echo htmlspecialchars(__('code'), ENT_QUOTES,'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string)($item['code']??''), ENT_QUOTES,'UTF-8'); ?></p>
<p><strong>metric:</strong> <?php echo htmlspecialchars((string)($item['metric_key']??''), ENT_QUOTES,'UTF-8'); ?></p>
<p><strong><?php echo htmlspecialchars(__('status'), ENT_QUOTES,'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string)($item['workflow_status']??''), ENT_QUOTES,'UTF-8'); ?></p>
<?php if(!empty($canTransition) && ($transitions??[])!==[]): ?>
<form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('bi/kpis').'/'.(int)($item['id']??0).'/transition'), ENT_QUOTES,'UTF-8'); ?>" class="row g-2"><?php echo rateb_csrf_field(); ?>
<input type="hidden" name="expected_version" value="<?php echo (int)($item['version']??1); ?>">
<div class="col-auto"><select name="to_status" class="form-select"><?php foreach($transitions as $st): ?><option value="<?php echo htmlspecialchars($st, ENT_QUOTES,'UTF-8'); ?>"><?php echo htmlspecialchars($st, ENT_QUOTES,'UTF-8'); ?></option><?php endforeach; ?></select></div>
<div class="col-auto"><button class="btn btn-primary"><?php echo htmlspecialchars(__('transition'), ENT_QUOTES,'UTF-8'); ?></button></div>
</form><?php endif; ?>
</div></div></div>
