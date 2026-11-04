<?php
declare(strict_types=1);
use Rateb\App\Core\View;
?>
<h1 class="h4 mb-3"><?php echo View::escape((string) ($title ?? '')); ?></h1>
<?php if (!empty($missing_release)): ?><p class="text-muted"><?php echo View::escape(__('auto_open_no_release')); ?></p><?php return; ?><?php endif; ?>
<form method="get" action="<?php echo rateb_app_url('automotive/open-catalog/models'); ?>" class="row g-2 mb-3">
    <div class="col-md-3"><input class="form-control" name="q" value="<?php echo View::escape((string) ($q ?? '')); ?>"></div>
    <div class="col-md-2"><input class="form-control" name="market" value="<?php echo View::escape((string) ($market ?? '')); ?>" placeholder="<?php echo View::escape(__('auto_open_market')); ?>"></div>
    <input type="hidden" name="kind" value="<?php echo View::escape((string) ($kind ?? '')); ?>">
    <input type="hidden" name="make" value="<?php echo View::escape((string) ($make ?? '')); ?>">
    <div class="col-md-2"><button class="btn btn-primary" type="submit"><?php echo View::escape(__('auto_epc_search')); ?></button></div>
</form>
<ul>
<?php foreach (($rows ?? []) as $row): ?>
<li><a href="<?php echo rateb_app_url('automotive/open-catalog/models/' . (int) $row['id']); ?>"><?php echo View::escape((string) $row['make_slug'] . ' ' . (string) $row['name_en']); ?></a></li>
<?php endforeach; ?>
</ul>
<?php if (($rows ?? []) === []): ?><p class="text-muted"><?php echo View::escape(__('auto_open_empty')); ?></p><?php endif; ?>
<p><?php echo View::escape(__('auto_open_page')); ?> <?php echo (int) ($page ?? 1); ?> / <?php echo (int) ($total ?? 0); ?></p>
