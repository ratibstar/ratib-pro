<?php
declare(strict_types=1);
use Rateb\App\Core\View;
?>
<h1 class="h4 mb-3"><?php echo View::escape((string) ($title ?? '')); ?></h1>
<?php if (!empty($missing_release)): ?><p class="text-muted"><?php echo View::escape(__('auto_open_no_release')); ?></p><?php return; ?><?php endif; ?>
<form method="get" action="<?php echo rateb_app_url('automotive/open-catalog/makes'); ?>" class="row g-2 mb-3">
    <div class="col-md-4"><input class="form-control" name="q" value="<?php echo View::escape((string) ($q ?? '')); ?>"></div>
    <input type="hidden" name="kind" value="<?php echo View::escape((string) ($kind ?? '')); ?>">
    <div class="col-md-2"><button class="btn btn-primary" type="submit"><?php echo View::escape(__('auto_epc_search')); ?></button></div>
</form>
<ul>
<?php foreach (($rows ?? []) as $row): ?>
<li><a href="<?php echo rateb_app_url('automotive/open-catalog/models'); ?>?kind=<?php echo View::escape((string) ($kind ?? '')); ?>&make=<?php echo View::escape((string) $row['make_slug']); ?>"><?php echo View::escape((string) $row['make_slug']); ?> (<?php echo (int) $row['models']; ?>)</a></li>
<?php endforeach; ?>
</ul>
<?php if (($rows ?? []) === []): ?><p class="text-muted"><?php echo View::escape(__('auto_open_empty')); ?></p><?php endif; ?>
<p><?php echo View::escape(__('auto_open_page')); ?> <?php echo (int) ($page ?? 1); ?></p>
